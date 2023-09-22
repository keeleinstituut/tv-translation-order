<?php

namespace App\Services\Prices;

use App\Enums\VolumeUnits;
use App\Models\Assignment;
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


        $prices = $this->assignment->volumes->map(function (Volume $volume) {
            return (new VolumePriceCalculator($volume))
                ->setUnitFee($this->getUnitFee($volume->unit_type))
                ->setDiscount($this->candidate->getDiscount())
                ->getPrice();
        });

        if ($prices->search(null) === false) {
            return $prices->sum();
        }

        return null;
    }

    private function getUnitFee(VolumeUnits $volumeUnit): ?float
    {
        return $this->candidate->getPrice(
            $this->assignment->subProject->source_language_classifier_value_id,
            $this->assignment->subProject->destination_language_classifier_value_id,
        )?->getUnitFee($volumeUnit);
    }
}
