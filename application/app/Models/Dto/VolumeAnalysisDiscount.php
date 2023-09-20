<?php

namespace App\Models\Dto;

class VolumeAnalysisDiscount implements \JsonSerializable
{
    public float $discount_percentage_101;

    public float $discount_percentage_repetitions;

    public float $discount_percentage_100;

    public float $discount_percentage_95_99;

    public float $discount_percentage_85_94;

    public float $discount_percentage_75_84;

    public float $discount_percentage_50_74;

    public float $discount_percentage_0_49;

    public function __construct(array $params)
    {
        $this->discount_percentage_101 = data_get($params, 'discount_percentage_101', 0);
        $this->discount_percentage_repetitions = data_get($params, 'discount_percentage_repetitions', 0);
        $this->discount_percentage_100 = data_get($params, 'discount_percentage_100', 0);
        $this->discount_percentage_95_99 = data_get($params, 'discount_percentage_95_99', 0);
        $this->discount_percentage_85_94 = data_get($params, 'discount_percentage_85_94', 0);
        $this->discount_percentage_75_84 = data_get($params, 'discount_percentage_75_84', 0);
        $this->discount_percentage_50_74 = data_get($params, 'discount_percentage_50_74', 0);
        $this->discount_percentage_0_49 = data_get($params, 'discount_percentage_0_49', 0);
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
