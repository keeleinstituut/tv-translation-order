<?php

namespace App\Services\Prices;

use App\Models\Assignment;
use App\Models\Price;
use App\Models\Vendor;
use App\Models\Volume;

class AssignmentPriceCalculator implements PriceCalculator
{
    private ?Vendor $assignee;

    public function __construct(private readonly Assignment $assignment)
    {
    }

    public function getPrice(): ?float
    {
        if (empty($this->assignment->volumes)) {
            return null;
        }

        $discount = null;
        $price = null;
        if (filled($assignee = $this->getAssignee())) {
            $discount = $assignee->getDiscount();
            /** @var Price|null $price */
            // TODO: add filtering based on the skill
            $price = $assignee->prices()->where(
                'src_lang_classifier_value_id',
                $this->assignment->subProject->source_language_classifier_value_id
            )->where(
                'dst_lang_classifier_value_id',
                $this->assignment->subProject->destination_language_classifier_value_id
            )->first();
        }

        $prices = $this->assignment->volumes->map(function (Volume $volume) use ($discount, $price) {
            return (new VolumePriceCalculator($volume))
                ->setDiscount($discount)
                ->setUnitFee($price?->getUnitFee($volume->unit_fee))
                ->getPrice();
        });


        return $prices->sum();
    }

    public function setAssignee(Vendor $assignee): static
    {
        $this->assignee = $assignee;
        return $this;
    }

    private function getAssignee(): ?Vendor
    {
        return $this->assignee ?: $this->assignment->assignee;
    }
}
