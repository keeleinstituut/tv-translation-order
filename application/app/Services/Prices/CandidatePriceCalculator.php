<?php

namespace App\Services\Prices;

use App\Models\Assignment;
use App\Models\Price;
use App\Models\Vendor;
use App\Models\Volume;

readonly class CandidatePriceCalculator implements PriceCalculator
{
    public function __construct(private Assignment $assignment, private Vendor $candidate)
    {
    }

    public function getPrice(): ?float
    {
        if (empty($this->assignment->volumes)) {
            return null;
        }

        $priceList = $this->getPriceList();
        $prices = $this->assignment->volumes->map(function (Volume $volume) use ($priceList) {
            return $volume->getPriceCalculator()
                ->setUnitFee($priceList?->getUnitFee($volume->unit_type))
                ->setDiscount($this->candidate->getDiscount())
                ->getPrice();
        });

        if ($prices->search(null) === false) {
            return max($prices->sum(), $priceList?->minimal_fee ?: 0);
        }

        return null;
    }

    private function getPriceList(): ?Price
    {
        return $this->candidate->getPriceList(
            $this->assignment->subProject->source_language_classifier_value_id,
            $this->assignment->subProject->destination_language_classifier_value_id,
        );
    }
}
