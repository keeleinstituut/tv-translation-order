<?php

namespace App\Services\Workflows\Tasks;

use Illuminate\Support\Collection;

readonly class TasksSearchResult
{
    public function __construct(private Collection $tasks, private int $totalCount)
    {}

    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function getCount(): int
    {
        return $this->totalCount;
    }
}
