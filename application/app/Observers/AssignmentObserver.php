<?php

namespace App\Observers;

use App\Enums\CandidateStatus;
use App\Enums\SubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Volume;
use Throwable;

class AssignmentObserver
{
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
    }

    /**
     * @throws Throwable
     */
    public function deleting(Assignment $assignment): void
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
     * Handle the Assignment "deleted" event.
     */
    public function deleted(Assignment $assignment): void
    {
        //
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
            }

            /** @var Candidate $candidate */
            $candidate = $assignment->candidates()
                ->where('vendor_id', $assignment->assigned_vendor_id)
                ->first();

            if (filled($candidate)) {
                $candidate->status = CandidateStatus::Accepted;
                $candidate->saveOrFail();
            }

            $subProject = $assignment->subProject;
            if (empty($subProject)) {
                return;
            }

            $subProject->price = $subProject->getPriceCalculator()->getPrice();
            $subProject->saveOrFail();

            $project = $subProject->project;

            if (empty($project)) {
                return;
            }

            $project->price = $project->getPriceCalculator()->getPrice();
            $project->saveOrFail();
        }
    }

    private function setExternalId(Assignment $assignment, int $sequence = null): void
    {
        $idx = $assignment->jobDefinition?->sequence ?: 0;
        $sequence = $sequence ?: $assignment->getSameJobDefinitionAssignmentsQuery()
                ->count() + 1;
        $assignment->ext_id = collect([
            $assignment->subProject->ext_id, '/', ++$idx,
            '.',
            $sequence,
        ])->implode('');
    }
}
