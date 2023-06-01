<?php

namespace App\Listeners\ClassifierValues;

use App\Sync\ApiClients\TvClassifierApiClient;
use App\Sync\Gateways\ClassifierValueResourceGateway;
use App\Sync\Repositories\ClassifierValueRepository;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Listeners\EntitySaveEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class SaveClassifierValueListener extends EntitySaveEventListener
{
    public function __construct(private readonly TvClassifierApiClient $apiClient)
    {
    }

    protected function getGateway(): ResourceGatewayInterface
    {
        return new ClassifierValueResourceGateway($this->apiClient);
    }

    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository;
    }
}
