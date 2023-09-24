<?php

namespace App\Services\Prices;

use App\Models\Dto\VolumeAnalysisDiscount;
use App\Models\Volume;

class VolumePriceCalculator implements PriceCalculator
{
    private ?VolumeAnalysisDiscount $discount = null;

    private ?float $unitFee = null;

    public function __construct(readonly private Volume $volume)
    {
    }

    public function getPrice(): ?float
    {
        if (empty($this->getUnitFee())) {
            return null;
        }

        if (filled($this->volume->getVolumeAnalysis())) {
            return $this->getDiscountedPrice();
        }

        return $this->volume->unit_quantity * $this->getUnitFee();
    }

    public function setDiscount(?VolumeAnalysisDiscount $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function setUnitFee(?float $unitFee): static
    {
        $this->unitFee = $unitFee;

        return $this;
    }

    private function getDiscountedPrice(): float
    {
        $volumeAnalysis = $this->volume->getVolumeAnalysis();
        $discount = $this->getDiscount();

        return array_sum(
            array_map(fn ($item) => $item * $this->getUnitFee(), [
                $volumeAnalysis->tm_101 * (100 - $discount->discount_percentage_101) / 100,
                $volumeAnalysis->tm_100 * (100 - $discount->discount_percentage_100) / 100,
                $volumeAnalysis->tm_95_99 * (100 - $discount->discount_percentage_95_99) / 100,
                $volumeAnalysis->tm_85_94 * (100 - $discount->discount_percentage_85_94) / 100,
                $volumeAnalysis->tm_75_84 * (100 - $discount->discount_percentage_75_84) / 100,
                $volumeAnalysis->tm_50_74 * (100 - $discount->discount_percentage_50_74) / 100,
                $volumeAnalysis->tm_0_49 * (100 - $discount->discount_percentage_0_49) / 100,
            ])
        );
    }

    private function getDiscount(): VolumeAnalysisDiscount
    {
        return $this->discount ?: $this->volume->getDiscount();
    }

    private function getUnitFee(): ?float
    {
        return $this->unitFee ?: $this->volume->unit_fee;
    }
}
