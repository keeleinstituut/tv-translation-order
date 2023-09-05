<?php

namespace App\Services\CatTools\Contracts;

interface CatToolService
{
    public function createProject(array $sourceFilesIds): void;

    public function getJobs(): array;
}
