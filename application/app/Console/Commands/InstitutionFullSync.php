<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use App\Sync\Gateways\InstitutionResourceGateway;
use App\Sync\Repositories\InstitutionRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\Console\Base\BaseEntityFullSyncCommand;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class InstitutionFullSync extends BaseEntityFullSyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:institutions';

    /**
     * @throws BindingResolutionException
     */
    protected function getResourceGateway(): ResourceGatewayInterface
    {
        return new InstitutionResourceGateway(
            app()->make(TvAuthorizationApiClient::class)
        );
    }

    protected function getEntityRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }

    protected function getSingleSyncQueueName(): string
    {
        return "tv-translation-order.institution";
    }
}
