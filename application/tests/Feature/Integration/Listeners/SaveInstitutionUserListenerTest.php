<?php

namespace tests\Feature\Integration\Listeners;

use App\Events\InstitutionUsers\InstitutionUserSaved;
use App\Listeners\InstitutionUsers\SaveInstitutionUserListener;
use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use SyncTools\Exceptions\ResourceGatewayConnectionException;
use Tests\ApiResponseHelpers;
use Tests\EntityHelpers;
use Tests\TestCase;

class SaveInstitutionUserListenerTest extends TestCase
{
    use ApiResponseHelpers, EntityHelpers;

    public function setUp(): void
    {
        parent::setup();
        Carbon::setTestNow(Carbon::create(2023, 6, 4, 12));
        Http::preventStrayRequests();
    }

    /**
     * @throws ResourceGatewayConnectionException
     */
    public function test_institution_user_updated_event_listened(): void
    {
        $institutionUser = InstitutionUser::factory()->create();
        $newInstitutionUserAttributes = $this->generateInstitutionUserResponseData($institutionUser->id);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($newInstitutionUserAttributes),
        ]);

        $this->app->make(SaveInstitutionUserListener::class)
            ->handle(new InstitutionUserSaved($institutionUser->id));

        $institutionUser->refresh();
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $newInstitutionUserAttributes);
    }

    /**
     * @throws ResourceGatewayConnectionException
     */
    public function test_trashed_institution_user_updated_event_listened(): void
    {
        $institutionUser = InstitutionUser::factory()->create();
        $newInstitutionUserAttributes = $this->generateInstitutionUserResponseData($institutionUser->id, true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($newInstitutionUserAttributes),
        ]);

        $this->app->make(SaveInstitutionUserListener::class)
            ->handle(new InstitutionUserSaved($institutionUser->id));

        $institutionUser->refresh();
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $newInstitutionUserAttributes);
    }

    public function test_institution_user_created_event_listened(): void
    {
        $institutionUserAttributes = $this->generateInstitutionUserResponseData();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($institutionUserAttributes),
        ]);

        $this->app->make(SaveInstitutionUserListener::class)
            ->handle(new InstitutionUserSaved($institutionUserAttributes['id']));

        $institutionUser = InstitutionUser::withTrashed()
            ->where('id', '=', $institutionUserAttributes['id'])
            ->first();

        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $institutionUserAttributes);
    }

    public function test_trashed_institution_user_created_event_listened(): void
    {
        $institutionUserAttributes = $this->generateInstitutionUserResponseData(isDeleted: true);
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeInstitutionUserResponse($institutionUserAttributes),
        ]);

        $this->app->make(SaveInstitutionUserListener::class)
            ->handle(new InstitutionUserSaved($institutionUserAttributes['id']));

        $institutionUser = InstitutionUser::withTrashed()
            ->where('id', '=', $institutionUserAttributes['id'])
            ->first();

        $this->assertModelExists($institutionUser);
        $this->assertInstitutionUserHasAttributesValuesFromResponseData($institutionUser, $institutionUserAttributes);
    }

    public function test_institution_user_saved_with_404_gateway_response_listened(): void
    {
        $institution = InstitutionUser::factory()->create();
        Http::fake([
            ...$this->getFakeKeycloakServiceAccountJwtResponse(),
            ...$this->getFakeNotFoundInstitutionUserResponse(),
        ]);

        $this->app->make(SaveInstitutionUserListener::class)
            ->handle(new InstitutionUserSaved($institution->id));

        $this->assertModelSoftDeleted($institution);
    }
}
