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

readonly class InstitutionResourceGateway implements ResourceGatewayInterface
{
    public function __construct(private TvAuthorizationApiClient $apiClient)
    {
    }

    /**
     * @throws ResourceGatewayConnectionException
     * @throws ResourceNotFoundException
     * @throws InvalidArgumentException
     */
    public function getResource(string $id): array
    {
        try {
            return $this->apiClient->getInstitution($id);
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
        try {
            foreach ($this->apiClient->getInstitutions() as $institutionData) {
                yield $institutionData;
            }
        } catch (RequestException $e) {
            throw new ResourceGatewayConnectionException("Gateway responded with status code {$e->getCode()}", 0, $e);
        }
    }
}
