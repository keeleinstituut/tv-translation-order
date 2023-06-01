<?php

namespace App\Sync\Gateways;

use App\Sync\ApiClients\TvClassifierApiClient;
use Generator;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;
use SyncTools\Exceptions\ResourceGatewayConnectionException;
use SyncTools\Exceptions\ResourceNotFoundException;
use SyncTools\Gateways\ResourceGatewayInterface;

readonly class ClassifierValueResourceGateway implements ResourceGatewayInterface
{
    public function __construct(private TvClassifierApiClient $apiClient)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getResource(string $id): array
    {
        try {
            return $this->apiClient->getClassifier($id);
        } catch (RequestException $e) {
            if ($e->getCode() === Response::HTTP_NOT_FOUND) {
                throw new ResourceNotFoundException();
            }

            throw new ResourceGatewayConnectionException("Gateway responded with status code {$e->getCode()}", 0, $e);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getResources(): Generator
    {
        try {
            foreach ($this->apiClient->getClassifiers() as $classifierData) {
                yield $classifierData;
            }
        } catch (RequestException $e) {
            throw new ResourceGatewayConnectionException("Gateway responded with status code {$e->getCode()}", 0, $e);
        }
    }
}
