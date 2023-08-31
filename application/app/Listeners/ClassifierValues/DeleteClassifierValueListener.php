<?php

namespace App\Listeners\ClassifierValues;

use App\Sync\Repositories\ClassifierValueRepository;
use SyncTools\Listeners\EntityDeleteEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class DeleteClassifierValueListener extends EntityDeleteEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new ClassifierValueRepository();
    }
}
