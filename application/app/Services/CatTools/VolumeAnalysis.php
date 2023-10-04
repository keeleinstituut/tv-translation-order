<?php

namespace App\Services\CatTools;

use JsonSerializable;

readonly class VolumeAnalysis implements JsonSerializable
{
    public int $total;

    public int $tm_101;

    public int $repetitions;

    public int $tm_100;

    public int $tm_95_99;

    public int $tm_85_94;

    public int $tm_75_84;

    public int $tm_50_74;

    public int $tm_0_49;

    /**
     * @var string[]
     */
    public array $files_names;

    public function __construct($params)
    {
        $this->tm_101 = data_get($params, 'tm_101', 0);
        $this->repetitions = data_get($params, 'repetitions', 0);
        $this->tm_100 = data_get($params, 'tm_100', 0);
        $this->tm_95_99 = data_get($params, 'tm_95_99', 0);
        $this->tm_85_94 = data_get($params, 'tm_85_94', 0);
        $this->tm_75_84 = data_get($params, 'tm_75_84', 0);
        $this->tm_50_74 = data_get($params, 'tm_50_74', 0);
        $this->tm_0_49 = data_get($params, 'tm_0_49', 0);

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
        $this->files_names = data_get($params, 'files_names', []);
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
            'files_names' => $this->files_names,
        ];
    }
}
