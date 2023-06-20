<?php

namespace tests\Feature\Integration\Listeners;

use App\Events\InstitutionUsers\InstitutionUserDeleted;
use App\Listeners\InstitutionUsers\DeleteInstitutionUserListener;
use App\Models\Cached\InstitutionUser;
use Tests\TestCase;

class DeleteInstitutionUserListenerTest extends TestCase
{
    public function test_institution_deleted_event_listened(): void
    {
        $institutionUser = InstitutionUser::factory()->create();
        $this->app->make(DeleteInstitutionUserListener::class)
            ->handle(new InstitutionUserDeleted($institutionUser->id));
        $this->assertModelMissing($institutionUser);
    }
}
