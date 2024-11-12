<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvClassifierApiClient;
use App\Sync\Gateways\ClassifierValueResourceGateway;
use App\Sync\Repositories\ClassifierValueRepository;
use Database\Seeders\JobDefinitionSeeder;
use Database\Seeders\ProjectTypeConfigSeeder;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\AmqpBase;
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

    public function handle(AmqpBase $amqpBase): void
    {
        parent::handle($amqpBase);
        (new ProjectTypeConfigSeeder())->run();
        (new JobDefinitionSeeder())->run();
    }

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
        return 'tv-translation-order.classifier-value';
    }
}
