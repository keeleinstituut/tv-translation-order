<?php

namespace App\Listeners\InstitutionUsers;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\Gateways\InstitutionUserResourceGateway;
use App\Sync\Repositories\InstitutionUserRepository;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Listeners\EntitySaveEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class SaveInstitutionUserListener extends EntitySaveEventListener
{
    public function __construct(private readonly TvAuthorizationApiClient $apiClient)
    {
    }

    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository;
    }

    protected function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionUserResourceGateway($this->apiClient);
    }
}
