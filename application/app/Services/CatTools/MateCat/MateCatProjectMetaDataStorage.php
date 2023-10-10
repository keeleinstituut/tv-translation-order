<?php

namespace App\Services\CatTools\MateCat;

use App\Enums\VolumeUnits;
use App\Models\CatToolJob;
use App\Models\SubProject;
use App\Services\CatTools\Exceptions\StorageException;
use App\Services\CatTools\VolumeAnalysis;
use DB;
use DomainException;
use Throwable;

readonly class MateCatProjectMetaDataStorage
{
    const RETRY_ATTEMPTS = 5;

    public function __construct(private SubProject $subProject)
    {
    }

    public function isEmpty(): bool
    {
        return count($this->subProject->cat_metadata) === 1 && isset($this->subProject->cat_metadata[$this->getProjectMTEnabledFlagKey()]);
    }

    public function clean(): void
    {
        $this->subProject->cat_metadata = [
            $this->getProjectMTEnabledFlagKey() => $this->hasMTEnabled(),
        ];

        $this->subProject->save();
    }

    public function storeCreatedProjectMeta(array $meta): void
    {
        $this->store($this->getProjectCreationKey(), [
            'id' => $meta['id_project'],
            'password' => $meta['project_pass'],
            'analyze_url' => $meta['analyze_url'],
            'new_keys' => $meta['new_keys'],
        ]);
    }

    public function storeProjectCreationStatus(array $meta): void
    {
        $this->store($this->getProjectCreationStatusKey(), $meta);
    }

    public function storeProjectFiles(array $filesIds): void
    {
        $this->store($this->getProjectFilesKey(), $filesIds);
    }

    public function storeIsMTEnabled(bool $isEnabled): void
    {
        $this->store($this->getProjectMTEnabledFlagKey(), $isEnabled);
    }

    public function storeAnalyzingResults(array $meta): void
    {
        $jobsAnalyzingResults = data_get($meta, 'data.jobs');
        $analyzingResults = collect();
        foreach ($jobsAnalyzingResults as $jobId => $jobAnalyzingData) {
            $files = [];
            foreach (data_get($jobAnalyzingData, 'chunks', []) as $jobPassword => $fileData) {
                $files[$this->composeJobExternalId($jobId, $jobPassword)] = data_get($fileData, '*.FILENAME');
            }

            foreach (data_get($jobAnalyzingData, 'totals', []) as $jobPassword => $data) {
                $externalId = $this->composeJobExternalId($jobId, $jobPassword);
                $analyzingResults->put(
                    $this->composeJobExternalId($jobId, $jobPassword),
                    $this->normalizeAnalysisResult($data, $files[$externalId] ?? [])
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
                        $job->volume_unit_type = VolumeUnits::Words;
                        $job->saveOrFail();
                    }
                }
            }, self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new StorageException('Saving of the project analysis results failed', previous: $e);
        }

        $this->store($this->getProjectAnalyzingKey(), [
            'status' => $meta['status'],
            'data' => $analyzingResults->toArray(),
        ]);
    }

    public function storeProjectUrls(array $meta): void
    {
        $this->store($this->getProjectUrlsKey(), $meta);
    }

    public function getProjectUrls(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectUrlsKey().'.urls') ?:
            throw new DomainException('Accessing of ProjectId for not created project');
    }

    public function getAnalyzingStatus(): string
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectAnalyzingKey().'.status', '');
    }

    public function getCreationStatus(): string
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationStatusKey().'.status', '');
    }

    public function getCreationStatusResponse(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationStatusKey(), []);
    }

    public function getCreationError(): string
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationStatusKey().'.errors.0.message', '');
    }

    public function getProjectSourceFilesIds(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectFilesKey(), []);
    }

    public function getTranslationsDownloadUrl()
    {
        return data_get($this->getProjectUrls(), 'jobs.0.translation_download_url') ?:
            throw new DomainException('Accessing of Project translations download URL for not created project');
    }

    public function getXLIFFsDownloadUrl()
    {
        return data_get($this->getProjectUrls(), 'jobs.0.xliff_download_url') ?:
            throw new DomainException('Accessing of Project XLIFF download URL for not created project');
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
                        'name' => implode('-', [$this->subProject->ext_id, $idx + 1]),
                        'translate_url' => data_get($jobData, 'urls.translate_url'),
                        'progress_percentage' => data_get($jobData, 'stats.PROGRESS_PERC'),
                        'metadata' => $jobData,
                    ])->saveOrFail();

                    $jobsIds->add($job->id);
                }

                $jobsIdsToDelete = $catToolJobs->pluck('id')->diff($jobsIds);
                CatToolJob::whereIn('id', $jobsIdsToDelete)->delete();
            }, self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new StorageException('Saving of the jobs data failed', previous: $e);
        }

        $this->subProject->load('catToolJobs');
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
            throw new StorageException('Storing of the jobs data failed', previous: $e);
        }

        $this->subProject->load('catToolJobs');
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
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationKey().'.id') ?:
            throw new DomainException('Accessing of ProjectId for not created project');
    }

    public function getProjectPassword(): string
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectCreationKey().'.password') ?:
            throw new DomainException('Accessing of ProjectId for not created project');
    }

    public function hasMTEnabled(): bool
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectMTEnabledFlagKey(), true);
    }

    public function wasSplit(): bool
    {
        return count($this->getJobs()) > 1;
    }

    private function store(string $key, $value): void
    {
        $this->subProject->cat_metadata[$key] = $value;
        try {
            DB::transaction(fn () => $this->subProject->saveOrFail(), self::RETRY_ATTEMPTS);
        } catch (Throwable $e) {
            throw new StorageException('Saving of the project data failed', previous: $e);
        }
    }

    private function getJobs(): array
    {
        return data_get($this->subProject->cat_metadata, $this->getProjectInfoKey().'.project.jobs') ?:
            throw new DomainException('Accessing of Jobs for not created project');
    }

    private function getProjectCreationKey(): string
    {
        return 'project-create-response';
    }

    private function getProjectCreationStatusKey(): string
    {
        return 'project-create-status-response';
    }

    private function getProjectFilesKey(): string
    {
        return 'project-files';
    }

    private function getProjectMTEnabledFlagKey(): string
    {
        return 'project-mt-enabled';
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

    private function normalizeAnalysisResult(array $analysisResult, array $filesNames): VolumeAnalysis
    {
        return new VolumeAnalysis([
            'files_names' => $filesNames,
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
