<?php

namespace App\Services\CatTools\Contracts;

interface SplittableCatToolJobs extends CatToolService
{
    /**
     * @param int $jobsCount
     * @return void
     */
    public function split(int $jobsCount): void;

    /**
     * @return void
     */
    public function merge(): void;
}
