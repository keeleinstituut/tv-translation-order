<?php

namespace App\Observers;

use App\Enums\CandidateStatus;
use App\Jobs\Workflows\AddCandidatesToWorkflow;
use App\Jobs\Workflows\DeleteCandidatesFromWorkflow;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Vendor;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class CandidateObserver
{
    public function __construct(private readonly NotificationPublisher $notificationPublisher)
    {
    }

    /**
     * Handle the Candidate "created" event.
     */
    public function created(Candidate $candidate): void
    {
        /** @var Vendor $vendor */
        $vendor = $candidate->vendor()->withTrashed()->first();
        if (filled($candidate->assignment) && filled($vendor?->institution_user_id)) {
            AddCandidatesToWorkflow::dispatch(
                $candidate->assignment,
                [$vendor->institution_user_id]
            );
        }
    }

    /**
     * Handle the Candidate "updated" event.
     * @throws Throwable
     */
    public function updated(Candidate $candidate): void
    {
        if ($candidate->wasChanged('status') && $candidate->status === CandidateStatus::Declined) {
            $this->deleteCandidateFromWorkflow($candidate);
            $this->publishTaskDeclinedEmailNotification($candidate->assignment, $candidate->vendor);
        }
    }

    /**
     * Handle the Candidate "deleted" event.
     */
    public function deleted(Candidate $candidate): void
    {
        $this->deleteCandidateFromWorkflow($candidate);
    }

    private function deleteCandidateFromWorkflow(Candidate $candidate): void
    {
        /** @var Vendor $vendor */
        $vendor = $candidate->vendor()->withTrashed()->first();
        if (filled($candidate->assignment) && filled($vendor?->institution_user_id)) {
            DeleteCandidatesFromWorkflow::dispatch(
                $candidate->assignment,
                [$vendor->institution_user_id]
            )->afterCommit();
        }
    }

    /**
     * @throws Throwable
     */
    private function publishTaskDeclinedEmailNotification(Assignment $assignment, Vendor $vendor): void
    {
        $project = $assignment->subProject?->project;
        if (filled($project)) {
            $manager = $project->managerInstitutionUser;
            $institution = $project->institution;
            $receiverEmail = $manager?->email ?: $institution->email;
            $receiverName = $manager?->getUserFullName() ?: $institution->name;

            if (filled($receiverEmail)) {
                $this->notificationPublisher->publishEmailNotification(
                    EmailNotificationMessage::make([
                        'notification_type' => NotificationType::TaskDeclinedByVendor,
                        'receiver_email' => $receiverEmail,
                        'receiver_name' => $receiverName,
                        'variables' => [
                            'assignment' => $assignment->only('ext_id'),
                            'job_definition' => $assignment->jobDefinition?->only('job_short_name'),
                            'user' => ['name' => $vendor->institutionUser?->getUserFullName()],
                        ]
                    ]),
                    $project->institution_id
                );
            }
        }
    }

    /**
     * Handle the Candidate "restored" event.
     */
    public function restored(Candidate $candidate): void
    {
        //
    }

    /**
     * Handle the Candidate "force deleted" event.
     */
    public function forceDeleted(Candidate $candidate): void
    {
        //
    }
}
