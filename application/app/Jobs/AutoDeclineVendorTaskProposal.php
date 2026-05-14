<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class AutoDeclineVendorTaskProposal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $candidateId)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(NotificationPublisher $notificationPublisher): void
    {
        DB::transaction(function () use ($notificationPublisher) {
            $candidate = Candidate::lockForUpdate()->find($this->candidateId);

            if (!$candidate || $candidate->status !== CandidateStatus::SubmittedToVendor) {
                return;
            }

            if (filled($candidate->assignment?->assigned_vendor_id) && $candidate->assignment->assigned_vendor_id !== $candidate->vendor_id) {
                return;
            }

            $candidate->status = CandidateStatus::Rejected;
            $candidate->saveOrFail();

            if (filled($candidate->assignment) && filled($candidate->vendor)) {
                $this->publishReactionTimeExpiredEmailNotification(
                    $notificationPublisher,
                    $candidate->assignment,
                    $candidate->vendor
                );
            }

            if ($candidate->assignment->subProject->project->is_calendar_project) {
                $calendarEntry = VendorCalendarEntry::where('assignment_id', $candidate->assignment_id)
                    ->where('vendor_id', $candidate->vendor_id)
                    ->first();

                if (filled($calendarEntry)) {
                    $calendarEntry->delete();
                }

                ProcessCandidatesNotificationCycle::dispatch($candidate->assignment)
                    ->afterCommit();
            }
        });
    }

    private function publishReactionTimeExpiredEmailNotification(
        NotificationPublisher $notificationPublisher,
        Assignment $assignment,
        Vendor $vendor
    ): void {
        $project = $assignment->subProject?->project;
        if (blank($project)) {
            return;
        }

        $manager = $project->managerInstitutionUser;
        $institution = $project->institution;
        $receiverEmail = $manager?->email ?: $institution->email;
        $receiverName = $manager?->getUserFullName() ?: $institution->name;

        if (blank($receiverEmail)) {
            return;
        }

        DB::afterCommit(function () use ($notificationPublisher, $assignment, $vendor, $project, $receiverEmail, $receiverName) {
            $notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::ReactionTimeExpired,
                    'receiver_email' => $receiverEmail,
                    'receiver_name' => $receiverName,
                    'variables' => [
                        'assignment' => $assignment->only('ext_id'),
                        'job_definition' => $assignment->jobDefinition?->only('job_short_name'),
                        'vendor' => ['name' => $vendor->institutionUser?->getUserFullName()],
                    ],
                ]),
                $project->institution_id
            );
        });
    }
}
