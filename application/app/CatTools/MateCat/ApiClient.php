<?php

namespace App\CatTools\MateCat;

use App\CatTools\MateCat\Contracts\SourceFile;
use Http;
use Illuminate\Http\Client\PendingRequest;
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
        $this->timeout = config('matecat.timeout', 5);
        $this->connectionTimeout = config('matecat.connection_timeout', 5);
    }

    public function createProject(Collection $params, Collection $files)
    {
        if (! $params->has('name', 'source_lang', 'target_lang')) {
            throw new InvalidArgumentException("'name', 'source_lang', 'target_lang' params are required");
        }

        $request = $this->getBasePendingRequest();
        $files->map(fn(SourceFile $file) => $request->attach(
            'files[]',
            $file->getName(),
            $file->getContent()
        ));

        return $request->post('/v1/new', [
            'project_name' => $params->get('name'),
            'source_lang' => $params->get('source_lang'),
            'target_lang' => $params->get('target_lang')
        ])->json();
    }

    protected function getBasePendingRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectionTimeout)
            ->throw();
    }
}
