<?php

namespace App\Services\CatTools\MateCat;

use App\Models\SubProject;
use DB;
use DomainException;
use RuntimeException;
use Throwable;

readonly class MateCatDataStorage
{
    const RETRY_ATTEMPTS = 5;

    public function __construct(private SubProject $subProject)
    {
    }

    public function storeCreatedProjectMeta(array $meta): void
    {
        $this->store($this->getProjectCreationKey(), [
            'id' => $meta['id_project'],
            'password' => $meta['project_pass'],
            'analyze_url' => $meta['analyze_url'],
            'new_keys' => $meta['new_keys']
        ]);
    }

    public function storeAnalyzingResults(array $meta): void
    {
        $this->store($this->getProjectAnalyzingKey(), $meta);
    }

    public function storeProjectUrls(array $meta): void
    {
        $this->store($this->getProjectUrlsKey(), $meta);
    }

    public function storeProjectInfo(array $meta): void
    {
        $this->store($this->getProjectInfoKey(), $meta);
    }

    public function storeSplittingResult(array $meta): void
    {
        $this->store($this->getProjectSplitKey(), $meta);
    }

    public function storeMergingResult(array $meta): void
    {
        $this->store($this->getProjectMergeKey(), $meta);
    }

    public function getProjectId(): int
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationKey() . '.id') ?:
            throw new DomainException("Accessing of ProjectId for not created project");
    }

    public function getProjectPassword(): string
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationKey() . '.password') ?:
            throw new DomainException("Accessing of ProjectId for not created project");
    }

    public function getJobs(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectInfoKey() . '.project.jobs') ?:
            throw new DomainException("Accessing of Jobs for not created project");
    }

    public function wasSplit(): bool
    {
        return isset($this->subProject->cat_metadata[$this->getProjectSplitKey()]);
    }

    public function getAnalyzingResults(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectAnalyzingKey(), []);
    }

    private function store(string $key, $value): void
    {
        $this->subProject->cat_metadata[$key] = $value;
        try {
            DB::transaction(fn () => $this->subProject->saveOrFail(), self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new RuntimeException("Saving of the project URLs failed", 0, $e);
        }
    }

    private function getProjectCreationKey(): string
    {
        return $this->subProject->id.'-created';
    }

    private function getProjectAnalyzingKey(): string
    {
        return $this->subProject->id.'-analyzed';
    }

    private function getProjectUrlsKey(): string
    {
        return $this->subProject->id.'-urls';
    }

    private function getProjectInfoKey(): string
    {
        return $this->subProject->id.'-info';
    }

    private function getProjectSplitKey(): string
    {
        return $this->subProject->id.'-split';
    }

    private function getProjectMergeKey(): string
    {
        return $this->subProject->id.'-merge';
    }
}
