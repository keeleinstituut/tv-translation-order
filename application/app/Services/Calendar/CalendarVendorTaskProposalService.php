<?php

namespace App\Services\Calendar;

use App\Enums\CandidateStatus;
use App\Jobs\AutoDeclineVendorTaskProposal;
use App\Jobs\Workflows\AddCandidatesToWorkflow;
use App\Jobs\Workflows\DeleteCandidatesFromWorkflow;
use App\Models\Assignment;
use App\Models\CalendarSetting;
use App\Models\Candidate;
use App\Models\VendorCalendarEntry;
use Illuminate\Support\Collection;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

readonly class CalendarVendorTaskProposalService
{
    public const int DEFAULT_REACTION_TIME_SECONDS = 1800;

    public function __construct(
        private SlotMatchingService    $slotMatchingService,
        private NotificationPublisher  $notificationPublisher,
    ) {
    }

    /**
     * @param Assignment $assignment
     * @return void
     * @throws Throwable
     */
    public function proposeTaskToVendor(Assignment $assignment): void
    {
        $candidate = $assignment->candidates()
            ->where('status', CandidateStatus::New)
            ->first();

        if ($candidate) {
            $this->proposeTo($candidate);
            return;
        }

        $this->handleNoCandidatesRemaining($assignment);
    }

    /**
     * @throws Throwable
     */
    public function handleDecline(Candidate $candidate): void
    {
        $candidate->status = CandidateStatus::Declined;
        $candidate->saveOrFail();

        if (filled($candidate->vendor?->institution_user_id)) {
            DeleteCandidatesFromWorkflow::dispatch(
                $candidate->assignment,
                [$candidate->vendor->institution_user_id]
            );
        }

        if ($candidate->assignment->subProject->project->is_calendar_project) {
            $this->proposeTaskToVendor($candidate->assignment);
        }
    }

    /**
     * @param Candidate $candidate
     * @return void
     * @throws Throwable
     */
    private function proposeTo(Candidate $candidate): void
    {
        $this->syncVendorCalendarEntry($candidate);

        $candidate->status = CandidateStatus::SubmittedToVendor;
        $candidate->notified_at = now();
        $candidate->saveOrFail();

        $this->sendProposalNotification($candidate);

        if (filled($candidate->vendor?->institution_user_id)) {
            AddCandidatesToWorkflow::dispatch(
                $candidate->assignment,
                [$candidate->vendor->institution_user_id]
            );
        }

        if (!$candidate->vendor?->is_internal) {
            $reactionTime = $this->getReactionTimeSeconds($candidate->assignment);
            AutoDeclineVendorTaskProposal::dispatch($candidate->id)
                ->delay(now()->addSeconds($reactionTime));
        }
    }

    /**
     * @param Assignment $assignment
     * @return void
     * @throws Throwable
     */
    private function handleNoCandidatesRemaining(Assignment $assignment): void
    {
        /** @var Collection<int, Candidate> $declinedCandidates */
        $declinedCandidates = $assignment->candidates()
            ->where('status', CandidateStatus::Declined)
            ->get();

        if ($declinedCandidates->isEmpty()) {
            return;
        }

        $isInternalFlow = $declinedCandidates->last()?->vendor?->is_internal;

        if ($isInternalFlow) {
            $this->tryNextInternalVendor($assignment, $declinedCandidates);
            return;
        }

        $this->notifyTpmCascadeExhausted($assignment);
    }

    /**
     * @param Assignment $assignment
     * @param $declinedCandidates
     * @return void
     * @throws Throwable
     */
    private function tryNextInternalVendor(Assignment $assignment, $declinedCandidates): void
    {
        $excludeVendorIds = $declinedCandidates->pluck('vendor_id');

        $project = $assignment->subProject->project;
        $nextVendor = $this->slotMatchingService->pickBestInternalVendorForProject(
            $project,
            excludeVendorIds: $excludeVendorIds,
        );

        if (!$nextVendor) {
            $this->notifyTpmCascadeExhausted($assignment);
            return;
        }

        $maxPosition = $assignment->candidates()->max('position') ?? -1;

        $candidate = Candidate::create([
            'assignment_id' => $assignment->id,
            'vendor_id' => $nextVendor->id,
            'position' => $maxPosition + 1,
            'status' => CandidateStatus::New,
        ]);

        $this->proposeTo($candidate);
    }

    /**
     * @param Candidate $candidate
     * @return void
     */
    private function syncVendorCalendarEntry(Candidate $candidate): void
    {
        $assignment = $candidate->assignment;
        $entry = VendorCalendarEntry::where('assignment_id', $assignment->id)->first();

        if ($entry && $entry->vendor_id === $candidate->vendor_id) {
            return;
        }

        $entry?->delete();

        $project = $assignment->subProject->project;

        VendorCalendarEntry::create([
            'vendor_id' => $candidate->vendor_id,
            'start_at' => $project->event_start_at,
            'end_at' => $project->event_end_at,
            'assignment_id' => $assignment->id,
        ]);
    }

    /**
     * @throws Throwable
     */
    private function sendProposalNotification(Candidate $candidate): void
    {
        $institutionUser = $candidate->vendor?->institutionUser;
        $assignment = $candidate->assignment;
        $institutionId = $assignment->subProject?->project?->institution_id;

        if (filled($institutionUser?->email) && filled($institutionId)) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::TaskCreated,
                    'receiver_email' => $institutionUser->email,
                    'receiver_name' => $institutionUser->getUserFullName(),
                    'variables' => [
                        'assignment' => $assignment->only(['ext_id']),
                    ]
                ]),
                $institutionId
            );
        }
    }

    private function notifyTpmCascadeExhausted(Assignment $assignment): void
    {
        // TODO: Implement once a new NotificationType is added to the notification service.
        // Should notify the TPM that no vendor accepted the task.
    }

    /**
     * @param Assignment $assignment
     * @return int
     */
    private function getReactionTimeSeconds(Assignment $assignment): int
    {
        $institutionId = $assignment->subProject?->project?->institution_id;

        if (blank($institutionId)) {
            return self::DEFAULT_REACTION_TIME_SECONDS;
        }

        return CalendarSetting::where('institution_id', $institutionId)
            ->first()
            ?->reaction_time_seconds ?? self::DEFAULT_REACTION_TIME_SECONDS;
    }
}
