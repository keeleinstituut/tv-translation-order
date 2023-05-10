<?php

namespace App\Console\Commands;

use Amqp\Console\Base\BaseEntitySyncCommand;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\ClassifierValueResourceGateway;
use App\Repositories\ClassifierValueRepository;

class ClassifierValueSync extends BaseEntitySyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'classifier-value:sync {id : ID of classifier value}';

    function getGateway(): ResourceGatewayInterface
    {
        return new ClassifierValueResourceGateway;
    }

    function getRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository;
    }
}
