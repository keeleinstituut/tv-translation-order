<?php

namespace App\Models\Dto;

use JsonSerializable;

class VolumeAnalysisDiscount implements JsonSerializable
{
    public ?float $discount_percentage_101;

    public ?float $discount_percentage_repetitions;

    public ?float $discount_percentage_100;

    public ?float $discount_percentage_95_99;

    public ?float $discount_percentage_85_94;

    public ?float $discount_percentage_75_84;

    public ?float $discount_percentage_50_74;

    public ?float $discount_percentage_0_49;

    public function __construct(array $params)
    {
        $this->discount_percentage_101 = $this->getValueOrNoDiscount($params, 'discount_percentage_101');
        $this->discount_percentage_repetitions = $this->getValueOrNoDiscount($params, 'discount_percentage_repetitions');
        $this->discount_percentage_100 = $this->getValueOrNoDiscount($params, 'discount_percentage_100');
        $this->discount_percentage_95_99 = $this->getValueOrNoDiscount($params, 'discount_percentage_95_99');
        $this->discount_percentage_85_94 = $this->getValueOrNoDiscount($params, 'discount_percentage_85_94');
        $this->discount_percentage_75_84 = $this->getValueOrNoDiscount($params, 'discount_percentage_75_84');
        $this->discount_percentage_50_74 = $this->getValueOrNoDiscount($params, 'discount_percentage_50_74');
        $this->discount_percentage_0_49 = $this->getValueOrNoDiscount($params, 'discount_percentage_0_49');
    }

    /**
     * The business requirement was changed, so previously it was discount,
     * but now we're considering it as a percent from the full price that should be paid.
     *
     * @param array $params
     * @param string $key
     * @return array|mixed
     */
    private function getValueOrNoDiscount(array $params, string $key)
    {
        return data_get($params, $key, 100);
    }

    public function jsonSerialize(): array
    {
        return [
            'discount_percentage_101' => $this->discount_percentage_101,
            'discount_percentage_repetitions' => $this->discount_percentage_repetitions,
            'discount_percentage_100' => $this->discount_percentage_100,
            'discount_percentage_95_99' => $this->discount_percentage_95_99,
            'discount_percentage_85_94' => $this->discount_percentage_85_94,
            'discount_percentage_75_84' => $this->discount_percentage_75_84,
            'discount_percentage_50_74' => $this->discount_percentage_50_74,
            'discount_percentage_0_49' => $this->discount_percentage_0_49,
        ];
    }
}
