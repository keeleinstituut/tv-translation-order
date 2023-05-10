<?php

namespace App\Console\Commands;

use Amqp\Console\Base\BaseEntityFullSyncCommand;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\ClassifierValueResourceGateway;
use App\Repositories\ClassifierValueRepository;

class ClassifierValueFullSync extends BaseEntityFullSyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classifier-value:full-sync';

    function getResourceGateway(): ResourceGatewayInterface
    {
        return new ClassifierValueResourceGateway;
    }

    function getEntityRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository;
    }
}
