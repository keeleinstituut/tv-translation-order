<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\InstitutionUserPinnedLanguage;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class InstitutionUserControllerTest extends TestCase
{
    public function test_pin_language_creates_pin_and_returns_resource(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        $language = ClassifierValue::factory()->language()->create();
        $mainLang = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $language->id]);

        $accessToken = $this->tokenForInstitutionUser($institutionUser);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-users/pinned-languages', [
                'institution_main_language_id' => $mainLang->id,
            ]);

        // THEN
        $response
            ->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'institution_user_id',
                    'institution_main_language_id',
                    'institution_main_language' => ['id', 'institution_id', 'language_id', 'language'],
                ],
            ])
            ->assertJsonFragment([
                'institution_user_id' => $institutionUser->id,
                'institution_main_language_id' => $mainLang->id,
            ]);

        $this->assertDatabaseHas('institution_user_pinned_languages', [
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLang->id,
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function test_pin_language_is_idempotent(): void
    {
        // GIVEN — language already pinned
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])
            ->createWithPrivileges(PrivilegeKey::ManageProject);

        $language = ClassifierValue::factory()->language()->create();
        $mainLang = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $language->id]);

        $existingPin = InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLang->id,
        ]);

        $accessToken = $this->tokenForInstitutionUser($institutionUser);

        // WHEN — pin the same language again
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-users/pinned-languages', [
                'institution_main_language_id' => $mainLang->id,
            ]);

        // THEN — 200 with the same record; no duplicate row
        $response
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $existingPin->id]);

        $this->assertDatabaseCount('institution_user_pinned_languages', 1);
    }

    public function test_pin_language_rejects_main_language_from_different_institution(): void
    {
        // GIVEN — a main language belonging to institution B, caller is from institution A
        $institutionA = Institution::factory()->create();
        $institutionB = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionA->id])->create();

        $language = ClassifierValue::factory()->language()->create();
        $mainLangOfB = InstitutionMainLanguage::create(['institution_id' => $institutionB->id, 'language_id' => $language->id]);

        $accessToken = $this->tokenForInstitutionUser($institutionUser);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-users/pinned-languages', [
                'institution_main_language_id' => $mainLangOfB->id,
            ]);

        // THEN — validation rejects the foreign ID
        $response->assertStatus(422);
    }

    public function test_unpin_language_deletes_pin_and_returns_204(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $language = ClassifierValue::factory()->language()->create();
        $mainLang = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $language->id]);

        $pin = InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLang->id,
        ]);

        $accessToken = $this->tokenForInstitutionUser($institutionUser);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-users/pinned-languages', [
                'institution_main_language_id' => $mainLang->id,
            ]);

        // THEN
        $response->assertStatus(204);

        $this->assertDatabaseMissing('institution_user_pinned_languages', ['id' => $pin->id]);
    }

    public function test_unpin_language_returns_404_when_not_pinned(): void
    {
        // GIVEN — language exists but is not pinned
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $language = ClassifierValue::factory()->language()->create();
        $mainLang = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $language->id]);

        $accessToken = $this->tokenForInstitutionUser($institutionUser);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-users/pinned-languages', [
                'institution_main_language_id' => $mainLang->id,
            ]);

        // THEN
        $response->assertStatus(404);
    }

    public function test_unpin_language_forbids_deleting_another_users_pin(): void
    {
        // GIVEN — user B has a pin; user A tries to delete it
        $institution = Institution::factory()->create();
        $institutionUserA = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();
        $institutionUserB = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $language = ClassifierValue::factory()->language()->create();
        $mainLang = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $language->id]);

        InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $institutionUserB->id,
            'institution_main_language_id' => $mainLang->id,
        ]);

        $accessToken = $this->tokenForInstitutionUser($institutionUserA);

        // WHEN — user A sends the same language ID
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-users/pinned-languages', [
                'institution_main_language_id' => $mainLang->id,
            ]);

        // THEN — user A has no pin for this language → 404 (record not found for their user ID)
        $response->assertStatus(404);
    }

    private function tokenForInstitutionUser(InstitutionUser $institutionUser): string
    {
        return AuthHelpers::generateAccessToken(
            AuthHelpers::makeTolkevaravClaimsForInstitutionUser($institutionUser)
        );
    }

    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        $institutionId = Str::orderedUuid();

        InstitutionUser::factory()
            ->count(10)
            ->setInstitution(['id' => $institutionId])
            ->create();

        // Create 10 more with different institution IDs
        InstitutionUser::factory()
            ->count(10)
            ->create();

        // Re-query to get them in the correct order
        $expectedInstitutionUsers = InstitutionUser::getModel()
            ->where('institution->id', $institutionId)
            ->orderByRaw("CONCAT(\"user\"->>'forename', \"user\"->>'surname') COLLATE \"et-EE-x-icu\" ASC")
            ->get();

        $queryParams = [];

        $this->assertListEndpoint($expectedInstitutionUsers, $institutionId, $queryParams);
    }

    private function assertListEndpoint($expectedDataset, $institutionId, $queryParams)
    {
        // GIVEN
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);
        $queryString = http_build_query($queryParams);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson("/api/institution-users?$queryString");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($expectedDataset)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJsonCount($expectedDataset->count(), 'data');
    }

    public static function constructRepresentation($obj): array
    {
        return RepresentationHelpers::createInstitutionUserRepresentation($obj);
    }
}
