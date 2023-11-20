<?php

namespace App\Observers;

use App\Enums\AssignmentStatus;
use App\Jobs\NotifyAssignmentCandidates;
use App\Models\Assignment;
use App\Models\SubProject;
use Throwable;

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
     * @throws Throwable
     */
    public function updated(SubProject $subProject): void
    {
        if ($subProject->wasChanged('active_job_definition_id')) {
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

                        NotifyAssignmentCandidates::dispatch($assignment);
                    });
            }
        }

        if ($subProject->wasChanged('deadline_at') && filled($subProject->deadline_at)) {
            $subProject->assignments->each(function (Assignment $assignment) use ($subProject) {
                if (empty($assignment->deadline_at)) {
                    $assignment->deadline_at = $subProject->deadline_at;
                    $assignment->saveOrFail();
                } elseif ($assignment->deadline_at > $subProject->deadline_at) {
                    $assignment->deadline_at = $subProject->deadline_at;
                    $assignment->saveOrFail();
                }
            });
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
