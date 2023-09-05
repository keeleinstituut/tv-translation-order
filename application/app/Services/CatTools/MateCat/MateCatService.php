<?php

namespace App\Services\CatTools\MateCat;

use App\Jobs\TrackMateCatProjectAnalyzingStatus;
use App\Jobs\TrackMateCatProjectCreationStatus;
use App\Jobs\TrackMateCatProjectProgress;
use App\Models\SubProject;
use App\Services\CatTools\CatToolUserTask;
use App\Services\CatTools\Contracts\SplittableCatTools;
use App\Services\CatTools\Exceptions\ProjectCreationFailedException;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

readonly class MateCatService implements SplittableCatTools
{
    private MateCatApiClient $apiClient;
    private MateCatDataStorage $storage;

    public function __construct(private SubProject $subProject)
    {
        $this->apiClient = new MateCatApiClient();
        $this->storage = new MateCatDataStorage($subProject);
    }

    /**
     * @inheritDoc
     */
    public function createProject(): void
    {
        try {
            $response = $this->apiClient->createProject([
                'name' => $this->subProject->ext_id,
                'source_lang' => $this->subProject->sourceLanguageClassifierValue->value,
                'target_lang' => $this->subProject->destinationLanguageClassifierValue->value,
            ], $this->subProject->sourceFiles);
        } catch (RequestException $e) {
            throw new ProjectCreationFailedException("Project not created", 0, $e);
        }

        if ($response['status'] !== 'OK') {
            throw new RuntimeException("MateCat project creation failed.");
        }

        $this->storage->storeCreatedProjectMeta($response);

        Bus::chain([
            new TrackMateCatProjectCreationStatus($this->subProject),
            new TrackMateCatProjectAnalyzingStatus($this->subProject),
            new TrackMateCatProjectProgress($this->subProject)
        ])->dispatch();
    }

    /**
     * @inheritDoc
     */
    public function getUserTasks(): Collection
    {
        return collect(array_map(fn(array $jobData) => new CatToolUserTask(
            $jobData['id'],
            data_get($jobData, 'stats.PROGRESS_PERC'),
            data_get($jobData, 'urls.translate_url'),
            data_get($jobData, 'urls.revise_urls.0.url'),
            data_get($jobData, 'urls.xliff_download_url'),
            data_get($jobData, 'urls.translation_download_url'), [
                'password' => $jobData['password']
            ]
        ), $this->storage->getJobs()));
    }

    /**
     * @inheritDoc
     */
    public function getAnalysisResults(): Collection
    {
        return collect($this->storage->getAnalyzingResults());
    }

    /**
     * @inheritDoc
     */
    public function getXliffFileStreamedDownloadResponse(): Response
    {
        /** @var CatToolUserTask $job */
        $job = $this->getUserTasks()->first();

        if (empty($job)) {
            throw new RuntimeException();
        }

        return Http::withOptions(['stream' => true])
            ->get($job->xliffDownloadUrl)
            ->throw();
    }

    /**
     * @inheritDoc
     */
    public function getTranslationFileStreamedDownloadResponse(): Response
    {
        /** @var CatToolUserTask $job */
        $job = $this->getUserTasks()->first();

        if (empty($job)) {
            throw new RuntimeException();
        }

        return Http::withOptions(['stream' => true])
            ->get($job->translationDownloadUrl)
            ->throw();
    }

    public function checkProjectCreationStatusUpdate(): bool
    {
        try {
            $response = $this->apiClient->retrieveCreationStatus(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new ProjectCreationFailedException("Project not created", 0, $e);
        }

        if ($response['status'] === 200) {
            return true;
        }

        // Project in queue. Wait.
        if ($response['status'] === 202) {
            return false;
        }

        throw new RuntimeException("Retrieving of project creation status responded with unexpected response");
    }

    /**
     * @throws RequestException
     */
    public function checkProjectAnalyzingStatusUpdate(): bool
    {
        $response = $this->apiClient->retrieveProjectStatus(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword()
        );

        $this->storage->storeAnalyzingResults($response);

        if ($response['status'] === 'DONE') {
            return true;
        }

        if ($response['status'] === 'ANALYZING') {
            return false;
        }

        throw new RuntimeException("Unexpected project analyzing response status");
    }

    /**
     * @throws RequestException
     */
    public function updateProjectTranslationUrls(): void
    {
        $response = $this->apiClient->retrieveProjectUrls(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword()
        );

        if (empty($response['urls'])) {
            throw new RuntimeException("Unexpected project URLs response format");
        }

        $this->storage->storeProjectUrls($response);
    }

    public function updateProjectInfo(): void
    {
        $response = $this->apiClient->retrieveProjectInfo(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword()
        );

        if (!isset($response['project'])) {
            throw new RuntimeException("Unexpected project info response format");
        }

        $this->storage->storeProjectInfo($response);
    }

    /**
     * @inheritDoc
     */
    public function split(int $chunksCount): void
    {
        if ($chunksCount < 1) {
            throw new InvalidArgumentException("Chunks count should be gather than 1");
        }

        $job = $this->storage->getJobs()[0] ?? null;

        if (empty($job)) {
            throw new DomainException("Job not found for the project");
        }

        try {
            $checkSplitPossibilityResponse = $this->apiClient->checkSplitPossibility(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $job['id'],
                $job['password'],
                $chunksCount
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        if (empty($checkSplitPossibilityResponse['data']['chunks'])) {
            throw new RuntimeException("Split in $chunksCount chunks is not available");
        }


        $splitResponse = $this->apiClient->split(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword(),
            $job['id'],
            $job['password'],
            $chunksCount
        );

        $this->storage->storeSplittingResult($splitResponse);

        TrackMateCatProjectAnalyzingStatus::dispatch($this->subProject);
    }

    /**
     * @inheritDoc
     */
    public function merge(): void
    {
        if ($this->storage->wasSplit()) {
            throw new DomainException("Can't merge project that wasn't split before");
        }

        if (empty($job = $this->storage->getJobs()[0] ?? null)) {
            throw new DomainException("Job not found for the project");
        }

        $response = $this->apiClient->merge(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword(),
            $job['id']
        );

        if (!isset($response['success'])) {
            throw new RuntimeException("Unexpected merge project jobs response format");
        }

        $this->storage->storeMergingResult($response);

        TrackMateCatProjectAnalyzingStatus::dispatch($this->subProject);
    }
}
