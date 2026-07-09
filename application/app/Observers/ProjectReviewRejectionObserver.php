<?php

namespace App\Observers;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectReviewRejection;
use App\Models\SubProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

class ProjectReviewRejectionObserver
{
    public function __construct(private readonly NotificationPublisher $notificationPublisher)
    {
    }

    public function creating(ProjectReviewRejection $projectReviewRejection): void
    {
        $projectReviewRejection->id = Str::orderedUuid();
        $projectReviewRejection->file_collection = join('/', [
            Project::REVIEW_FILES_COLLECTION_PREFIX,
            $projectReviewRejection->id
        ]);
    }

    /**
     * Handle the ProjectReviewRejection "created" event.
     * @throws \Throwable
     */
    public function created(ProjectReviewRejection $projectReviewRejection): void
    {
        if (filled($client = $projectReviewRejection->project?->clientInstitutionUser)) {
            $this->publishProjectRejectedEmailNotification($projectReviewRejection, $client);
        }

        $this->publishProjectRejectedEmailNotification(
            $projectReviewRejection,
            $projectReviewRejection->project?->managerInstitutionUser,
            true
        );
    }

    /**
     * Handle the ProjectReviewRejection "updated" event.
     */
    public function updated(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "deleted" event.
     */
    public function deleted(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "restored" event.
     */
    public function restored(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * Handle the ProjectReviewRejection "force deleted" event.
     */
    public function forceDeleted(ProjectReviewRejection $projectReviewRejection): void
    {
        //
    }

    /**
     * @throws \Throwable
     */
    private function publishProjectRejectedEmailNotification(ProjectReviewRejection $projectReviewRejection, ?InstitutionUser $receiver, bool $isManager = false): void
    {
        if (empty($project = $projectReviewRejection->project)) {
            return;
        }

        $receiverEmail = $receiver?->email;
        $receiverName = $receiver?->getUserFullName();

        if ($isManager && empty($receiverEmail)) {
            $receiverEmail = $receiver?->email ?: $project->institution?->email;
            $receiverName = $receiver?->getUserFullName() ?: $project->institution?->name;
        }

        if (filled($receiverEmail)) {
            DB::afterCommit(function () use ($project, $projectReviewRejection, $receiverEmail, $receiverName) {
                $this->notificationPublisher->publishEmailNotification(
                    EmailNotificationMessage::make([
                        'notification_type' => NotificationType::ProjectRejected,
                        'receiver_email' => $receiverEmail,
                        'receiver_name' => $receiverName,
                        'variables' => [
                            'project' => $project->only(['ext_id']),
                            'project_review_rejection' => [
                                ...$projectReviewRejection->only(['description']),
                                'sub_projects' => $projectReviewRejection->getSubProjects()
                                    ->each(fn(SubProject $subProject) => $subProject->only(['ext_id']))
                            ]
                        ]
                    ]),
                    $project->institution_id
                );
            });
        }
    }
}
