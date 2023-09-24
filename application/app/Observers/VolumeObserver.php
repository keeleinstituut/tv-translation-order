<?php

namespace App\Observers;

use App\Models\Volume;

class VolumeObserver
{
    public function creating(Volume $volume): void
    {
        $assignee = $volume->assignment->assignee;
        if (filled($assignee)) {
            if (empty($volume->unit_fee)) {
                $volume->unit_fee = $assignee->getPriceList(
                    $volume->assignment->subProject->source_language_classifier_value_id,
                    $volume->assignment->subProject->destination_language_classifier_value_id,
                )?->getUnitFee($volume->unit_type);
                $volume->save();
            }

            if (filled($volume->cat_tool_job_id) && empty($volume->discounts)) {
                $volume->discounts = $assignee->getDiscount();
            }
        }
    }

    /**
     * Handle the Volume "created" event.
     */
    public function created(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * Handle the Volume "updated" event.
     */
    public function updated(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * Handle the Volume "deleted" event.
     */
    public function deleted(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * Handle the Volume "restored" event.
     */
    public function restored(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    private function updateCachedPrices(Volume $volume): void
    {
        $subProject = $volume->assignment->subProject;
        $subProject->price = $subProject->getPriceCalculator()->getPrice();
        $subProject->save();

        $project = $subProject->project;
        $project->price = $project->getPriceCalculator()->getPrice();
        $project->save();
    }
}