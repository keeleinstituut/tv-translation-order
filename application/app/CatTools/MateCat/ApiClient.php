<?php

namespace App\CatTools\MateCat;

use App\CatTools\MateCat\Contracts\SourceFile;
use Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

readonly class ApiClient
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
     * @throws RequestException
     */
    public function createProject(array $params, Collection $files): array
    {
        $params = collect($params);
        if (!$params->has('name', 'source_lang', 'target_lang')) {
            throw new InvalidArgumentException("'name', 'source_lang', 'target_lang' params are required");
        }

        $request = $this->getBasePendingRequest();
        $files->map(fn(SourceFile $file) => $request->attach(
            'files[]',
            $file->getContent(),
            $file->getName()
        ));

        return $request->post('/v1/new', [
            'project_name' => $params->get('name'),
            'source_lang' => $params->get('source_lang'),
            'target_lang' => $params->get('target_lang')
        ])->throw()->json();
    }

    /**
     * @throws RequestException
     */
    public function retrieveCreationStatus(int $id, string $password): array
    {
        return $this->getBasePendingRequest()
            ->get("/v2/projects/$id/$password/creation_status")
            ->throw()->json();
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
    public function retrieveProjectStatus(int $id, string $password): array
    {
        return $this->getBasePendingRequest()->get('/status', [
            'id_project' => $id,
            'project_pass' => $password,
        ])->throw()->json();
    }

    protected function getBasePendingRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectionTimeout)
            ->throw();
    }
}
