<?php

namespace tests\Feature\Integration\Sync;

use App\Models\CachedEntities\Institution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class InstitutionSyncTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_institution_created(): void
    {
        $institutionAttributes = $this->generateInstitutionResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionResponse($institutionAttributes),
        ]);
        $this->artisan('sync:single:institution', ['id' => $institutionAttributes['id']])
            ->assertExitCode(0);

        $institution = Institution::withTrashed()->where('id', '=', $institutionAttributes['id'])->first();
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $institutionAttributes);
    }

    public function test_institution_updated(): void
    {
        $institution = Institution::factory()->create();
        $newInstitutionAttributes = $this->generateInstitutionResponseData($institution->id);

        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionResponse($newInstitutionAttributes),
        ]);

        $this->artisan('sync:single:institution', ['id' => $institution->id])->assertExitCode(0);

        $institution->refresh();

        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $newInstitutionAttributes);
    }

    public function test_institution_deleted(): void
    {
        $institution = Institution::factory()->create();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeNotFoundInstitutionResponse(),
        ]);

        $this->artisan('sync:single:institution', ['id' => $institution->id])->assertExitCode(0);
        $this->assertModelSoftDeleted($institution);
    }
}
