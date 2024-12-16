<?php

namespace App\Observers;

use App\Enums\AssignmentStatus;
use App\Enums\JobKey;
use App\Jobs\NotifyAssignmentCandidatesAboutNewTask;
use App\Models\Assignment;
use App\Models\SubProject;
use AuditLogClient\Services\AuditLogMessageBuilder;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Support\Facades\Auth;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class SubProjectObserver
{
    public function __construct(private readonly NotificationPublisher $notificationPublisher, private readonly AuditLogPublisher $auditLogPublisher)
    {
    }


    /**
     * Handle the SubProject "creating" event.
     */
    public function creating(SubProject $subProject): void
    {
        $subProject->ext_id = collect([
            $subProject->project->ext_id,
            $subProject->sourceLanguageClassifierValue->value . $subProject->destinationLanguageClassifierValue->value,
            $subProject->project->subProjectSequence->incrementCurrentValue(),
        ])->implode('-');
    }

    /**
     * Handle the SubProject "created" event.
     */
    public function created(SubProject $subProject): void
    {
        //
    }

    /**
     * Handle the SubProject "updating" event.
     */
    public function updating(SubProject $subProject): void
    {
        if ($subProject->isDirty('status')) {
            if (Auth::check()) {
                $this->auditLogPublisher->publish(
                    AuditLogMessageBuilder::makeUsingJWT()
                        ->toModifyObjectEventComputingDiff(
                            $subProject->getAuditLogObjectType(),
                            $subProject->getIdentitySubset(),
                            $subProject->fresh()->getAuditLogRepresentation(),
                            $subProject->getAuditLogRepresentation(),
                        )
                );
            }
        }
    }

    /**
     * Handle the SubProject "updated" event.
     * @throws Throwable
     */
    public function updated(SubProject $subProject): void
    {
        if ($subProject->wasChanged('active_job_definition_id')) {
            $prevActiveJobDefinitionId = $subProject->getOriginal('active_job_definition_id');
            if (filled($prevActiveJobDefinitionId)) {
                $subProject->assignments()->where('job_definition_id', $prevActiveJobDefinitionId)
                    ->each(function (Assignment $assignment) {
                        $assignment->status = AssignmentStatus::Done;
                        $assignment->saveOrFail();
                    });
            }

            if (filled($subProject->active_job_definition_id)) {
                $subProject->assignments()->where('job_definition_id', $subProject->active_job_definition_id)
                    ->each(function (Assignment $assignment) {
                        $assignment->status = AssignmentStatus::InProgress;
                        $assignment->saveOrFail();

                        NotifyAssignmentCandidatesAboutNewTask::dispatch($assignment);
                    });

                if ($subProject->activeJobDefinition->job_key === JobKey::JOB_OVERVIEW) {
                    $this->publishSubProjectSentToPmEmailNotification($subProject);
                }
            }
        }

        if ($subProject->wasChanged('deadline_at') && filled($subProject->deadline_at)) {
            $subProject->assignments->each(function (Assignment $assignment) use ($subProject) {
                if (empty($assignment->deadline_at)) {
                    $assignment->deadline_at = $subProject->deadline_at;
                    $assignment->saveOrFail();
                } elseif ($assignment->deadline_at > $subProject->deadline_at) {
                    $assignment->deadline_at = $subProject->deadline_at;
                    $assignment->saveOrFail();
                }
            });
        }
    }

    /**
     * Handle the SubProject "deleted" event.
     */
    public function deleted(SubProject $subProject): void
    {
        //
    }

    /**
     * Handle the SubProject "restored" event.
     */
    public function restored(SubProject $subProject): void
    {
        //
    }

    /**
     * Handle the SubProject "force deleted" event.
     */
    public function forceDeleted(SubProject $subProject): void
    {
        //
    }

    private function publishSubProjectSentToPmEmailNotification(SubProject $subProject): void
    {
        $manager = $subProject->project->managerInstitutionUser;
        if (filled($manager?->email)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::SubProjectSentToPm,
                    'receiver_email' => $manager->email,
                    'receiver_name' => $manager->getUserFullName(),
                    'variables' => [
                        'sub_project' => $subProject->only([
                            'ext_id'
                        ]),
                    ]
                ]),
                $subProject->project->institution_id
            );
        }
    }
}
