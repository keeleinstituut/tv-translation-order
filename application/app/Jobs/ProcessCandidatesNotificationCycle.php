<?php

namespace App\Jobs;

use App\Enums\CandidateStatus;
use App\Exceptions\CalendarSlotConflictException;
use App\Models\Assignment;
use App\Models\CalendarSetting;
use App\Models\Candidate;
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

    public const int DEFAULT_REACTION_TIME_SECONDS = 1800; // 30min

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly Assignment $assignment)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(NotificationPublisher $notificationPublisher, VendorReservationService $vendorReservation): void
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

        $project = $this->assignment->subProject->project;
        if ($project->is_calendar_project) {

            if ($this->hasPendingNotifiedVendor()) {
                /**
                 * If there is a vendor who got notified, we will wait until acceptance or declining
                 * In case if the vendor is external, the system will reject the pending proposal automatically after the delay
                 * @see AutoDeclineVendorTaskProposal
                 */
                return;
            }


            /** @var Collection<Candidate> $candidates */
            $candidates = $this->assignment->candidates()
                ->whereHas('vendor')
                ->where('status', CandidateStatus::New)
                ->ordered();

            if (blank($candidates)) {
                $this->handleNoCandidatesRemaining($this->assignment, $notificationPublisher);
                return;
            }

            [$internals, $externals] = $candidates->partition(fn(Candidate $candidate) => $candidate->vendor?->is_internal);
            /** @var Candidate $candidate */
            foreach ($internals as $candidate) {
                try {
                    $vendorReservation->rotate(
                        $this->assignment,
                        $candidate->vendor_id,
                        $project->event_start_at,
                        $project->event_end_at,
                    );
                } catch (CalendarSlotConflictException) {
                    $candidate->status = CandidateStatus::Declined;
                    $candidate->saveOrFail();
                    continue;
                }

                if (blank($candidate->notified_at)) {
                    $this->notifyAssignmentCandidate($candidate, $notificationPublisher);
                }
                return;
            }

            foreach ($externals as $candidate) {
                try {
                    $vendorReservation->rotate(
                        $this->assignment,
                        $candidate->vendor_id,
                        $project->event_start_at,
                        $project->event_end_at,
                    );
                } catch (CalendarSlotConflictException) {
                    $candidate->status = CandidateStatus::Declined;
                    $candidate->saveOrFail();
                    continue;
                }

                if (blank($candidate->notified_at)) {
                    $this->notifyAssignmentCandidate($candidate, $notificationPublisher);
                    AutoDeclineVendorTaskProposal::dispatch($candidate->id)
                        ->afterCommit()
                        ->delay(now()->addSeconds($this->getReactionTimeSeconds()));
                }
                return;
            }

            $this->handleNoCandidatesRemaining($this->assignment, $notificationPublisher);
        }

        $this->assignment->candidates->each(function (Candidate $candidate) use ($notificationPublisher) {
            if ($candidate->status === CandidateStatus::New && blank($candidate->notified_at)) {
                $this->notifyAssignmentCandidate($candidate, $notificationPublisher);
            }
        });
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

    /**
     * @return int
     */
    private function getReactionTimeSeconds(): int
    {
        $institutionId = $this->assignment->subProject?->project?->institution_id;

        if (blank($institutionId)) {
            return self::DEFAULT_REACTION_TIME_SECONDS;
        }

        return CalendarSetting::where('institution_id', $institutionId)
            ->first()
            ?->reaction_time_seconds ?? self::DEFAULT_REACTION_TIME_SECONDS;
    }

    /**
     * @param Assignment $assignment
     * @return void
     * @throws Throwable
     */
    private function handleNoCandidatesRemaining(Assignment $assignment, NotificationPublisher $notificationPublisher): void
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
