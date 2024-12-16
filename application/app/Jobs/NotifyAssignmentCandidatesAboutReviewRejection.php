<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\JobDefinition;
use App\Models\SubProject;
use App\Services\Workflows\Tasks\TasksSearchResult;
use DomainException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

class NotifyAssignmentCandidatesAboutReviewRejection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly SubProject $subProject)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationPublisher $notificationPublisher): void
    {
        $tasksSearchResult = $this->subProject->workflow()->getTasksSearchResult();
        if (empty($activeJobDefinition = $tasksSearchResult->getActiveJobDefinition())) {
            return;
        }

        $this->subProject->assignments()->where('job_definition_id', $activeJobDefinition->id)
            ->each(function (Assignment $assignment) use ($notificationPublisher) {
                if (filled($assignment->assignee) && filled($receiver = $assignment->assignee?->institutionUser) && filled($receiver->email)) {
                    $notificationPublisher->publishEmailNotification(
                        EmailNotificationMessage::make([
                            'notification_type' => NotificationType::TaskRejected,
                            'receiver_email' => $receiver->email,
                            'receiver_name' => $receiver->getUserFullName(),
                            'variables' => [
                                'assignment' => $assignment->only(['ext_id']),
                            ]
                        ]),
                        $this->subProject->project->institution_id
                    );
                }
            });
    }
}
