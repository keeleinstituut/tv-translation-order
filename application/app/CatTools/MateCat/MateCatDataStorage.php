<?php

namespace App\CatTools\MateCat;

use Cache;
use DomainException;

readonly class MateCatDataStorage
{
    public function __construct(private string $subOrderId)
    {
    }

    public function storeCreatedProjectMeta(array $meta): void
    {
        Cache::set($this->getProjectCreationKey(), [
            'id' => $meta['id_project'],
            'password' => $meta['project_pass'],
            'analyze_url' => $meta['analyze_url'],
            'new_keys' => $meta['new_keys']
        ]);
    }

    public function storeAnalyzingResults(array $meta): void
    {
        Cache::set($this->getProjectAnalyzingKey(), $meta);
    }

    public function storeProjectUrls(array $meta): void
    {
        Cache::set($this->getProjectUrlsKey(), $meta);
    }

    public function getProjectId(): int
    {
        return Cache::get($this->getProjectCreationKey(), [])['id'] ??
            throw new DomainException("Accessing of ProjectId for not created project");
    }

    public function getProjectPassword(): string
    {
        return Cache::get($this->getProjectCreationKey(), [])['password'] ??
            throw new DomainException("Accessing of ProjectPassword for not created project");
    }

    public function getAnalyzingMeta(): array
    {
        return Cache::get($this->getProjectAnalyzingKey(), []);
    }

    public function getUrls(): array
    {
        return Cache::get($this->getProjectUrlsKey(), []);
    }

    private function getProjectCreationKey(): string
    {
        return $this->subOrderId.'-created';
    }

    private function getProjectAnalyzingKey(): string
    {
        return $this->subOrderId.'-analyzed';
    }

    private function getProjectUrlsKey(): string
    {
        return $this->subOrderId.'-urls';
    }
}
