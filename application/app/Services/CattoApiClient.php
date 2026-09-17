<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class CattoApiClient
{
    public function __construct(private readonly ServiceAccountJwtRetrieverInterface $jwtRetriever)
    {
    }

    /**
     * @throws RequestException
     */
    public function createProject(string $name, string $sourceLocale, string $institutionId): array
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->jwtRetriever->getJwt(),
        ])
            ->baseUrl(rtrim(config('services.catto.base_url'), '/'))
            ->throw()
            ->post('/projects', [
                'name' => $name,
                'source_locale' => $sourceLocale,
                'tenant_id' => $institutionId,
            ])
            ->json('data');
    }
}
