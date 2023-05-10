<?php

namespace App\Console\Commands;

use Amqp\Console\Base\BaseEntitySyncCommand;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\InstitutionResourceGateway;
use App\Repositories\InstitutionRepository;

class InstitutionSync extends BaseEntitySyncCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'institution:sync {id : ID of institution}';

    function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionResourceGateway;
    }

    function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }
}
