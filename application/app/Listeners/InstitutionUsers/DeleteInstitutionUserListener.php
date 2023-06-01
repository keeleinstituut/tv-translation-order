<?php

namespace App\Listeners\InstitutionUsers;

use App\Sync\Repositories\InstitutionUserRepository;
use SyncTools\Listeners\EntityDeleteEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class DeleteInstitutionUserListener extends EntityDeleteEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionUserRepository;
    }
}
