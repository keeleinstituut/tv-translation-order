<?php

namespace App\Services\CatTools\MateCat;

use App\Jobs\TrackMateCatProjectAnalyzingStatus;
use App\Jobs\TrackMateCatProjectCreationStatus;
use App\Jobs\TrackMateCatProjectProgress;
use App\Models\CatToolTmKey;
use App\Models\Media;
use App\Models\SubProject;
use App\Services\CatTools\Contracts\CatToolService;
use App\Services\CatTools\Contracts\DownloadableFile;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use App\Services\CatTools\Exceptions\CatToolRetrievingException;
use App\Services\CatTools\Exceptions\CatToolSetupFailedException;
use App\Services\CatTools\Exceptions\UnexpectedResponseFormatException;
use BadMethodCallException;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

readonly class MateCat implements CatToolService
{
    const DUMMY_MT_ENGINE_ID = 0;

    private MateCatApiClient $apiClient;

    private MateCatProjectMetaDataStorage $storage;

    public function __construct(private SubProject $subProject)
    {
        $this->apiClient = new MateCatApiClient();
        $this->storage = new MateCatProjectMetaDataStorage($subProject);
    }

    /**
     * @param  array  $filesIds
     * {@inheritDoc}
     *
     * @throws ValidationException
     */
    public function setupJobs(array $filesIds = null, bool $useMT = true): void
    {
        if ($this->getSetupStatus() === CatToolSetupStatus::Done) {
            throw new BadMethodCallException('Cat tool is already setup');
        }

        if (! $this->storage->isEmpty()) {
            $this->storage->clean();
        }

        if (is_null($filesIds)) {
            $files = $this->subProject->sourceFiles;
        } else {
            $files = $this->subProject->sourceFiles->filter(
                fn (Media $sourceFile) => in_array($sourceFile->id, $filesIds)
            )->values();
        }

        if (! is_null($filesIds) && count($filesIds) !== count($files)) {
            throw new InvalidArgumentException('Incorrect files IDs');
        }

        if (empty($this->subProject->catToolTmKeys)) {
            throw new InvalidArgumentException('Project should have at least one TM key');
        }

        try {
            $params = [
                'name' => $this->subProject->ext_id,
                'source_lang' => $this->subProject->sourceLanguageClassifierValue->value,
                'target_lang' => $this->subProject->destinationLanguageClassifierValue->value,
                'tm_keys' => $this->subProject->catToolTmKeys
                    ->map(fn (CatToolTmKey $key) => ExternalTmKeyComposer::compose($key))
                    ->toArray(),
            ];

            if (! $this->storage->hasMTEnabled()) {
                $params['mt_engine'] = self::DUMMY_MT_ENGINE_ID;
            }

            $response = $this->apiClient->createProject($params, $files);
        } catch (RequestException $e) {
            if ($e->response->status() === Response::HTTP_BAD_REQUEST) {
                $errorMessage = $e->response->json('debug') ?:
                    $e->response->json('message');

                throw new InvalidArgumentException($errorMessage);
            }

            if ($e->response->status() === Response::HTTP_UNPROCESSABLE_ENTITY) {
                $tmKeysErrors = $e->response->json('errors.tm_keys');
                if (! empty($tmKeysErrors)) {
                    throw ValidationException::withMessages([
                        'tm_keys' => $tmKeysErrors,
                    ])->status(Response::HTTP_BAD_REQUEST);
                }
            }

            throw new CatToolSetupFailedException('Project not created', previous: $e);
        }

        if ($response['status'] !== 'OK') {
            throw new CatToolSetupFailedException('Project creation failed');
        }

        $this->storage->storeCreatedProjectMeta($response);
        $this->storage->storeProjectFiles($filesIds);

        Bus::chain([
            new TrackMateCatProjectCreationStatus($this->subProject),
            new TrackMateCatProjectAnalyzingStatus($this->subProject),
            new TrackMateCatProjectProgress($this->subProject),
        ])->dispatch();
    }

    /**
     * {@inheritDoc}
     */
    public function getDownloadableXLIFFsFile(): DownloadableFile
    {
        try {
            $response = Http::withOptions(['stream' => true])
                ->get($this->storage->getXLIFFsDownloadUrl())
                ->throw();
        } catch (RequestException $e) {
            throw new CatToolRetrievingException('Retrieving of XLIFF files failed.', previous: $e);
        }

        return new MateCatDownloadableFile(
            $response,
            "{$this->subProject->ext_id}-xliff"
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getDownloadableTranslationsFile(): DownloadableFile
    {
        try {
            $response = Http::withOptions(['stream' => true])
                ->get($this->storage->getTranslationsDownloadUrl())
                ->throw();
        } catch (RequestException $e) {
            throw new CatToolRetrievingException('Retrieving of translations files failed.', previous: $e);
        }

        return new MateCatDownloadableFile(
            $response,
            "{$this->subProject->ext_id}-translation"
        );
    }

    /**
     * {@inheritDoc}
     */
    public function split(int $jobsCount): Collection
    {
        if ($jobsCount < 1) {
            throw new InvalidArgumentException('Chunks count should be gather than 1');
        }

        if (! $job = $this->subProject->catToolJobs->first()) {
            throw new DomainException('Job not found for the project');
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
            if ($e->response->status() === 422) {
                throw new InvalidArgumentException("Not possible to split job with $jobsCount chunks. Please try another amount.");
            }

            throw new CatToolRetrievingException('Check splitting possibility failed.', previous: $e);
        }

        if (empty($checkSplitPossibilityResponse['data']['chunks'])) {
            throw new UnexpectedResponseFormatException('Unexpected response format from split possibility check.');
        }

        $availableChunksCount = count($checkSplitPossibilityResponse['data']['chunks']);
        if ($availableChunksCount !== $jobsCount) {
            throw new InvalidArgumentException("Not possible to split job with $jobsCount chunks. Please try to split with amount less or equal to $availableChunksCount.");
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
            throw new CatToolRetrievingException('Splitting failed.', previous: $e);
        }

        $this->storage->storeSplittingResult($splitResponse);
        $this->updateProjectInfo();
        TrackMateCatProjectAnalyzingStatus::dispatch($this->subProject);

        return $this->subProject->catToolJobs;
    }

    /**
     * {@inheritDoc}
     */
    public function merge(): Collection
    {
        if (! $this->storage->wasSplit()) {
            throw new DomainException("Can't merge project that wasn't split before");
        }

        if (empty($job = $this->subProject->catToolJobs->first())) {
            throw new DomainException('Job not found for the project');
        }

        try {
            $response = $this->apiClient->merge(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $job->metadata['id']
            );
        } catch (RequestException $e) {
            throw new RuntimeException('', 0, $e);
        }

        if (! isset($response['success'])) {
            throw new UnexpectedResponseFormatException('Unexpected merge project jobs response format');
        }

        $this->storage->storeMergingResult($response);

        if ($response['success']) {
            $this->updateProjectInfo();
            TrackMateCatProjectAnalyzingStatus::dispatch($this->subProject);
        }

        return $this->subProject->catToolJobs;
    }

    public function checkProjectCreated(): bool
    {
        $response = $this->apiClient->retrieveCreationStatus(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword()
        );

        $this->storage->storeProjectCreationStatus($response);

        if ($response['status'] === 200) {
            return true;
        }

        // Project in queue. Wait.
        if ($response['status'] === 202) {
            return false;
        }

        throw new UnexpectedResponseFormatException('Retrieving of project creation status responded with unexpected response');
    }

    public function checkProjectAnalyzed(): bool
    {
        try {
            $response = $this->apiClient->retrieveProjectAnalyzingStatus(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new CatToolRetrievingException('Retrieving of CAT analysis status failed.', previous: $e);
        }

        $this->storage->storeAnalyzingResults($response);

        if ($response['status'] === 'DONE') {
            return true;
        }

        if ($response['status'] === 'ANALYZING') {
            return false;
        }

        throw new UnexpectedResponseFormatException('Unexpected project analyzing response status');
    }

    public function updateProjectTranslationUrls(): void
    {
        try {
            $response = $this->apiClient->retrieveProjectUrls(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword()
            );
        } catch (RequestException $e) {
            throw new CatToolRetrievingException('CAT urls retrieving failed.', previous: $e);
        }

        if (empty($response['urls'])) {
            throw new UnexpectedResponseFormatException('Unexpected project URLs response format');
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
            throw new CatToolRetrievingException('CAT data retrieving failed.', previous: $e);
        }

        if (! isset($response['project'])) {
            throw new UnexpectedResponseFormatException('Unexpected project info response format');
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
            throw new CatToolRetrievingException('Translation progress retrieving failed.', previous: $e);
        }

        if (! isset($response['project'])) {
            throw new UnexpectedResponseFormatException('Unexpected project info response format');
        }

        $this->storage->storeProjectProgress($response);
    }

    public function isAnalyzed(): bool
    {
        return $this->storage->getAnalyzingStatus() === 'DONE';
    }

    public function getSourceFiles(): Collection
    {
        $sourceFilesIds = $this->storage->getProjectSourceFilesIds();

        return $this->subProject->sourceFiles->filter(
            fn (Media $sourceFile) => in_array($sourceFile->id, $sourceFilesIds)
        )->values();
    }

    /**
     * @throws RequestException
     */
    public function toggleMtEngine(bool $isEnabled): void
    {
        if ($this->getSetupStatus() === CatToolSetupStatus::Done) {
            $this->apiClient->toggleMTEngine(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $isEnabled
            );
        }

        $this->storage->storeIsMTEnabled($isEnabled);
    }

    public function hasMtEnabled(): bool
    {
        return $this->storage->hasMTEnabled();
    }

    /**
     * @throws ValidationException
     * @throws RequestException
     */
    public function setTmKeys(): void
    {
        try {
            $this->apiClient->setTMKeys(
                $this->storage->getProjectId(),
                $this->storage->getProjectPassword(),
                $this->subProject->catToolTmKeys()->get()
                    ->map(fn (CatToolTmKey $tmKey) => ExternalTmKeyComposer::compose($tmKey))
                    ->toArray()
            );
        } catch (RequestException $e) {
            if ($e->response->status() === Response::HTTP_UNPROCESSABLE_ENTITY) {
                $errors = $e->response->json('errors');
                if (! empty($errors)) {
                    throw ValidationException::withMessages([
                        'tm_keys' => $errors,
                    ])->status(Response::HTTP_BAD_REQUEST);
                }
            }

            throw $e;
        }
    }

    public function getSetupStatus(): CatToolSetupStatus
    {
        if (empty($this->storage->getCreationStatusResponse())) {
            return CatToolSetupStatus::NotStarted;
        }

        if (filled($this->storage->getCreationError())) {
            return CatToolSetupStatus::Failed;
        }

        if ($this->storage->getCreationStatus() == 200) {
            return CatToolSetupStatus::Done;
        }

        return CatToolSetupStatus::InProgress;
    }
}
