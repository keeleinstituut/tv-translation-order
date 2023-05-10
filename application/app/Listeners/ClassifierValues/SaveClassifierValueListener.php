<?php

namespace App\Listeners\ClassifierValues;

use App\Gateways\ClassifierValueResourceGateway;
use Amqp\Gateways\ResourceGatewayInterface;
use Amqp\Listeners\EntitySaveEventListener;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Repositories\ClassifierValueRepository;

class SaveClassifierValueListener extends EntitySaveEventListener
{
    function getGateway(): ResourceGatewayInterface
    {
        return new ClassifierValueResourceGateway;
    }

    function getRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository;
    }
}
