<?php

namespace App\Console\Commands;

use App\Sync\ApiClients\TvClassifierApiClient;
use App\Sync\Gateways\ClassifierValueResourceGateway;
use App\Sync\Repositories\ClassifierValueRepository;
use Database\Seeders\JobDefinitionSeeder;
use Database\Seeders\ProjectTypeConfigSeeder;
use Illuminate\Contracts\Container\BindingResolutionException;
use SyncTools\Console\Base\BaseEntitySyncCommand;
use SyncTools\Gateways\ResourceGatewayInterface;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class ClassifierValueSync extends BaseEntitySyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:single:classifier-value {id : ID of classifier value}';

    public function handle(): void
    {
        parent::handle();
        (new ProjectTypeConfigSeeder())->run();
        (new JobDefinitionSeeder())->run();
    }

    /**
     * @throws BindingResolutionException
     */
    protected function getGateway(): ResourceGatewayInterface
    {
        return new ClassifierValueResourceGateway(
            app()->make(TvClassifierApiClient::class)
        );
    }

    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository;
    }
}
