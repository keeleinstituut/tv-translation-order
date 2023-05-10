<?php

namespace App\Listeners\Institutions;

use Amqp\Listeners\EntityDeleteEventListener;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Repositories\InstitutionRepository;

class DeleteInstitutionListener extends EntityDeleteEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }
}
