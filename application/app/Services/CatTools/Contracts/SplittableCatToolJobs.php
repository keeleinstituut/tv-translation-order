<?php

namespace App\Services\CatTools\Contracts;

interface SplittableCatToolJobs extends CatToolService
{
    public function split(int $jobsCount): void;
    public function merge(): void;
}
