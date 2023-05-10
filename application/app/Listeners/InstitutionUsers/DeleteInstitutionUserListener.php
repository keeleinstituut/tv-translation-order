<?php

namespace App\Listeners\InstitutionUsers;

use Amqp\Listeners\EntityDeleteEventListener;
use Amqp\Repositories\CachedEntityRepositoryInterface;
use App\Repositories\InstitutionUserRepository;

class DeleteInstitutionUserListener extends EntityDeleteEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository;
    }
}
