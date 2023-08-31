<?php

namespace App\Observers;

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
            $subProject->sourceLanguageClassifierValue->value.$subProject->destinationLanguageClassifierValue->value,
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
