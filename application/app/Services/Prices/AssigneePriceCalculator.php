<?php

namespace App\Services\Prices;

use App\Enums\JobKey;
use App\Enums\OutsourceRequestStatus;
use App\Models\Price;
use App\Models\Volume;

class AssigneePriceCalculator extends BaseAssignmentPriceCalculator
{
    private ?Price $priceList = null;

    private bool $priceListResolved = false;

    public function getPrice(): ?float
    {
        return $this->compute(fn (Volume $volume) => $volume->getPriceCalculator()->getPrice());
    }

    public function getPriceWithoutDiscount(): ?float
    {
        return $this->compute(fn (Volume $volume) => $volume->getPriceCalculator()->getPriceWithoutDiscount());
    }

    public function getDiscountAmount(): ?float
    {
        $price = $this->getPrice();
        $priceWithoutDiscount = $this->getPriceWithoutDiscount();

        if ($price === null || $priceWithoutDiscount === null) {
            return null;
        }

        return round($priceWithoutDiscount - $price, 2);
    }

    /**
     * @param callable(Volume): ?float $volumePrice
     */
    private function compute(callable $volumePrice): ?float
    {
        /** Overview tasks will not be payable and will be done by translation/project manager */
        if ($this->assignment->jobDefinition?->job_key === JobKey::JOB_OVERVIEW) {
            return 0;
        }

        // If the assignment is outsourced, use the price from the outsourced request
        if ($this->assignment->currentOutsourceRequest?->status === OutsourceRequestStatus::Fulfilled && filled($this->assignment->currentOutsourceRequest?->price)) {
            return $this->assignment->currentOutsourceRequest->price;
        }

        if ($this->hasNoVolume()) {
            return null;
        }

        $prices = $this->assignment->volumes->map($volumePrice);

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
        if (!$this->priceListResolved) {
            $this->priceListResolved = true;
            $this->priceList = $this->assignment->assignee?->getPriceList(
                $this->assignment->subProject->source_language_classifier_value_id,
                $this->assignment->subProject->destination_language_classifier_value_id,
                $this->assignment->jobDefinition?->skill_id
            );
        }

        return $this->priceList;
    }
}
