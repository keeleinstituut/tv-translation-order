<?php

namespace App\CatTools\MateCat;

use App\CatTools\Contracts\CatToolService;
use App\CatTools\SubOrder;
use App\Jobs\TrackMateCatProjectAnalyzingStatus;
use App\Jobs\TrackMateCatProjectCreationStatus;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Throwable;

readonly class MateCatService implements CatToolService
{
    private ApiClient $apiClient;

    private MateCatDataStorage $storage;

    public function __construct(private SubOrder $subOrder)
    {
        $this->apiClient = new ApiClient();
        $this->storage = new MateCatDataStorage($subOrder->id);
    }

    /**
     * @throws RequestException
     */
    public function init(Collection $files): void
    {
        $response = $this->apiClient->createProject([
            'name' => $this->subOrder->id,
            'source_lang' => $this->subOrder->sourceLanguage,
            'target_lang' => $this->subOrder->targetLanguages,
            'subject' => $this->subOrder->id . '-1'
        ], $files);

        if ($response['status'] !== 'OK') {
            throw new RuntimeException("MateCat project creation failed.");
        }
        $this->storage->storeCreatedProjectMeta($response);

        Bus::chain([
            new TrackMateCatProjectCreationStatus($this->subOrder->getMeta()),
            new TrackMateCatProjectAnalyzingStatus($this->subOrder->getMeta())
        ])->catch(function (Throwable $e) {
            $this->subOrder->markAsFailed($e->getMessage());
        })->dispatch();
    }

    /**
     * @throws RequestException
     */
    public function handleProjectCreationStatusUpdate(): bool
    {

        $response = $this->apiClient->retrieveCreationStatus(
            $this->storage->getProjectId(),
            $this->storage->getProjectPassword()
        );

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
    public function handleProjectAnalyzingStatusUpdate(): bool
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

        throw new RuntimeException("Unexpected project analyzing status");
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
            throw new RuntimeException("Unexpected project URLs response");
        }

        $this->storage->storeProjectUrls($response);
    }

    public function getStorage(): MateCatDataStorage
    {
        return $this->storage;
    }
}
