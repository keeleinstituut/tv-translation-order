<?php

namespace App\Observers;

use App\Enums\AssignmentStatus;
use App\Enums\SubProjectStatus;
use App\Jobs\NotifyAssignmentCandidates;
use App\Models\Assignment;
use App\Models\SubProject;

class SubProjectObserver
{
    /**
     * Handle the SubProject "creating" event.
     */
    public function creating(SubProject $subProject): void
    {
        $subProject->ext_id = collect([
            $subProject->project->ext_id,
            $subProject->sourceLanguageClassifierValue->value . $subProject->destinationLanguageClassifierValue->value,
            $subProject->project->subProjectSequence->incrementCurrentValue(),
        ])->implode('-');
    }

    /**
     * Handle the SubProject "created" event.
     */
    public function created(SubProject $subProject): void
    {
        //
    }

    /**
     * Handle the SubProject "updated" event.
     */
    public function updated(SubProject $subProject): void
    {
        if ($subProject->wasChanged('active_job_definition_id')) {
            $subProject->assignments()
                ->where('job_definition_id', $subProject->active_job_definition_id)
                ->whereNull('assigned_vendor_id')
                ->each(fn(Assignment $assignment) => NotifyAssignmentCandidates::dispatch($assignment));

            $prevActiveJobDefinitionId = $subProject->getOriginal('active_job_definition_id');
            if (filled($prevActiveJobDefinitionId)) {
                $subProject->assignments()->where('job_definition_id', $prevActiveJobDefinitionId)
                    ->each(function (Assignment $assignment) {
                        $assignment->status = AssignmentStatus::Done;
                        $assignment->saveOrFail();
                    });
            }

            if (filled($subProject->active_job_definition_id)) {
                $subProject->assignments()->where('job_definition_id', $subProject->active_job_definition_id)
                    ->each(function (Assignment $assignment) {
                        $assignment->status = AssignmentStatus::InProgress;
                        $assignment->saveOrFail();
                    });
            }
        }
    }

    /**
     * Handle the SubProject "deleted" event.
     */
    public function deleted(SubProject $subProject): void
    {
        //
    }

    /**
     * Handle the SubProject "restored" event.
     */
    public function restored(SubProject $subProject): void
    {
        //
    }

    /**
     * Handle the SubProject "force deleted" event.
     */
    public function forceDeleted(SubProject $subProject): void
    {
        //
    }
}
