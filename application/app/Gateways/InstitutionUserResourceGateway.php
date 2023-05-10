<?php

namespace App\Gateways;

use Amqp\Exceptions\ResourceGatewayConnectionException;
use Amqp\Exceptions\ResourceNotFoundException;
use Amqp\Gateways\ResourceGatewayInterface;
use Generator;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

readonly class InstitutionUserResourceGateway implements ResourceGatewayInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('sync.authorization_service_base_url'), '/');
    }

    public function getResource(string $id): array
    {
        $response = Http::get("$this->baseUrl/sync/institution-users/$id");

        if ($response->status() === Response::HTTP_NOT_FOUND) {
            throw new ResourceNotFoundException();
        }

        if ($response->status() !== Response::HTTP_OK) {
            throw new ResourceGatewayConnectionException("Gateway responded with status code {$response->status()}");
        }

        return $response->json('data');
    }

    public function getResources(): Generator
    {
        yield from $this->getResourcesRecursively("$this->baseUrl/sync/institution-users");
    }

    /**
     * @param string $url
     * @return Generator
     * @throws ResourceGatewayConnectionException
     */
    private function getResourcesRecursively(string $url): Generator
    {
        $response = Http::get($url);

        if ($response->status() !== Response::HTTP_OK) {
            throw new ResourceGatewayConnectionException("Gateway responded with status code {$response->status()}");
        }

        foreach ($response->json('data') as $resource) {
            yield $resource;
        }

        if ($response->json('links.next')) {
            yield from $this->getResourcesRecursively($response->json('links.next'));
        }
    }
}
