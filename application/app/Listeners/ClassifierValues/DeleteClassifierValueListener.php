<?php

namespace App\Listeners\ClassifierValues;

use Amqp\Listeners\EntityDeleteEventListener;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Repositories\ClassifierValueRepository;

class DeleteClassifierValueListener extends EntityDeleteEventListener
{
    function getRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository();
    }
}
