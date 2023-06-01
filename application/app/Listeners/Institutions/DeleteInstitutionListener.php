<?php

namespace App\Listeners\Institutions;

use App\Sync\Repositories\InstitutionRepository;
use SyncTools\Listeners\EntityDeleteEventListener;
use SyncTools\Repositories\CachedEntityRepositoryInterface;

class DeleteInstitutionListener extends EntityDeleteEventListener
{
    protected function getRepository(): CachedEntityRepositoryInterface
    {
        return new InstitutionRepository;
    }
}
