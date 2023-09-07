<?php

namespace App\Services\CatTools\MateCat;

use App\Jobs\TrackMateCatProjectAnalyzingStatus;
use App\Jobs\TrackMateCatProjectCreationStatus;
use App\Jobs\TrackMateCatProjectProgress;
use App\Models\Media;
use App\Models\SubProject;
use App\Services\CatTools\Contracts\DownloadableFile;
use App\Services\CatTools\Contracts\SplittableCatToolJobs;
use App\Services\CatTools\Exceptions\ProjectCreationFailedException;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

readonly class MateCatService implements SplittableCatToolJobs
{
    private MateCatApiClient $apiClient;
    private MateCatProjectMetaDataStorage $storage;

    public function __construct(private SubProject $subProject)
    {
        $this->apiClient = new MateCatApiClient();
        $this->storage = new MateCatProjectMetaDataStorage($subProject);
    }

    /**
     * @param array $filesIds
     * @inheritDoc
     */
    public function setupCatToolJobs(array $filesIds = null): void
    {
        if (is_null($filesIds)) {
            $files = $this->subProject->sourceFiles;
        } else {
            $files = $this->subProject->sourceFiles->filter(
                fn(Media $sourceFile) => in_array($sourceFile->id, $filesIds)
            )->values();
        }

        if (!is_null($filesIds) && count($filesIds) !== count($files)) {
            throw new InvalidArgumentException("Incorrect files IDs");
        }

        try {
            $response = $this->apiClient->createProject([
                'name' => $this->subProject->ext_id,
                'source_lang' => $this->subProject->sourceLanguageClassifierValue->value,
                'target_lang' => $this->subProject->destinationLanguageClassifierValue->value,
            ], $files);
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
    public function getDownloadableXLIFFsFile(): DownloadableFile
    {
        try {
            $response = Http::withOptions(['stream' => true])
                ->get($this->storage->getXLIFFsDownloadUrl())
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException("", previous: $e);
        }

        return new MateCatDownloadableFile(
            $response,
            "{$this->subProject->ext_id}-xliff"
        );
    }

    /**
     * @inheritDoc
     */
    public function getDownloadableTranslationsFile(): DownloadableFile
    {
        try {
            $response = Http::withOptions(['stream' => true])
                ->get($this->storage->getTranslationsDownloadUrl())
                ->throw();
        } catch (RequestException $e) {
            throw new RuntimeException("", previous: $e);
        }

        return new MateCatDownloadableFile(
            $response,
            "{$this->subProject->ext_id}-translation"
        );
    }

    /**
     * @inheritDoc
     */
    public function split(int $jobsCount): void
    {
        if ($jobsCount < 1) {
            throw new InvalidArgumentException("Chunks count should be gather than 1");
        }

        if (!$job = $this->subProject->catToolJobs->first()) {
            throw new DomainException("Job not found for the project");
        }

        try {
            $checkSplitPossibilityResponse = $this->apiClient->checkSplitPossibility(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $job->metadata['id'],
                $job->metadata['password'],
                $jobsCount
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        if (empty($checkSplitPossibilityResponse['data']['chunks'])) {
            throw new RuntimeException("Split in $jobsCount chunks is not available");
        }

        try {
            $splitResponse = $this->apiClient->split(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $job->metadata['id'],
                $job->metadata['password'],
                $jobsCount
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        $this->storage->storeSplittingResult($splitResponse);
        $this->updateProjectInfo();
        TrackMateCatProjectAnalyzingStatus::dispatch($this->subProject);
    }

    /**
     * @inheritDoc
     */
    public function merge(): void
    {
        if (!$this->storage->wasSplit()) {
            throw new DomainException("Can't merge project that wasn't split before");
        }

        if (empty($job = $this->subProject->catToolJobs->first())) {
            throw new DomainException("Job not found for the project");
        }

        try {
            $response = $this->apiClient->merge(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $job->metadata['id']
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        if (!isset($response['success'])) {
            throw new RuntimeException("Unexpected merge project jobs response format");
        }

        $this->storage->storeMergingResult($response);

        if ($response['success']) {
            $this->updateProjectInfo();
            TrackMateCatProjectAnalyzingStatus::dispatch($this->subProject);
        }
    }

    public function checkProjectCreated(): bool
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

    public function checkProjectAnalyzed(): bool
    {
        try {
            $response = $this->apiClient->retrieveProjectStatus(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        $this->storage->storeAnalyzingResults($response);

        if ($response['status'] === 'DONE') {
            return true;
        }

        if ($response['status'] === 'ANALYZING') {
            return false;
        }

        throw new RuntimeException("Unexpected project analyzing response status");
    }

    public function updateProjectTranslationUrls(): void
    {
        try {
            $response = $this->apiClient->retrieveProjectUrls(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        if (empty($response['urls'])) {
            throw new RuntimeException("Unexpected project URLs response format");
        }

        $this->storage->storeProjectUrls($response);
    }

    public function updateProjectInfo(): void
    {
        try {
            $response = $this->apiClient->retrieveProjectInfo(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        if (!isset($response['project'])) {
            throw new RuntimeException("Unexpected project info response format");
        }

        $this->storage->storeProjectInfo($response);
    }

    public function updateProjectProgress(): void
    {
        try {
            $response = $this->apiClient->retrieveProjectInfo(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new RuntimeException("", 0, $e);
        }

        if (!isset($response['project'])) {
            throw new RuntimeException("Unexpected project info response format");
        }

        $this->storage->storeProjectProgress($response);
    }
}
