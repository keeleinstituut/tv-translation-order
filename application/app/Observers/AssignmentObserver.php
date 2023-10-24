<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\Volume;

class AssignmentObserver
{
    /**
     * Handle the Assignment "creating" event.
     */
    public function creating(Assignment $assignment): void
    {
        $idx = $assignment->jobDefinition?->sequence ?: 0;

        $assignment->ext_id = collect([
            $assignment->subProject->ext_id, '/', ++$idx,
            '.',
            Assignment::where('sub_project_id', $assignment->sub_project_id)
                ->where('job_definition_id', $assignment->job_definition_id)
                ->count() + 1,
        ])->implode('');
    }

    /**
     * Handle the Assignment "created" event.
     */
    public function created(Assignment $assignment): void
    {
        $this->updateVolumesAssigneeFields($assignment);
    }

    /**
     * Handle the Assignment "updated" event.
     */
    public function updated(Assignment $assignment): void
    {
        $this->updateVolumesAssigneeFields($assignment);
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

    private function updateVolumesAssigneeFields(Assignment $assignment): void
    {
        if (filled($assignment->assigned_vendor_id) && $assignment->wasChanged('assigned_vendor_id')) {
            if (empty($assignment->volumes)) {
                return;
            }

            Volume::withoutEvents(fn() => $assignment->volumes->map(function (Volume $volume) use ($assignment) {
                $volume->discounts = $assignment->assignee->getVolumeAnalysisDiscount();
                $volume->unit_fee = $assignment->assignee->getPriceList(
                    $assignment->subProject->source_language_classifier_value_id,
                    $assignment->subProject->destination_language_classifier_value_id,
                    $assignment->jobDefinition?->skill_id
                )?->getUnitFee($volume->unit_type);
                $volume->save();
            }));

            $subProject = $assignment->subProject;
            if (empty($subProject)) {
                return;
            }

            $subProject->price = $subProject->getPriceCalculator()->getPrice();
            $subProject->save();

            $project = $subProject->project;

            if (empty($project)) {
                return;
            }

            $project->price = $project->getPriceCalculator()->getPrice();
            $project->save();
        }
    }
}
