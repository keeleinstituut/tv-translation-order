<?php

namespace tests\Feature\Integration\Listeners;

use App\Events\Institutions\InstitutionDeleted;
use App\Listeners\Institutions\DeleteInstitutionListener;
use App\Models\CachedEntities\Institution;
use Tests\TestCase;

class DeleteInstitutionListenerTest extends TestCase
{
    public function test_institution_deleted_event_listened(): void
    {
        $institution = Institution::factory()->create();
        $this->app->make(DeleteInstitutionListener::class)
            ->handle(new InstitutionDeleted($institution->id));
        $this->assertModelSoftDeleted($institution);
    }
}
