<?php

namespace App\Models\Dto;

use JsonSerializable;

class VolumeAnalysis implements JsonSerializable
{
    public float $total;

    public float $tm_101;

    public float $repetitions;

    public float $tm_100;

    public float $tm_95_99;

    public float $tm_85_94;

    public float $tm_75_84;

    public float $tm_50_74;

    public float $tm_0_49;

    public float $raw_word_count;

    public array $files_names;

    public function __construct(array $params)
    {
        $this->tm_101 = data_get($params, 'tm_101', 0);
        $this->repetitions = data_get($params, 'repetitions', 0);
        $this->tm_100 = data_get($params, 'tm_100', 0);
        $this->tm_95_99 = data_get($params, 'tm_95_99', 0);
        $this->tm_85_94 = data_get($params, 'tm_85_94', 0);
        $this->tm_75_84 = data_get($params, 'tm_75_84', 0);
        $this->tm_50_74 = data_get($params, 'tm_50_74', 0);
        $this->tm_0_49 = data_get($params, 'tm_0_49', 0);
        $this->raw_word_count = data_get($params, 'raw_word_count', 0);
        $this->files_names = data_get($params, 'files_names', []);

        $this->total = array_sum([
            $this->tm_101,
            $this->repetitions,
            $this->tm_100,
            $this->tm_95_99,
            $this->tm_85_94,
            $this->tm_75_84,
            $this->tm_50_74,
            $this->tm_0_49,
        ]);
    }

    public function jsonSerialize(): array
    {
        return [
            'total' => $this->total,
            'tm_101' => $this->tm_101,
            'repetitions' => $this->repetitions,
            'tm_100' => $this->tm_100,
            'tm_95_99' => $this->tm_95_99,
            'tm_85_94' => $this->tm_85_94,
            'tm_75_84' => $this->tm_75_84,
            'tm_50_74' => $this->tm_50_74,
            'tm_0_49' => $this->tm_0_49,
            'raw_word_count' => $this->raw_word_count,
            'files_names' => $this->files_names,
        ];
    }
}
