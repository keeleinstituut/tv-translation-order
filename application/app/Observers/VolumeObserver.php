<?php

namespace App\Observers;

use App\Models\Volume;
use Throwable;

class VolumeObserver
{
    public function creating(Volume $volume): void
    {
        if (filled($assignee = $volume->assignment->assignee)) {
            if (empty($volume->unit_fee)) {
                $volume->unit_fee = $assignee->getPriceList(
                    $volume->assignment->subProject->source_language_classifier_value_id,
                    $volume->assignment->subProject->destination_language_classifier_value_id,
                    $volume->assignment->jobDefinition?->skill_id
                )?->getUnitFee($volume->unit_type);
                $volume->save();
            }

            if (filled($volume->cat_tool_job_id) && empty($volume->discounts)) {
                $volume->discounts = $assignee->getVolumeAnalysisDiscount();
            }
        }
    }

    /**
     * Handle the Volume "created" event.
     * @throws Throwable
     */
    public function created(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * Handle the Volume "updated" event.
     * @throws Throwable
     */
    public function updated(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * Handle the Volume "deleted" event.
     * @throws Throwable
     */
    public function deleted(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * Handle the Volume "restored" event.
     * @throws Throwable
     */
    public function restored(Volume $volume): void
    {
        $this->updateCachedPrices($volume);
    }

    /**
     * @throws Throwable
     */
    private function updateCachedPrices(Volume $volume): void
    {
        if (filled($assignment = $volume->assignment)) {
            $assignment->price = $assignment->getPriceCalculator()->getPrice();
            $assignment->saveOrFail();

            if (filled($subProject = $assignment->subProject)) {
                $subProject->price = $subProject->getPriceCalculator()->getPrice();
                $subProject->saveOrFail();
            }

            if (filled($project = $subProject?->project)) {
                $project->price = $project->getPriceCalculator()->getPrice();
                $project->saveOrFail();
            }
        }
    }
}
