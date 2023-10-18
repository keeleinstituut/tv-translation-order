<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\Gateways\InstitutionUserResourceGateway;
use App\Sync\Repositories\InstitutionUserRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\Console\Base\BaseEntitySyncCommand;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionUserSync extends BaseEntitySyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:single:institution-user {id : ID of institution user}';

    /**
     * @throws BindingResolutionException
     */
    protected function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionUserResourceGateway(
            app()->make(TvAuthorizationApiClient::class)
        );
    }

    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository;
    }
}
