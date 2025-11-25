<?php

namespace tests\Feature\Integration\Sync;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\TestCase;

class InstitutionUserSyncTest extends TestCase
{
    use ApiResponseHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_institution_user_created(): void
    {
        $institutionUserAttributes = $this->generateInstitutionUserResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($institutionUserAttributes),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $institutionUserAttributes['id']])
            ->assertExitCode(0);

        $institutionUser = InstitutionUser::withTrashed()->where('id', '=', $institutionUserAttributes['id'])->first();
        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $institutionUserAttributes);
    }

    public function test_trashed_institution_user_created(): void
    {
        $institutionUserAttributes = $this->generateInstitutionUserResponseData(isDeleted: true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($institutionUserAttributes),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $institutionUserAttributes['id']])
            ->assertExitCode(0);

        $institutionUser = InstitutionUser::withTrashed()->where('id', '=', $institutionUserAttributes['id'])->first();
        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $institutionUserAttributes);
    }

    public function test_institution_user_updated(): void
    {
        $institutionUser = InstitutionUser::factory()->create();
        $newInstitutionUserAttributes = $this->generateInstitutionUserResponseData($institutionUser->id);

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($newInstitutionUserAttributes),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $institutionUser->id])->assertExitCode(0);

        $institutionUser->refresh();

        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $newInstitutionUserAttributes);
    }

    public function test_trashed_institution_user_updated(): void
    {
        $institutionUser = InstitutionUser::factory()->create();
        $newInstitutionUserAttributes = $this->generateInstitutionUserResponseData($institutionUser->id, true);

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($newInstitutionUserAttributes),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $institutionUser->id])->assertExitCode(0);

        $institutionUser->refresh();

        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $newInstitutionUserAttributes);
    }

    public function test_institution_user_deleted(): void
    {
        $institution = InstitutionUser::factory()->create();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeNotFoundInstitutionUserResponse(),
        ]);

        $this->artisan('sync:single:institution-user', ['id' => $institution->id])->assertExitCode(0);
        $this->assertModelSoftDeleted($institution);
    }
}
