<?php

namespace tests\Feature\Integration\Sync;

use App\Models\CachedEntities\Institution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class InstitutionFullSyncTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_institutions_synced(): void
    {
        $institutionAttributes = $this->generateInstitutionResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionsResponse([
                $institutionAttributes,
            ]),
        ]);

        $this->artisan('sync:institutions')->assertExitCode(0);

        $institution = Institution::withTrashed()
            ->where('id', '=', $institutionAttributes['id'])
            ->first();

        $this->assertNotNull($institution);
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $institutionAttributes);
    }

    public function test_trashed_institutions_synced(): void
    {
        $institutionAttributes = $this->generateInstitutionResponseData(isDeleted: true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionsResponse([
                $institutionAttributes,
            ]),
        ]);

        $this->artisan('sync:institutions')->assertExitCode(0);

        $institution = Institution::withTrashed()
            ->where('id', '=', $institutionAttributes['id'])
            ->first();

        $this->assertNotNull($institution);
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $institutionAttributes);
    }

    public function test_not_synced_institutions_removed(): void
    {
        $institutions = Institution::factory(10)->create(['synced_at' => Carbon::now()->subDay()]);

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionsResponse(),
        ]);

        $this->artisan('sync:institutions')->assertExitCode(0);
        foreach ($institutions as $institution) {
            // Refresh the model from database to get current state
            $institution->refresh();
            $this->assertModelSoftDeleted($institution);
        }
    }

    public function test_synced_institutions_updated(): void
    {
        $institution = Institution::factory()->create();
        $newInstitutionAttributes = $this->generateInstitutionResponseData($institution->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionsResponse([
                $newInstitutionAttributes,
            ]),
        ]);

        $this->artisan('sync:institutions')->assertExitCode(0);

        $institution->refresh();
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $newInstitutionAttributes);
    }

    public function test_trashed_synced_institutions_updated(): void
    {
        $institution = Institution::factory()->create();
        $newInstitutionAttributes = $this->generateInstitutionResponseData($institution->id, true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionsResponse([
                $newInstitutionAttributes,
            ]),
        ]);

        $this->artisan('sync:institutions')->assertExitCode(0);

        $institution->refresh();
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $newInstitutionAttributes);
    }
}
