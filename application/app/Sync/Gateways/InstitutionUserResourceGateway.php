<?php

namespace App\Sync\Gateways;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use Generator;
use Illuminate\Http\Client\RequestException;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use SyncTools\Exceptions\ResourceGatewayConnectionException;
use SyncTools\Exceptions\ResourceNotFoundException;
use SyncTools\Gateways\ResourceGatewayInterface;

readonly class InstitutionUserResourceGateway implements ResourceGatewayInterface
{
    public function __construct(private TvAuthorizationApiClient $apiClient)
    {
    }

    /**
     * @throws ResourceGatewayConnectionException
     * @throws ResourceNotFoundException
     */
    public function getResource(string $id): array
    {
        try {
            return $this->apiClient->getInstitutionUser($id);
        } catch (RequestException $e) {
            if ($e->getCode() === Response::HTTP_NOT_FOUND) {
                throw new ResourceNotFoundException();
            }

            throw new ResourceGatewayConnectionException("Gateway responded with status code {$e->getCode()}", 0, $e);
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws ResourceGatewayConnectionException
     */
    public function getResources(): Generator
    {
        yield from $this->getResourcesRecursively();
    }

    /**
     * @throws ResourceGatewayConnectionException|InvalidArgumentException
     */
    private function getResourcesRecursively(int $page = 1): Generator
    {
        try {
            $response = $this->apiClient->getInstitutionUsers($page);
        } catch (RequestException $e) {
            throw new ResourceGatewayConnectionException("Gateway responded with status code {$e->getCode()}", 0, $e);
        }

        foreach ($response['data'] as $resource) {
            yield $resource;
        }

        if ($response['meta']['current_page'] < $response['meta']['last_page']) {
            yield from $this->getResourcesRecursively($response['meta']['current_page']++);
        }
    }
}
