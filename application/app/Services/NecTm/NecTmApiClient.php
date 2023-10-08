<?php

namespace App\Services\NecTm;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use KeycloakAuthGuard\Services\CachedServiceAccountJwtRetriever;
use KeycloakAuthGuard\Services\Decoders\JwtTokenDecoder;
use KeycloakAuthGuard\Services\RealmJwkRetrieverInterface;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetriever;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class NecTmApiClient
{
    private int $timeout;

    private int $connectionTimeout;

    private string $baseUrl;

    private ServiceAccountJwtRetrieverInterface $jwtRetriever;

    /**
     * @throws BindingResolutionException
     */
    public function __construct()
    {
        $this->baseUrl = config('nectm.base_url');
        $this->timeout = config('nectm.timeout', 30);
        $this->connectionTimeout = config('nectm.connection_timeout', 30);
        $this->jwtRetriever = $this->getServiceAccountJwtRetriever();
    }

    public function retrieveTm(string $id)
    {
        return $this->getBasePendingRequest()->get("/tags/$id")
            ->json()->throw();
    }

    protected function getBasePendingRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectionTimeout)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->jwtRetriever->getJwt(),
            ]);
    }

    /**
     * @throws BindingResolutionException
     */
    private function getServiceAccountJwtRetriever(): ServiceAccountJwtRetrieverInterface
    {
        return new CachedServiceAccountJwtRetriever(
            new ServiceAccountJwtRetriever(
                config('matecat.nectm_service_account_client_id'),
                config('matecat.nectm_service_account_client_secret')
            ),
            new JwtTokenDecoder(
                app()->make(RealmJwkRetrieverInterface::class)
            ),
            app('cache')->store(config('keycloak.cache_store'))
        );
    }
}
