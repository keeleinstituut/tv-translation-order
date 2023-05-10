<?php

namespace App\Listeners\Institutions;

use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Listeners\EntitySaveEventListener;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\InstitutionResourceGateway;
use App\Repositories\InstitutionRepository;

class SaveInstitutionListener extends EntitySaveEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }

    protected function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionResourceGateway;
    }
}
