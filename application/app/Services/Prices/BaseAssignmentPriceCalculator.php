<?php

namespace App\Services\Prices;

use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Models\Volume;
use Illuminate\Support\Collection;

abstract class BaseAssignmentPriceCalculator implements PriceCalculator
{
    public function __construct(protected Assignment $assignment)
    {
    }

    /**
     * @return Collection<Volume>
     */
    protected function getVolumesWithoutMinFeeUnit(): Collection
    {
        return $this->assignment->volumes->filter(
            fn(Volume $volume) => $volume->unit_type !== VolumeUnits::MinimalFee
        );
    }

    /**
     * @return Collection<Volume>
     */
    protected function getVolumes(): Collection
    {
        return $this->assignment->volumes;
    }

    protected function hasNoVolume(): bool
    {
        return ! filled($this->getVolumes());
    }

    /**
     * @return bool
     * @see https://github.com/keeleinstituut/tv-tolkevarav/issues/684#issuecomment-2133389962
     */
    protected function hasOnlyMinFeeUnitVolumes(): bool
    {
        return $this->assignment->volumes->filter(
                fn(Volume $volume) => $volume->unit_type === VolumeUnits::MinimalFee
            )->count() === $this->assignment->volumes->count();
    }
}
