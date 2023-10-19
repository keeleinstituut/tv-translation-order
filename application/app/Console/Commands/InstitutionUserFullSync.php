<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\Gateways\InstitutionUserResourceGateway;
use App\Sync\Repositories\InstitutionUserRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\Console\Base\BaseEntityFullSyncCommand;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionUserFullSync extends BaseEntityFullSyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:institution-users';

    /**
     * @throws BindingResolutionException
     */
    protected function getResourceGateway(): ResourceGatewayInterface
    {
        return new InstitutionUserResourceGateway(
            app()->make(TvAuthorizationApiClient::class)
        );
    }

    protected function getEntityRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository();
    }

    protected function getSingleSyncQueueName(): string
    {
        return "tv-translation-order.institution-user";
    }
}
