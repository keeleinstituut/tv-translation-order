<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class NotifyAssignmentCandidatesAboutNewTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(NotificationPublisher $notificationPublisher): void
    {
        if (filled($this->assignment->assigned_vendor_id)) {
            return;
        }

        /** We need to notify candidates only in case if tasks are populated */
        if ($this->assignment->job_definition_id !== $this->assignment->subProject->active_job_definition_id) {
            return;
        }

        if (empty($this->assignment->candidates)) {
            return;
        }

        $this->assignment->candidates->each(function (Candidate $candidate) use ($notificationPublisher) {
            if ($candidate->status === CandidateStatus::New) {
                $candidate->status = CandidateStatus::SubmittedToVendor;
                $candidate->saveOrFail();

                $institutionUser = $candidate->vendor?->institutionUser;
                if (filled($institutionUser?->email) && filled($this->assignment->subProject?->project?->institution_id)) {
                    $notificationPublisher->publishEmailNotification(
                        EmailNotificationMessage::make([
                            'notification_type' => NotificationType::TaskCreated,
                            'receiver_email' => $institutionUser->email,
                            'receiver_name' => $institutionUser->getUserFullName(),
                            'variables' => [
                                'assignment' => $this->assignment->only([
                                    'ext_id'
                                ]),
                            ]
                        ]),
                        $this->assignment->subProject->project->institution_id
                    );
                }
            }
        });
    }
}
