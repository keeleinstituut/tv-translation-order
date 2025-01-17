<?php

namespace App\Observers;

use App\Models\CachedEntities\Institution;
use App\Models\Sequence;

class InstitutionObserver
{
    /**
     * Handle the Institution "created" event.
     */
    public function created(Institution $institution): void
    {
        //
    }

    /**
     * Handle the Institution "updated" event.
     */
    public function updated(Institution $institution): void
    {
        //
    }

    /**
     * Handle the Institution "deleted" event.
     */
    public function deleted(Institution $institution): void
    {
        //
    }

    /**
     * Handle the Institution "restored" event.
     */
    public function restored(Institution $institution): void
    {
        //
    }

    /**
     * Handle the Institution "force deleted" event.
     */
    public function forceDeleted(Institution $institution): void
    {
        //
    }

    /**
     * Handle the Institution "saved" event.
     */
    public function saved(Institution $institution): void
    {
        if (! $institution->institutionProjectSequence) {
            $seq = new Sequence();
            $seq->sequenceable_id = $institution->id;
            $seq->sequenceable_type = Institution::class;
            $seq->name = Sequence::INSTITUTION_PROJECT_SEQ;
            $seq->save();
        }
    }
}
