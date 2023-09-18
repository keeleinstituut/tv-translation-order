<?php

namespace App\Observers;

use App\Models\Assignment;

class AssignmentObserver
{
    /**
     * Handle the Assignment "creating" event.
     * @param Assignment $assignment
     */
    public function creating(Assignment $assignment): void
    {
        $idx = $assignment->subProject->project->typeClassifierValue->projectTypeConfig->getJobsFeatures()
            ->search($assignment->feature);

        if ($idx === false) {
            $idx = 0;
        }

        $assignment->ext_id = collect([
            $assignment->subProject->ext_id, '/',
            ++$idx,
            '.',
            Assignment::where('sub_project_id', $assignment->sub_project_id)
                ->where('feature', $assignment->feature)->count() + 1
        ])->implode('');
    }

    /**
     * Handle the Assignment "created" event.
     */
    public function created(Assignment $assignment): void
    {
        //
    }

    /**
     * Handle the Assignment "updated" event.
     */
    public function updated(Assignment $assignment): void
    {
        //
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
}
