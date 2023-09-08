<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\SubProject;

class AssignmentObserver
{
    /**
     * Handle the SubProject "creating" event.
     * @param Assignment $assignment
     */
    public function creating(Assignment $assignment): void
    {
        $assignment->ext_id = collect([
            $assignment->subProject->ext_id,
            // task (feature) number (in project type’s workflow)
            // subtask number (one subtask for each vendor in current task)
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
        //
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
