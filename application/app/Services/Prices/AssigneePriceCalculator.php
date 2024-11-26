<?php

namespace App\Services\Prices;

use App\Enums\JobKey;
use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Models\Price;
use App\Models\Volume;

class AssigneePriceCalculator extends BaseAssignmentPriceCalculator
{
    public function getPrice(): ?float
    {
        /** Overview tasks will not be payable and will be done by translation/project manager */
        if ($this->assignment->jobDefinition?->job_key === JobKey::JOB_OVERVIEW) {
            return 0;
        }

        if ($this->hasNoVolume()) {
            return null;
        }

        $prices = $this->assignment->volumes->map(function (Volume $volume) {
            return $volume->getPriceCalculator()->getPrice();
        });

        if ($prices->isEmpty()) {
            return null;
        }

        if ($prices->search(null, true) === false) {
            $priceList = $this->getPriceList();
            return max($prices->sum(), $priceList?->minimal_fee ?: 0);
        }

        return null;
    }

    private function getPriceList(): ?Price
    {
        return $this->assignment->assignee?->getPriceList(
            $this->assignment->subProject->source_language_classifier_value_id,
            $this->assignment->subProject->destination_language_classifier_value_id,
            $this->assignment->jobDefinition?->skill_id
        );
    }
}
