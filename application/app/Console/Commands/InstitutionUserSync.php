<?php

namespace App\Console\Commands;

use Amqp\Console\Base\BaseEntitySyncCommand;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\ClassifierValueResourceGateway;
use App\Gateways\InstitutionUserResourceGateway;
use App\Repositories\ClassifierValueRepository;
use App\Repositories\InstitutionUserRepository;

class InstitutionUserSync extends BaseEntitySyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'institution-user:sync {id : ID of institution user}';

    function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionUserResourceGateway;
    }

    function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository;
    }
}
