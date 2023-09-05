<?php

namespace App\Services\CatTools\MateCat;

use App\Jobs\TrackMateCatProjectAnalyzingStatus;
use App\Jobs\TrackMateCatProjectCreationStatus;
use App\Models\Media;
use App\Models\SubProject;
use App\Services\CatTools\Contracts\CatToolService;
use App\Services\CatTools\Exceptions\ProjectCreationFailedException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

readonly class MateCatService implements CatToolService
{
    private MateCatApiClient $apiClient;
    private MateCatDataStorage $storage;

    public function __construct(private SubProject $subProject)
    {
        $this->apiClient = new MateCatApiClient();
        $this->storage = new MateCatDataStorage($subProject);
    }

    public function createProject(array $sourceFilesIds): void
    {
        if (empty($sourceFilesIds)) {
            throw new InvalidArgumentException("Couldn't create project without source files");
        }

        $sourceFiles = $this->subProject->sourceFiles->filter(
            fn(Media $sourceFile) => in_array($sourceFile->id, $sourceFilesIds)
        )->values();

        if (empty($sourceFiles)) {
            throw new InvalidArgumentException("Files with such ids are not exist");
        }

        try {
            $response = $this->apiClient->createProject([
                'name' => $this->subProject->ext_id,
                'source_lang' => $this->subProject->sourceLanguageClassifierValue->value,
                'target_lang' => $this->subProject->destinationLanguageClassifierValue->value,
            ], $sourceFiles);
        } catch (RequestException $e) {
            echo $e->getMessage(), PHP_EOL;
            throw new ProjectCreationFailedException("Project not created", 0, $e);
        }

        if ($response['status'] !== 'OK') {
            throw new RuntimeException("MateCat project creation failed.");
        }

        $this->storage->storeCreatedProjectMeta($response);
        Bus::chain([
            new TrackMateCatProjectCreationStatus($this->subProject),
            new TrackMateCatProjectAnalyzingStatus($this->subProject)
        ])->catch(function (Throwable $e) {
            // TODO: handle fails
        })->dispatch();
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
    public function storeProjectTranslationUrls(): void
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

    public function getJobs(): array
    {
        return [];
    }
}
