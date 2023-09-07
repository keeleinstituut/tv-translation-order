<?php

namespace App\Services\CatTools\MateCat;

use App\Models\CatToolJob;
use App\Models\SubProject;
use DB;
use DomainException;
use RuntimeException;
use Throwable;

readonly class MateCatProjectMetaDataStorage
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
        $jobsAnalyzingResults = data_get($meta, 'data.jobs');
        $analyzingResults = collect();
        foreach ($jobsAnalyzingResults as $jobId => $jobAnalyzingData) {
            foreach (data_get($jobAnalyzingData, 'totals', []) as $jobPassword => $data) {
                $analyzingResults->put($this->composeJobExternalId($jobId, $jobPassword), $data);
            }
        }

        $this->store($this->getProjectAnalyzingKey(), $analyzingResults->toArray());
    }

    public function storeProjectUrls(array $meta): void
    {
        $this->store($this->getProjectUrlsKey(), $meta);
    }

    public function getProjectUrls(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectUrlsKey() . '.urls') ?:
            throw new DomainException("Accessing of ProjectId for not created project");
    }

    public function getTranslationsDownloadUrl()
    {
        return data_get($this->getProjectUrls(), 'translation_download_url') ?:
            throw new DomainException("Accessing of Project translations download URL for not created project");
    }

    public function getXLIFFDownloadUrl()
    {
        return data_get($this->getProjectUrls(), 'xliff_download_url') ?:
            throw new DomainException("Accessing of Project XLIFF download URL for not created project");
    }


    public function storeProjectInfo(array $meta): void
    {
        $this->store($this->getProjectInfoKey(), $meta);
        try {
            DB::transaction(function () {
                $catToolJobs = $this->subProject->catToolJobs
                    ->keyBy(fn(CatToolJob $job) => $this->composeJobExternalId(
                        $job->meta['id'],
                        $job->meta['password']
                    ));

                $jobsData = $this->getJobs();
                foreach ($jobsData as $idx => $jobData) {
                    $jobIdentifier = $this->composeJobExternalId(
                        $jobData['id'],
                        $jobData['password']
                    );

                    $job = $catToolJobs->has($jobIdentifier) ?
                        $catToolJobs->get($jobIdentifier) : new CatToolJob();

                    $job->fill([
                        'sub_project_id' => $this->subProject->id,
                        'ext_id' => $jobIdentifier,
                        'name' => join('-', [$this->subProject->ext_id, $idx + 1]),
                        'translate_url' => data_get($jobData, 'urls.translate_url'),
                        'progress_percentage' => data_get($jobData, 'stats.PROGRESS_PERC'),
                        'volume_analysis' => $this->getAnalyzingResults()[$jobIdentifier] ?? [],
                        'meta' => $jobData,
                    ])->saveOrFail();
                }
            }, self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new RuntimeException("Saving of the jobs data failed", previous: $e);
        }
    }

    public function storeProjectProgress(array $meta): void
    {
        $this->store($this->getProjectInfoKey(), $meta);

        try {
            DB::transaction(function () {
                $catToolJobs = $this->subProject->catToolJobs
                    ->keyBy(fn(CatToolJob $job) => $job->ext_id);

                foreach ($this->getJobs() as $jobData) {
                    $jobIdentifier = $this->composeJobExternalId($jobData['id'], $jobData['password']);
                    if ($catToolJobs->has($jobIdentifier)) {
                        $job = $catToolJobs->get($jobIdentifier);
                        $job->progress_percentage = data_get($jobData, 'stats.PROGRESS_PERC');
                        $job->meta = $jobData;
                        $job->saveOrFail();
                    }
                }
            }, self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new RuntimeException("Storing of the jobs data failed", previous: $e);
        }
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
            DB::transaction(fn() => $this->subProject->saveOrFail(), self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new RuntimeException("Saving of the project URLs failed", previous: $e);
        }
    }

    private function getProjectCreationKey(): string
    {
        return $this->subProject->id . '-created';
    }

    private function getProjectAnalyzingKey(): string
    {
        return $this->subProject->id . '-analyzed';
    }

    private function getProjectUrlsKey(): string
    {
        return $this->subProject->id . '-urls';
    }

    private function getProjectInfoKey(): string
    {
        return $this->subProject->id . '-info';
    }

    private function getProjectSplitKey(): string
    {
        return $this->subProject->id . '-split';
    }

    private function getProjectMergeKey(): string
    {
        return $this->subProject->id . '-merge';
    }

    private function composeJobExternalId(int $id, string $password): string
    {
        return "$id-$password";
    }
}
