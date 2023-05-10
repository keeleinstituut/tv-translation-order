<?php

namespace App\Console\Commands;

use Amqp\Console\Base\BaseEntityFullSyncCommand;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\InstitutionUserResourceGateway;
use App\Repositories\InstitutionUserRepository;

class InstitutionUserFullSync extends BaseEntityFullSyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'institution-user:full-sync';

    function getResourceGateway(): ResourceGatewayInterface
    {
        return new InstitutionUserResourceGateway;
    }

    function getEntityRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository();
    }
}
