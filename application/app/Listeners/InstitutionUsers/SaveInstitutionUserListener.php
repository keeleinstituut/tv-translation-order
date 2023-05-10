<?php

namespace App\Listeners\InstitutionUsers;

use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Listeners\EntitySaveEventListener;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Gateways\InstitutionUserResourceGateway;
use App\Repositories\InstitutionUserRepository;

class SaveInstitutionUserListener extends EntitySaveEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository;
    }

    protected function getGateway(): ResourceGatewayInterface
    {
        return new InstitutionUserResourceGateway;
    }
}
