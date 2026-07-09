<?php

namespace App\Services\Prices;

use App\Enums\JobKey;
use App\Models\Assignment;
use App\Models\InstitutionPartner;
use App\Models\Volume;

class OutsourceOfferPriceCalculator extends BaseAssignmentPriceCalculator
{
    public function __construct(Assignment $assignment, private readonly InstitutionPartner $partner)
    {
        parent::__construct($assignment);
    }

    public function getPrice(): ?float
    {
        if ($this->assignment->jobDefinition?->job_key === JobKey::JOB_OVERVIEW) {
            return 0;
        }

        if ($this->hasNoVolume()) {
            return null;
        }

        $srcLangId = $this->assignment->subProject->source_language_classifier_value_id;
        $dstLangId = $this->assignment->subProject->destination_language_classifier_value_id;
        $skillId = $this->assignment->jobDefinition?->skill_id;
        $discount = $this->partner->getVolumeAnalysisDiscount();

        $prices = $this->getVolumes()->map(fn(Volume $volume) => $volume->getPriceCalculator()
            ->setUnitFee($this->partner->resolveFee($srcLangId, $dstLangId, $skillId, $volume->unit_type))
            ->setDiscount($discount)
            ->getPrice()
        );

        if ($prices->isEmpty()) {
            return null;
        }

        if ($prices->search(null, true) === false) {
            return max($prices->sum(), $this->partner->resolveMinimalFee($srcLangId, $dstLangId, $skillId) ?: 0);
        }

        return null;
    }
}
