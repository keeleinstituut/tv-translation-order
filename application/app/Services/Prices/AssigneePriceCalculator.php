<?php

namespace App\Services\Prices;

use App\Enums\JobKey;
use App\Models\Assignment;
use App\Models\Price;
use App\Models\Volume;

readonly class AssigneePriceCalculator implements PriceCalculator
{
    public function __construct(private Assignment $assignment)
    {
    }

    public function getPrice(): ?float
    {
        /** Overview tasks will not be payable and will be done by translation/project manager */
        if ($this->assignment->jobDefinition?->job_key === JobKey::JOB_OVERVIEW) {
            return 0;
        }

        if (empty($this->assignment->volumes)) {
            return null;
        }

        $prices = $this->assignment->volumes->map(function (Volume $volume) {
            return $volume->getPriceCalculator()->getPrice();
        });

        if ($prices->search(null) === false) {
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
