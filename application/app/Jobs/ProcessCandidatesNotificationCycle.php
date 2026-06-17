<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Exceptions\CalendarSlotConflictException;
use App\Models\Assignment;
use App\Models\InstitutionSetting;
use App\Models\Candidate;
use App\Services\Calendar\CalendarSettingsResolver;
use App\Services\Calendar\TimeSlot;
use App\Services\Calendar\VendorReservationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class ProcessCandidatesNotificationCycle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const int DEFAULT_REACTION_TIME_MINUTES = 30;

    public function __construct(private readonly Assignment $assignment)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(
        NotificationPublisher    $notificationPublisher,
        VendorReservationService $vendorReservation,
        CalendarSettingsResolver $calendarSettings,
    ): void
    {
        if ($this->shouldSkipCycle()) {
            return;
        }

        if ($this->assignment->subProject->project->is_calendar_project) {
            $this->dispatchCalendarCycle($notificationPublisher, $vendorReservation, $calendarSettings);
            return;
        }

        $this->dispatchSimpleCycle($notificationPublisher);
    }

    private function shouldSkipCycle(): bool
    {
        if (filled($this->assignment->assigned_vendor_id)) {
            return true;
        }

        // Tasks for the previous job_definition shouldn't be acted on.
        if ($this->assignment->job_definition_id !== $this->assignment->subProject->active_job_definition_id) {
            return true;
        }

        return empty($this->assignment->candidates);
    }

    /**
     * Notify one calendar candidate per cycle, internals before externals.
     * After a successful notification this job returns; CandidateObserver / AutoDeclineVendorTaskProposal
     * are responsible for re-dispatching the cycle to advance to the next candidate.
     *
     * @throws Throwable
     */
    private function dispatchCalendarCycle(
        NotificationPublisher    $notificationPublisher,
        VendorReservationService $vendorReservation,
        CalendarSettingsResolver $calendarSettings,
    ): void
    {
        if ($this->hasPendingNotifiedVendor()) {
            return;
        }

        $project = $this->assignment->subProject->project;
        $candidates = $this->assignment->candidates()
            ->with(['vendor.institutionUser'])
            ->whereHas('vendor')
            ->where('status', CandidateStatus::New)
            ->ordered()
            ->get();

        if (blank($candidates) && $project->use_external_vendor) {
            $this->notifyNoExternalRemaining($this->assignment, $notificationPublisher);
            return;
        }

        $timeSlot = $calendarSettings->resolveTimeSlotForProject($project);
        [$internals, $externals] = $candidates->partition(fn(Candidate $candidate) => $candidate->vendor->is_internal);

        foreach ($internals as $candidate) {
            if ($this->processCalendarCandidate($candidate, $vendorReservation, $timeSlot, $notificationPublisher)) {
                return;
            }
        }

        foreach ($externals as $candidate) {
            if ($this->processCalendarCandidate($candidate, $vendorReservation, $timeSlot, $notificationPublisher)) {
                return;
            }
        }

        $this->notifyNoExternalRemaining($this->assignment, $notificationPublisher);
    }

    /**
     * @throws Throwable
     */
    private function dispatchSimpleCycle(NotificationPublisher $notificationPublisher): void
    {
        $this->assignment->candidates->each(function (Candidate $candidate) use ($notificationPublisher) {
            if ($candidate->status === CandidateStatus::New && blank($candidate->notified_at)) {
                $this->notifyAssignmentCandidate($candidate, $notificationPublisher);
            }
        });
    }

    /**
     * Try to reserve the calendar slot and notify a single candidate.
     *
     * @return bool true when the cycle should stop (candidate notified or already pending);
     *              false when the candidate was skipped due to a slot conflict and the loop should try the next one.
     * @throws Throwable
     */
    private function processCalendarCandidate(
        Candidate                $candidate,
        VendorReservationService $vendorReservation,
        TimeSlot                 $timeSlot,
        NotificationPublisher    $notificationPublisher,
    ): bool
    {
        try {
            $vendorReservation->rotateToVendor(
                $this->assignment,
                $candidate->vendor_id,
                $timeSlot->bufferedStartAt,
                $timeSlot->bufferedEndAt,
            );
        } catch (CalendarSlotConflictException) {
            $candidate->status = CandidateStatus::Rejected;
            $candidate->saveOrFail();
            return false;
        }

        if (filled($candidate->notified_at)) {
            return true;
        }

        $this->notifyAssignmentCandidate($candidate, $notificationPublisher);

        if (!$candidate->vendor->is_internal) {
            AutoDeclineVendorTaskProposal::dispatch($candidate->id)
                ->afterCommit()
                ->delay(now()->addMinutes($this->getReactionTimeMinutes()));
        }

        return true;
    }

    /**
     * @throws Throwable
     */
    private function notifyAssignmentCandidate(Candidate $candidate, NotificationPublisher $notificationPublisher): void
    {
        $candidate->status = CandidateStatus::SubmittedToVendor;
        $candidate->notified_at = Carbon::now()->utc();
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

    private function getReactionTimeMinutes(): int
    {
        $institutionId = $this->assignment->subProject?->project?->institution_id;

        if (blank($institutionId)) {
            return self::DEFAULT_REACTION_TIME_MINUTES;
        }

        return InstitutionSetting::where('institution_id', $institutionId)
            ->first()
            ?->reaction_time_minutes ?? self::DEFAULT_REACTION_TIME_MINUTES;
    }

    /**
     * @throws Throwable
     */
    private function notifyNoExternalRemaining(Assignment $assignment, NotificationPublisher $notificationPublisher): void
    {
        $manager = $assignment->subProject?->project?->managerInstitutionUser;
        $institution = $assignment->subProject?->project?->institution;

        $receiverEmail = $manager?->email ?: $institution?->email;
        $receiverName = $manager?->getUserFullName() ?: $institution?->name;

        if (filled($receiverEmail) && filled($receiverName)) {
            $notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::NoExternalVendorsAvailable,
                    'receiver_email' => $receiverEmail,
                    'receiver_name' => $receiverName,
                    'variables' => [
                        'assignment' => $assignment->only(['ext_id']),
                        'job_definition' => $assignment->jobDefinition?->only(['job_short_name'])
                    ]
                ]),
                $institution->id
            );
        }
    }

    private function hasPendingNotifiedVendor(): bool
    {
        return $this->assignment->candidates()
            ->whereHas('vendor')
            ->where('status', CandidateStatus::SubmittedToVendor)
            ->whereNotNull('notified_at')
            ->exists();
    }
}
