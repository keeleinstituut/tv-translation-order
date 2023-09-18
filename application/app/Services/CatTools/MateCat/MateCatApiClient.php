<?php

namespace App\Services\CatTools\MateCat;

use App\Models\Media;
use Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

readonly class MateCatApiClient
{
    private int $timeout;

    private int $connectionTimeout;

    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('matecat.base_url');
        $this->timeout = config('matecat.timeout', 30);
        $this->connectionTimeout = config('matecat.connection_timeout', 30);
    }

    /**
     * @param  Collection<int, Media>  $files
     *
     * @throws RequestException
     */
    public function createProject(array $params, Collection $files): array
    {
        $params = collect($params);
        if (! $params->has('name', 'source_lang', 'target_lang')) {
            throw new InvalidArgumentException("'name', 'source_lang', 'target_lang' params are required");
        }

        $request = $this->getBasePendingRequest();
        $files->map(fn (Media $file) => $request->attach(
            'files[]',
            $file->stream(),
            $file->file_name
        ));

        return $request->post('/v1/new', [
            'project_name' => $params->get('name'),
            'source_lang' => $params->get('source_lang'),
            'target_lang' => $params->get('target_lang'),
        ])->throw()->json();
    }

    public function retrieveCreationStatus(int $id, string $password): array
    {
        return $this->getBasePendingRequest()
            ->get("/v2/projects/$id/$password/creation_status")
            ->json();
    }

    /**
     * @throws RequestException
     */
    public function retrieveProjectUrls(int $id, string $password): array
    {
        return $this->getBasePendingRequest()
            ->get("/v2/projects/$id/$password/urls")
            ->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function retrieveProjectInfo(int $id, string $password): array
    {
        return $this->getBasePendingRequest()
            ->get("/v2/projects/$id/$password")
            ->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function retrieveProjectAnalyzingStatus(int $id, string $password): array
    {
        return $this->getBasePendingRequest()->get('/status', [
            'id_project' => $id,
            'project_pass' => $password,
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function checkSplitPossibility(int $id, string $password, int $jobId, string $jobPassword, int $numSplit): array
    {
        return $this->getBasePendingRequest()
            ->post("/v2/projects/$id/$password/jobs/$jobId/$jobPassword/split/$numSplit/check")
            ->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function split(int $id, string $password, int $jobId, string $jobPassword, int $numSplit): array
    {
        return $this->getBasePendingRequest()
            ->post("/v2/projects/$id/$password/jobs/$jobId/$jobPassword/split/$numSplit/apply")
            ->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function merge(int $id, string $password, int $jobId): array
    {
        return $this->getBasePendingRequest()
            ->post("/v2/projects/$id/$password/jobs/$jobId/merge")
            ->throw()->json();
    }

    protected function getBasePendingRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectionTimeout);
    }
}
