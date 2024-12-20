<?php

namespace App\Observers;

use App\Enums\ProjectStatus;
use App\Enums\SubProjectStatus;
use App\Jobs\Workflows\UpdateProjectClientInsideWorkflow;
use App\Jobs\Workflows\UpdateProjectDeadlineInsideWorkflow;
use App\Jobs\Workflows\UpdateProjectManagerInsideWorkflow;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\Sequence;
use App\Models\SubProject;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Services\AuditLogMessageBuilder;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class ProjectObserver
{
    public function __construct(private readonly NotificationPublisher $notificationPublisher, private readonly AuditLogPublisher $auditLogPublisher)
    {
    }

    /**
     * Handle the Project "creating" event.
     */
    public function creating(Project $project): void
    {
        if ($project->ext_id == null) {
            $project->ext_id = collect([
                $project->institution->short_name,
                Carbon::now()->format('Y-m'),
                data_get($project->typeClassifierValue->meta, 'code', ''),
                $project->institution->institutionProjectSequence->incrementCurrentValue(),
            ])->implode('-');
        }
    }

    /**
     * Handle the Project "created" event.
     *
     * @throws Throwable
     */
    public function created(Project $project): void
    {
        $seq = new Sequence();
        $seq->sequenceable_id = $project->id;
        $seq->sequenceable_type = Project::class;
        $seq->name = Sequence::PROJECT_SUBPROJECT_SEQ;
        $seq->saveOrFail();

        if (filled($projectManager = $project->managerInstitutionUser) && filled($projectManager->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ProjectCreated,
                    'receiver_email' => $projectManager->email,
                    'receiver_name' => $projectManager->getUserFullName(),
                    'variables' => [
                        'project' => $project->only(['ext_id'])
                    ]
                ]),
                $project->institution_id
            );
        }
    }

    /**
     * @throws Throwable
     */
    public function updating(Project $project): void
    {
        $newProjectGotManager = $project->status === ProjectStatus::New &&
            $project->isDirty('manager_institution_user_id') &&
            is_null($project->getOriginal('manager_institution_user_id'));

        if ($newProjectGotManager) {
            $project->status = ProjectStatus::Registered;
            $project->subProjects->each(function (SubProject $subProject) {
                $subProject->status = SubProjectStatus::Registered;
                $subProject->saveOrFail();
            });
        }

        if ($project->isDirty('status')) {
            if ($project->status === ProjectStatus::Cancelled) {
                $project->cancelled_at = Carbon::now();

                $project->subProjects->each(function (SubProject $subProject) {
                    $subProject->status = SubProjectStatus::Cancelled;
                    $subProject->saveOrFail();
                });
            } elseif ($project->status === ProjectStatus::Accepted) {
                $project->accepted_at = Carbon::now();
            } elseif ($project->status === ProjectStatus::Corrected) {
                $project->corrected_at = Carbon::now();
            } elseif ($project->status === ProjectStatus::Rejected) {
                $project->rejected_at = Carbon::now();
            } elseif ($project->status === ProjectStatus::SubmittedToClient) {
                $project->submitted_to_client_review_at = Carbon::now();
            }

            if (Auth::check()) {
                $this->auditLogPublisher->publish(
                    AuditLogMessageBuilder::makeUsingJWT()
                        ->toModifyObjectEventComputingDiff(
                            $project->getAuditLogObjectType(),
                            $project->getIdentitySubset(),
                            $project->fresh()->getAuditLogRepresentation(),
                            $project->getAuditLogRepresentation(),
                        )
                );
            }
        }

        if ($project->isDirty('deadline_at') && filled($project->deadline_at)) {
            $project->deadline_notification_sent_at = null;
        }
    }

    /**
     * Handle the Project "updated" event.
     * @throws RequestException
     * @throws Throwable
     */
    public function updated(Project $project): void
    {
        if ($project->wasChanged('manager_institution_user_id') && filled($project->managerInstitutionUser)) {
            UpdateProjectManagerInsideWorkflow::dispatch($project);
            $this->publishPmOrClientAssignedToProject($project, $project->managerInstitutionUser);
        }

        if ($project->wasChanged('client_institution_user_id') && filled($project->clientInstitutionUser)) {
            UpdateProjectClientInsideWorkflow::dispatch($project);
            $this->publishPmOrClientAssignedToProject($project, $project->clientInstitutionUser);
        }

        if ($project->wasChanged('deadline_at') && filled($project->deadline_at)) {
            $project->subProjects->each(function (SubProject $subProject) use ($project) {
                if (empty($subProject->deadline_at) || $subProject->deadline_at > $project->deadline_at) {
                    $subProject->deadline_at = $project->deadline_at;
                    $subProject->saveOrFail();
                }
            });
            UpdateProjectDeadlineInsideWorkflow::dispatchSync($project);
        }

        if ($project->wasChanged('event_start_at')) {
            $project->assignments->each(function (Assignment $assignment) use ($project) {
                $assignment->event_start_at = $project->event_start_at;
                if (filled($project->event_start_at) && filled($assignment->deadline_at) && $assignment->deadline_at < $project->event_start_at) {
                    $assignment->event_start_at = null;
                }

                $assignment->saveOrFail();
            });
        }

        if ($project->wasChanged('status')) {
            if ($project->status === ProjectStatus::Cancelled) {
                filled($project->managerInstitutionUser) && $this->publishProjectCancelledEmailNotification($project, $project->managerInstitutionUser);
                filled($project->clientInstitutionUser) && $this->publishProjectCancelledEmailNotification($project, $project->clientInstitutionUser);
                $this->publishProjectCancelledEmailNotificationForVendors($project);
            } elseif ($project->status === ProjectStatus::SubmittedToClient || $project->status === ProjectStatus::Corrected) {
                $this->publishProjectSubmittedToClientEmailNotification($project);
                $this->publishProjectIsReadyForReviewEmailNotification($project);
            } elseif ($project->status === ProjectStatus::Accepted) {
                filled($project->managerInstitutionUser) && $this->publishProjectAcceptedEmailNotification($project, $project->managerInstitutionUser);
                filled($project->clientInstitutionUser) && $this->publishProjectAcceptedEmailNotification($project, $project->clientInstitutionUser);
            } elseif ($project->status === ProjectStatus::Registered) {
                $this->publishProjectRegisteredEmailNotification($project);
            }


        }
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        //
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        //
    }

    private function publishProjectSubmittedToClientEmailNotification(Project $project): void
    {
        $manager = $project->managerInstitutionUser;
        if (filled($manager?->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ProjectSentToClient,
                    'receiver_email' => $manager->email,
                    'receiver_name' => $manager->getUserFullName(),
                    'variables' => [
                        'project' => $project->only(['ext_id']),
                    ]
                ]),
                $project->institution_id
            );
        }
    }

    private function publishProjectIsReadyForReviewEmailNotification(Project $project): void
    {
        $client = $project->clientInstitutionUser;
        if (filled($client?->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ProjectReadyForReview,
                    'receiver_email' => $client->email,
                    'receiver_name' => $client->getUserFullName(),
                    'variables' => [
                        'project' => $project->only(['ext_id']),
                    ]
                ]),
                $project->institution_id
            );
        }
    }

    private function publishPmOrClientAssignedToProject(Project $project, InstitutionUser $assignee): void
    {
        if (filled($assignee->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::InstitutionUserAssignedToProject,
                    'receiver_email' => $assignee->email,
                    'receiver_name' => $assignee->getUserFullName(),
                    'variables' => [
                        'project' => $project->only(['ext_id'])
                    ]
                ]),
                $project->institution_id
            );
        }
    }

    private function publishProjectCancelledEmailNotification(Project $project, InstitutionUser $receiver): void
    {
        if (filled($receiver->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ProjectCancelled,
                    'receiver_email' => $receiver->email,
                    'receiver_name' => $receiver->getUserFullName(),
                    'variables' => [
                        'project' => $project->only([
                            'ext_id',
                            'cancellation_reason',
                            'cancellation_comment'
                        ]),
                    ]
                ]),
                $project->institution_id
            );
        }
    }

    private function publishProjectCancelledEmailNotificationForVendors(Project $project): void
    {
        $project->assignments->each(function (Assignment $assignment) use ($project) {
            if (filled($receiver = $assignment->assignee?->institutionUser) && filled($receiver->email)) {
                $this->notificationPublisher->publishEmailNotification(
                    EmailNotificationMessage::make([
                        'notification_type' => NotificationType::TaskCancelled,
                        'receiver_email' => $receiver->email,
                        'receiver_name' => $receiver->getUserFullName(),
                        'variables' => [
                            'assignment' => $assignment->only('ext_id'),
                            'job_definition' => $assignment->jobDefinition?->only('job_short_name'),
                        ]
                    ]),
                    $project->institution_id
                );
            }
        });
    }

    private function publishProjectAcceptedEmailNotification(Project $project, InstitutionUser $receiver): void
    {
        if (filled($receiver->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ProjectAccepted,
                    'receiver_email' => $receiver->email,
                    'receiver_name' => $receiver->getUserFullName(),
                    'variables' => [
                        'project' => $project->only([
                            'ext_id'
                        ]),
                    ]
                ]),
                $project->institution_id
            );
        }
    }

    private function publishProjectRegisteredEmailNotification(Project $project): void
    {
        $receiver = $project->clientInstitutionUser;
        if (filled($receiver?->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ProjectRegistered,
                    'receiver_email' => $receiver->email,
                    'receiver_name' => $receiver->getUserFullName(),
                    'variables' => [
                        'project' => $project->only([
                            'ext_id'
                        ]),
                    ]
                ]),
                $project->institution_id
            );
        }
    }
}
