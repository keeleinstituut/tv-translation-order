<?php

namespace tests\Feature\Integration\Listeners;

use App\Events\Institutions\InstitutionSaved;
use App\Listeners\Institutions\SaveInstitutionListener;
use App\Models\Cached\Institution;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class SaveInstitutionListenerTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    public function test_institution_updated_event_listened(): void
    {
        $institution = Institution::factory()->create();
        $newInstitutionAttributes = $this->generateInstitutionResponseData($institution->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionResponse($newInstitutionAttributes),
        ]);

        $this->app->make(SaveInstitutionListener::class)
            ->handle(new InstitutionSaved($institution->id));

        $institution->refresh();
        $this->assertModelHasAttributesValues($institution, $newInstitutionAttributes);
    }

    public function test_trashed_institution_updated_event_listened(): void
    {
        $institution = Institution::factory()->create();
        $newInstitutionAttributes = $this->generateInstitutionResponseData($institution->id, true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionResponse($newInstitutionAttributes),
        ]);

        $this->app->make(SaveInstitutionListener::class)
            ->handle(new InstitutionSaved($institution->id));

        $institution->refresh();
        $this->assertModelHasAttributesValues($institution, $newInstitutionAttributes);
    }

    public function test_institution_created_event_listened(): void
    {
        $institutionAttributes = $this->generateInstitutionResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionResponse($institutionAttributes),
        ]);

        $this->app->make(SaveInstitutionListener::class)
            ->handle(new InstitutionSaved($institutionAttributes['id']));

        $institution = Institution::withTrashed()->where('id', '=', $institutionAttributes['id'])->first();
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $institutionAttributes);
    }

    public function test_trashed_institution_created_event_listened(): void
    {
        $institutionAttributes = $this->generateInstitutionResponseData(isDeleted: true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionResponse($institutionAttributes),
        ]);

        $this->app->make(SaveInstitutionListener::class)
            ->handle(new InstitutionSaved($institutionAttributes['id']));

        $institution = Institution::withTrashed()->where('id', '=', $institutionAttributes['id'])->first();
        $this->assertModelExists($institution);
        $this->assertModelHasAttributesValues($institution, $institutionAttributes);
    }

    public function test_institution_saved_event_with_404_gateway_response_listened(): void
    {
        $institution = Institution::factory()->create();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeNotFoundInstitutionResponse(),
        ]);

        $this->app->make(SaveInstitutionListener::class)
            ->handle(new InstitutionSaved($institution->id));

        $this->assertModelMissing($institution);
    }
}
