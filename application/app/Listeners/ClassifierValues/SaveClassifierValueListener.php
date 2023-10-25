<?php

namespace App\Listeners\ClassifierValues;

use App\Sync\ApiClients\TvClassifierApiClient;
use App\Sync\Gateways\ClassifierValueResourceGateway;
use App\Sync\Repositories\ClassifierValueRepository;
use Database\Seeders\JobDefinitionSeeder;
use Database\Seeders\ProjectTypeConfigSeeder;
use SyncTools\Events\SyncEntityEvent;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Listeners\EntitySaveEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class SaveClassifierValueListener extends EntitySaveEventListener
{
    public function __construct(private readonly TvClassifierApiClient $apiClient)
    {
    }

    public function handle(SyncEntityEvent $event): void
    {
        parent::handle($event);
        (new ProjectTypeConfigSeeder())->run();
        (new JobDefinitionSeeder())->run();
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
