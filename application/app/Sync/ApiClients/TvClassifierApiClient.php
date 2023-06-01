<?php

namespace App\Sync\ApiClients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class TvClassifierApiClient
{
    private string $baseUrl;

    public function __construct(private readonly ServiceAccountJwtRetrieverInterface $jwtRetriever)
    {
        $this->baseUrl = rtrim(config('sync.classifier_service_base_url'), '/');
    }

    /**
     * @throws RequestException
     */
    public function getClassifier(string $id): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/classifier-values/$id")
            ->throw()->json('data');
    }

    /**
     * @throws RequestException
     */
    public function getClassifiers(): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/classifier-values")->throw()
            ->json('data');
    }

    private function getBaseRequest(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->jwtRetriever->getJwt(),
        ])->throw();
    }
}
