<?php

namespace App\Listeners\Institutions;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\Gateways\InstitutionResourceGateway;
use App\Sync\Repositories\InstitutionRepository;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Listeners\EntitySaveEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class SaveInstitutionListener extends EntitySaveEventListener
{
    public function __construct(private readonly TvAuthorizationApiClient $apiClient)
    {
    }

    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }

    protected function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionResourceGateway($this->apiClient);
    }
}
