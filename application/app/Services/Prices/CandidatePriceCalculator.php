<?php

namespace App\Services\Prices;

use App\Models\Assignment;
use App\Models\Price;
use App\Models\Vendor;
use App\Models\Volume;

class CandidatePriceCalculator extends BaseAssignmentPriceCalculator
{
    public function __construct(Assignment $assignment, private readonly Vendor $candidate)
    {
        parent::__construct($assignment);
    }

    public function getPrice(): ?float
    {
        if ($this->hasNoVolume()) {
            return null;
        }

        $priceList = $this->getPriceList();
        $discount = $this->candidate->getVolumeAnalysisDiscount();
        $prices = $this->getVolumes()->map(function (Volume $volume) use ($priceList, $discount) {
            return $volume->getPriceCalculator()
                ->setUnitFee($priceList?->getUnitFee($volume->unit_type))
                ->setDiscount($discount)
                ->getPrice();
        });

        if ($prices->isEmpty()) {
            return null;
        }

        if ($prices->search(null, true) === false) {
            return max($prices->sum(), $priceList?->minimal_fee ?: 0);
        }

        return null;
    }

    private function getPriceList(): ?Price
    {
        return $this->candidate->getPriceList(
            $this->assignment->subProject->source_language_classifier_value_id,
            $this->assignment->subProject->destination_language_classifier_value_id,
            $this->assignment->jobDefinition?->skill_id
        );
    }
}
