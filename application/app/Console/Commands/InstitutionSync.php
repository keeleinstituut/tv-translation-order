<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\Gateways\InstitutionResourceGateway;
use App\Sync\Repositories\InstitutionRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\Console\Base\BaseEntitySyncCommand;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionSync extends BaseEntitySyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'institution:sync {id : ID of institution}';

    /**
     * @throws BindingResolutionException
     */
    protected function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionResourceGateway(
            app()->make(TvAuthorizationApiClient::class)
        );
    }

    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }
}
