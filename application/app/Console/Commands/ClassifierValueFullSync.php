<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvClassifierApiClient;
use App\Sync\Gateways\ClassifierValueResourceGateway;
use App\Sync\Repositories\ClassifierValueRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\Console\Base\BaseEntityFullSyncCommand;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class ClassifierValueFullSync extends BaseEntityFullSyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:classifier-values';

    /**
     * @throws BindingResolutionException
     */
    protected function getResourceGateway(): ResourceGatewayInterface
    {
        return new ClassifierValueResourceGateway(
            app()->make(TvClassifierApiClient::class)
        );
    }

    protected function getEntityRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository;
    }

    protected function getSingleSyncQueueName(): string
    {
        return "tv-translation-order.classifier-value";
    }
}
