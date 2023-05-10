<?php

namespace App\Gateways;

use Amqp\Exceptions\ResourceGatewayConnectionException;
use Amqp\Exceptions\ResourceNotFoundException;
use Amqp\Gateways\ResourceGatewayInterface;
use Generator;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

readonly class InstitutionResourceGateway implements ResourceGatewayInterface
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('sync.authorization_service_base_url'), '/');
    }

    public function getResource(string $id): array
    {
        $response = Http::get("$this->baseUrl/sync/institutions/$id");

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
        $response = Http::get("$this->baseUrl/sync/institutions");

        if ($response->status() !== Response::HTTP_OK) {
            throw new ResourceGatewayConnectionException("Gateway responded with status code {$response->status()}");
        }

        foreach ($response->json('data') as $resource) {
            yield $resource;
        }
    }
}
