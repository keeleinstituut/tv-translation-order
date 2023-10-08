<?php

namespace App\Observers;

use App\Models\Assignment;
use App\Models\CatToolJob;
use App\Models\Volume;

class CatToolJobObserver
{
    /**
     * Handle the CatToolJob "created" event.
     */
    public function created(CatToolJob $catToolJob): void
    {
    }

    /**
     * Handle the CatToolJob "updated" event.
     */
    public function updated(CatToolJob $catToolJob): void
    {
        if ($catToolJob->wasChanged('volume_analysis')) {
            if (empty($catToolJob->assignments)) {
                return;
            }

            $catToolJob->assignments->map(function (Assignment $assignment) {
                Volume::withoutEvents(fn () => $assignment->volumes?->map(function (Volume $volume) {
                    $volume->custom_volume_analysis = null;
                    $volume->save();
                }));
            });

            $subProject = $catToolJob->subProject;
            $subProject->price = $subProject->getPriceCalculator()->getPrice();
            $subProject->save();

            $project = $subProject->project;
            $project->price = $project->getPriceCalculator()->getPrice();
            $project->save();
        }
    }

    /**
     * Handle the CatToolJob "deleted" event.
     */
    public function deleted(CatToolJob $catToolJob): void
    {
        //
    }

    /**
     * Handle the CatToolJob "restored" event.
     */
    public function restored(CatToolJob $catToolJob): void
    {
        //
    }

    /**
     * Handle the CatToolJob "force deleted" event.
     */
    public function forceDeleted(CatToolJob $catToolJob): void
    {
        //
    }
}