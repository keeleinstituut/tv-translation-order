<?php

namespace App\Observers;

use App\Enums\CandidateStatus;
use App\Jobs\Workflows\UpdateAssignmentDeadlineInsideWorkflow;
use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Candidate;
use App\Models\Volume;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class AssignmentObserver
{
    public function __construct(private readonly NotificationPublisher $notificationPublisher)
    {

    }

    /**
     * Handle the Assignment "creating" event.
     */
    public function creating(Assignment $assignment): void
    {
        $this->setExternalId($assignment);
    }

    /**
     * Handle the Assignment "created" event.
     * @throws Throwable
     */
    public function created(Assignment $assignment): void
    {
        $this->updateVolumesAssigneeFields($assignment);
    }

    /**
     * Handle the Assignment "updated" event.
     * @throws Throwable
     */
    public function updated(Assignment $assignment): void
    {
        $this->updateVolumesAssigneeFields($assignment);
        if ($assignment->wasChanged('deadline_at')) {
            UpdateAssignmentDeadlineInsideWorkflow::dispatch($assignment);
        }

        if (filled($assignment->assigned_vendor_id) && $assignment->wasChanged('assigned_vendor_id')) {
            if (filled($manager = $assignment->subProject?->project?->managerInstitutionUser)) {
                $this->publishTaskAcceptedEmailNotification($assignment, $manager);
            }

            if (filled($vendorInstitutionUser = $assignment->assignee?->institutionUser)) {
                $this->publishTaskAcceptedEmailNotification($assignment, $vendorInstitutionUser);
            }
        }
    }

    public function deleting(Assignment $assignment): void
    {
    }

    /**
     * Handle the Assignment "deleted" event.
     * @throws Throwable
     */
    public function deleted(Assignment $assignment): void
    {
        $assignments = $assignment->getSameJobDefinitionAssignmentsQuery()
            ->orderBy('created_at')
            ->get();

        $assignments->each(function (Assignment $assignment, int $idx) {
            $this->setExternalId($assignment, $idx);
            $assignment->saveOrFail();
        });
    }

    /**
     * Handle the Assignment "restored" event.
     */
    public function restored(Assignment $assignment): void
    {
        //
    }

    /**
     * Handle the Assignment "force deleted" event.
     */
    public function forceDeleted(Assignment $assignment): void
    {
        //
    }

    /**
     * @throws Throwable
     */
    private function updateVolumesAssigneeFields(Assignment $assignment): void
    {
        if (filled($assignment->assigned_vendor_id) && $assignment->wasChanged('assigned_vendor_id')) {
            /** @var Candidate $candidate */
            $candidate = $assignment->candidates()
                ->where('vendor_id', $assignment->assigned_vendor_id)
                ->first();

            if (filled($candidate)) {
                $candidate->status = CandidateStatus::Accepted;
                $candidate->saveOrFail();
            }

            if (filled($assignment->volumes)) {
                Volume::withoutEvents(fn() => $assignment->volumes->map(function (Volume $volume) use ($assignment) {
                    $volume->discounts = $assignment->assignee->getVolumeAnalysisDiscount();
                    $volume->unit_fee = $assignment->assignee->getPriceList(
                        $assignment->subProject->source_language_classifier_value_id,
                        $assignment->subProject->destination_language_classifier_value_id,
                        $assignment->jobDefinition?->skill_id
                    )?->getUnitFee($volume->unit_type);
                    $volume->save();
                }));

                // https://github.com/laravel/framework/issues/27138
                $assignment->price = $assignment->getPriceCalculator()->getPrice();
                $assignment->saveQuietly();

                if (filled($subProject = $assignment->subProject)) {
                    $subProject->price = $subProject->getPriceCalculator()->getPrice();
                    $subProject->saveOrFail();
                }

                if (filled($project = $subProject?->project)) {
                    $project->price = $project->getPriceCalculator()->getPrice();
                    $project->saveOrFail();
                }
            }
        }
    }

    private function setExternalId(Assignment $assignment, int $sequence = null): void
    {
        $idx = $assignment->jobDefinition?->sequence ?: 0;
        $sequence = is_null($sequence) ? $assignment->getSameJobDefinitionAssignmentsQuery()
            ->count() : $sequence;
        $assignment->ext_id = collect([$assignment->subProject->ext_id, '/', ++$idx, '.', ++$sequence])
            ->implode('');
    }

    private function publishTaskAcceptedEmailNotification(Assignment $assignment, InstitutionUser $receiver): void
    {
        if (filled($receiver->email)) {
            $this->notificationPublisher->publishEmailNotification(EmailNotificationMessage::make([
                'notification_type' => NotificationType::TaskAccepted,
                'receiver_email' => $receiver->email,
                'receiver_name' => $receiver->getUserFullName(),
                'variables' => [
                    'assignment' => $assignment->only('ext_id'),
                    'job_definition' => $assignment->jobDefinition?->only('job_short_name'),
                    'vendor' => $assignment->assignee?->only(['company_name']),
                    'user' => ['name' => $assignment->assignee?->institutionUser?->getUserFullName()],
                ]
            ]));
        }
    }
}
