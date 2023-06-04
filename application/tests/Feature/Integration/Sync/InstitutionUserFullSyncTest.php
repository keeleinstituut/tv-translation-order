<?php

namespace tests\Feature\Integration\Sync;

use App\Models\InstitutionUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\TestCase;

class InstitutionUserFullSyncTest extends TestCase
{
    use ApiResponseHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_institution_users_synced(): void
    {
        $institutionUserAttributes = $this->generateInstitutionUserResponseData();

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUsersResponse([
                $institutionUserAttributes,
            ]),
        ]);

        $this->artisan('institution-user:full-sync')->assertExitCode(0);

        $institutionUser = InstitutionUser::withTrashed()
            ->where('id', '=', $institutionUserAttributes['id'])
            ->first();

        $this->assertNotNull($institutionUser);
        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData(
            $institutionUser, $institutionUserAttributes
        );
    }

    public function test_not_synced_institution_users_removed(): void
    {
        $institutionUsers = InstitutionUser::factory(10)->create(['synced_at' => Carbon::now()->subDay()]);

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUsersResponse(),
        ]);

        $this->artisan('institution-user:full-sync')->assertExitCode(0);

        foreach ($institutionUsers as $institutionUser) {
            $this->assertModelMissing($institutionUser);
        }
    }

    public function test_synced_institution_users_updated(): void
    {
        $institutionUser = InstitutionUser::factory()->create();
        $newInstitutionUserAttributes = $this->generateInstitutionUserResponseData($institutionUser->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUsersResponse([
                $newInstitutionUserAttributes,
            ]),
        ]);

        $this->artisan('institution-user:full-sync')->assertExitCode(0);

        $institutionUser->refresh();
        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData(
            $institutionUser, $newInstitutionUserAttributes
        );
    }
}
