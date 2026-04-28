<?php

namespace App\Observers;

use App\Enums\CandidateStatus;
use App\Enums\SubProjectStatus;
use App\Jobs\ProcessCandidatesNotificationCycle;
use App\Jobs\Workflows\AddCandidatesToWorkflow;
use App\Jobs\Workflows\DeleteCandidatesFromWorkflow;
use App\Jobs\Workflows\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
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
        if (in_array($candidate->assignment->subProject->status, [SubProjectStatus::New, SubProjectStatus::Registered])) {
            TrackSubProjectStatus::dispatchSync($candidate->assignment->subProject);
            $candidate->assignment->subProject->refresh();
        }

        /** @var Vendor $vendor */
        $vendor = $candidate->vendor()->withTrashed()->first();
        if (filled($candidate->assignment) && filled($vendor?->institution_user_id)) {
            AddCandidatesToWorkflow::dispatch(
                $candidate->assignment,
                [$vendor->institution_user_id]
            );
        }

        if ($candidate->assignment->subProject->status === SubProjectStatus::TasksSubmittedToVendors) {
            ProcessCandidatesNotificationCycle::dispatchAfterResponse($candidate->assignment);
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
            if (filled($candidate->notified_at)) {
                $this->publishTaskDeclinedEmailNotification($candidate->assignment, $candidate->vendor);
            }
        }
    }

    /**
     * Handle the Candidate "deleted" event.
     * @throws Throwable
     */
    public function deleted(Candidate $candidate): void
    {
        $this->deleteCandidateFromWorkflow($candidate);

        if ($candidate->assignment->subProject->status === SubProjectStatus::TasksSubmittedToVendors) {
            ProcessCandidatesNotificationCycle::dispatch($candidate->assignment);
        }
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
                DB::afterCommit(function () use ($assignment, $vendor, $project, $receiverEmail, $receiverName) {
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
                });
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
