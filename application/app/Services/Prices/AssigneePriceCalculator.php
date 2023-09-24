<?php

namespace App\Services\Prices;

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
        );
    }
}
