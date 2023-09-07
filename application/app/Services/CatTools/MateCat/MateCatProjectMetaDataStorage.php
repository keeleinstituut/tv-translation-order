<?php

namespace App\Services\CatTools\MateCat;

use App\Models\CatToolJob;
use App\Models\SubProject;
use App\Services\CatTools\CatAnalysisResult;
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
                $analyzingResults->put(
                    $this->composeJobExternalId($jobId, $jobPassword),
                    $this->mapAnalysisResult($data)
                );
            }
        }

        try {
            DB::transaction(function () use ($analyzingResults) {
                $catToolJobs = $this->subProject->catToolJobs
                    ->keyBy('ext_id');

                foreach ($analyzingResults as $externalId => $analyzingResult) {
                    if ($catToolJobs->has($externalId)) {
                        $job = $catToolJobs->get($externalId);
                        $job->volume_analysis = $analyzingResult;
                        $job->saveOrFail();
                    }
                }
            }, self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {

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

    public function getXLIFFsDownloadUrl()
    {
        return data_get($this->getProjectUrls(), 'xliff_download_url') ?:
            throw new DomainException("Accessing of Project XLIFF download URL for not created project");
    }


    public function storeProjectInfo(array $meta): void
    {
        $this->store($this->getProjectInfoKey(), $meta);
        try {
            DB::transaction(function () {
                $jobsIds = collect();
                $catToolJobs = $this->subProject->catToolJobs
                    ->keyBy('ext_id');

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
                        'revise_url' => data_get($jobData, 'urls.revise_urls.0.url'),
                        'progress_percentage' => data_get($jobData, 'stats.PROGRESS_PERC'),
                        'metadata' => $jobData,
                    ])->saveOrFail();

                    $jobsIds->add($job->id);
                }

                $jobsIdsToDelete = $catToolJobs->pluck('id')->diff($jobsIds);
                CatToolJob::whereIn('id', $jobsIdsToDelete)->delete();

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
                    ->keyBy('ext_id');

                foreach ($this->getJobs() as $jobData) {
                    $jobIdentifier = $this->composeJobExternalId($jobData['id'], $jobData['password']);
                    if ($catToolJobs->has($jobIdentifier)) {
                        $job = $catToolJobs->get($jobIdentifier);
                        $job->progress_percentage = data_get($jobData, 'stats.PROGRESS_PERC');
                        $job->metadata = $jobData;
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

    public function wasSplit(): bool
    {
        return count($this->getJobs()) > 1;
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

    private function getJobs(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectInfoKey() . '.project.jobs') ?:
            throw new DomainException("Accessing of Jobs for not created project");
    }

    private function getProjectCreationKey(): string
    {
        return 'project-create-response';
    }

    private function getProjectAnalyzingKey(): string
    {
        return 'project-analyzing-results-response';
    }

    private function getProjectUrlsKey(): string
    {
        return 'project-urls-response';
    }

    private function getProjectInfoKey(): string
    {
        return 'project-info-response';
    }

    private function getProjectSplitKey(): string
    {
        return 'project-split-response';
    }

    private function getProjectMergeKey(): string
    {
        return 'project-merge-response';
    }

    private function composeJobExternalId(int $id, string $password): string
    {
        return "$id-$password";
    }

    private function mapAnalysisResult(array $analysisResult): CatAnalysisResult
    {
        return new CatAnalysisResult([
            'total' => data_get($analysisResult, 'TOTAL_PAYABLE.0', 0),
            'tm_101' => data_get($analysisResult, 'ICE.0', 0),
            'repetitions' => data_get($analysisResult, 'REPETITIONS.0', 0),
            'tm_100' => data_get($analysisResult, 'TM_100_PUBLIC.0', 0) + data_get($analysisResult, 'TM_100.0', 0),
            'tm_95_99' => data_get($analysisResult, 'TM_95_99.0', 0),
            'tm_85_94' => data_get($analysisResult, 'TM_85_94.0', 0),
            'tm_75_84' => data_get($analysisResult, 'TM_75_84.0', 0) + data_get($analysisResult, 'INTERNAL_MATCHES.0', 0),
            'tm_50_74' => data_get($analysisResult, 'TM_50_74.0', 0),
            'tm_0_49' => data_get($analysisResult, 'NEW.0', 0) + data_get($analysisResult, 'MT.0', 0),
        ]);
    }
}
