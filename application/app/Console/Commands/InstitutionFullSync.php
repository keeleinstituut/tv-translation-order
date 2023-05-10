<?php

namespace App\Console\Commands;

use Amqp\Console\Base\BaseEntityFullSyncCommand;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\InstitutionResourceGateway;
use App\Repositories\InstitutionRepository;

class InstitutionFullSync extends BaseEntityFullSyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'institution:full-sync';

    function getResourceGateway(): ResourceGatewayInterface
    {
        return new InstitutionResourceGateway;
    }

    function getEntityRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }
}
