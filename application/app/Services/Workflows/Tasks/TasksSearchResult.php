<?php

namespace App\Services\Workflows\Tasks;

use App\Models\JobDefinition;
use DomainException;
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

    public function getActiveJobDefinition(): ?JobDefinition
    {
        if ($this->getCount() === 0) {
            return null;
        }

        $assignments = $this->getTasks()->pluck('assignment')->filter();
        if (empty($assignments)) {
            return null;
        }

        $jobDefinitionsIds = $assignments->pluck('job_definition_id')->unique();
        if ($jobDefinitionsIds->count() > 1) {
            throw new DomainException('Current state of the workflow contains multiple job definitions');
        }

        return JobDefinition::query()->find($jobDefinitionsIds->get(0));
    }
}
