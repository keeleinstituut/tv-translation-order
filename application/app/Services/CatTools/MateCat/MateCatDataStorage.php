<?php

namespace App\Services\CatTools\MateCat;

use App\Models\SubProject;
use DomainException;
use RuntimeException;
use Throwable;

readonly class MateCatDataStorage
{
    public function __construct(private SubProject $subProject)
    {
    }

    /**
     * @param array $meta
     * @return void
     */
    public function storeCreatedProjectMeta(array $meta): void
    {
        $this->subProject->cat_metadata[$this->getProjectCreationKey()] = [
            'id' => $meta['id_project'],
            'password' => $meta['project_pass'],
            'analyze_url' => $meta['analyze_url'],
            'new_keys' => $meta['new_keys']
        ];

        try {
            $this->subProject->saveOrFail();
        } catch (Throwable $e) {
            throw new RuntimeException("Saving of project creation data failed", 0, $e);
        }
    }

    /**
     * @param array $meta
     * @return void
     */
    public function storeAnalyzingResults(array $meta): void
    {
        $this->subProject->cat_metadata[$this->getProjectAnalyzingKey()] = $meta;
        try {
            $this->subProject->saveOrFail();
        } catch (Throwable $e) {
            throw new RuntimeException("Saving of the project analyzing results failed", 0, $e);
        }
    }

    /**
     * @param array $meta
     * @return void

     */
    public function storeProjectUrls(array $meta): void
    {
        $this->subProject->cat_metadata[$this->getProjectUrlsKey()] = $meta;

        try {
            $this->subProject->saveOrFail();
        } catch (Throwable $e) {
            throw new RuntimeException("Saving of the project URLs failed", 0, $e);
        }
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

    public function getAnalyzingResults(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectAnalyzingKey(), []);
    }

    public function getUrls(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectUrlsKey(), []);
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
}
