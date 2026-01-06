<?php

namespace App\Sync\ApiClients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class TvAuthorizationApiClient
{
    private string $baseUrl;

    public function __construct(private readonly ServiceAccountJwtRetrieverInterface $jwtRetriever)
    {
        $this->baseUrl = rtrim(config('sync.authorization_service_base_url'), '/');
    }

    public function getInstitution(string $id): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institutions/$id")
            ->json('data');
    }

    public function getInstitutions(): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institutions")
            ->json('data');
    }

    public function getInstitutionUser(string $id): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institution-users/$id")
            ->json('data');
    }

    public function getInstitutionUsers(?int $page = null): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institution-users", [
            'page' => $page ?: 1,
        ])->json();
    }

    private function getBaseRequest(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->jwtRetriever->getJwt(),
        ])->throw();
    }
}
