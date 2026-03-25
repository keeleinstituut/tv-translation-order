<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\InstitutionUserPinnedLanguage;
use Tests\AuthHelpers;
use Tests\TestCase;

class InstitutionControllerTest extends TestCase
{
    public function test_index_returns_main_languages_for_institution(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $otherInstitution = Institution::factory()->create();
        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();
        $languageC = ClassifierValue::factory()->language()->create();

        InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageA->id]);
        InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageB->id]);
        InstitutionMainLanguage::create(['institution_id' => $otherInstitution->id, 'language_id' => $languageC->id]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions/main-languages');

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'institution_id', 'language_id', 'language']]])
            ->assertJsonFragment(['institution_id' => $institution->id, 'language_id' => $languageA->id])
            ->assertJsonFragment(['institution_id' => $institution->id, 'language_id' => $languageB->id]);
    }

    public function test_index_requires_edit_institution_privilege(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions/main-languages');

        // THEN
        $response->assertStatus(403);
    }

    public function test_sync_main_languages_returns_updated_collection(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$languageA->id, $languageB->id],
            ]);

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'institution_id', 'language_id', 'language'],
                ],
            ])
            ->assertJsonFragment(['institution_id' => $institution->id, 'language_id' => $languageA->id])
            ->assertJsonFragment(['institution_id' => $institution->id, 'language_id' => $languageB->id]);
    }

    public function test_sync_main_languages_adds_new_language_to_existing_set(): void
    {
        // GIVEN — institution already has languageA
        $institution = Institution::factory()->create();
        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $languageA->id,
        ]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN — sync with both A and B
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$languageA->id, $languageB->id],
            ]);

        // THEN — both languages present
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_sync_main_languages_removes_language_no_longer_in_set(): void
    {
        // GIVEN — institution has languageA and languageB
        $institution = Institution::factory()->create();
        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageA->id]);
        InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageB->id]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN — sync with only languageA
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$languageA->id],
            ]);

        // THEN — only languageA remains
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson(['data' => [['language_id' => $languageA->id]]]);

        $this->assertDatabaseMissing('institution_main_languages', [
            'institution_id' => $institution->id,
            'language_id' => $languageB->id,
        ]);
    }

    public function test_sync_main_languages_preserves_existing_row_id_for_unchanged_language(): void
    {
        // GIVEN — languageA is already registered; its row id must survive the sync
        $institution = Institution::factory()->create();
        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        $existingRow = InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $languageA->id,
        ]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN — sync keeping languageA and adding languageB
        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$languageA->id, $languageB->id],
            ]);

        // THEN — the original InstitutionMainLanguage row for languageA still exists with the same id
        $this->assertDatabaseHas('institution_main_languages', [
            'id' => $existingRow->id,
            'institution_id' => $institution->id,
            'language_id' => $languageA->id,
        ]);
    }

    public function test_sync_main_languages_preserves_user_pins_for_unchanged_languages(): void
    {
        // GIVEN — user has languageA pinned; sync keeps languageA but removes languageB
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        $mainLangA = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageA->id]);
        $mainLangB = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageB->id]);

        // User has pinned languageA
        InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLangA->id,
        ]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN — sync keeping only languageA (removing languageB)
        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$languageA->id],
            ]);

        // THEN — the user pin for languageA is preserved
        $this->assertDatabaseHas('institution_user_pinned_languages', [
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLangA->id,
        ]);
    }

    public function test_sync_main_languages_cascades_removal_to_user_pins(): void
    {
        // GIVEN — user has languageB pinned; sync removes languageB
        $institution = Institution::factory()->create();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institution->id])->create();

        $languageA = ClassifierValue::factory()->language()->create();
        $languageB = ClassifierValue::factory()->language()->create();

        $mainLangA = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageA->id]);
        $mainLangB = InstitutionMainLanguage::create(['institution_id' => $institution->id, 'language_id' => $languageB->id]);

        // User has pinned languageB (which will be removed)
        $pinnedB = InstitutionUserPinnedLanguage::create([
            'institution_user_id' => $institutionUser->id,
            'institution_main_language_id' => $mainLangB->id,
        ]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN — sync keeping only languageA
        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$languageA->id],
            ]);

        // THEN — user pin for languageB is gone (cascade delete)
        $this->assertDatabaseMissing('institution_user_pinned_languages', [
            'id' => $pinnedB->id,
        ]);
    }

    public function test_sync_main_languages_clears_all_languages_when_empty_array_given(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $language = ClassifierValue::factory()->language()->create();

        InstitutionMainLanguage::create([
            'institution_id' => $institution->id,
            'language_id' => $language->id,
        ]);

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', ['languages' => []]);

        // THEN
        $response->assertStatus(200)->assertJsonCount(0, 'data');

        $this->assertDatabaseEmpty('institution_main_languages');
    }

    public function test_sync_main_languages_requires_edit_institution_privilege(): void
    {
        // GIVEN — token without the required privilege
        $institution = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institution->id, 'name' => $institution->name],
            'privileges' => [],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', ['languages' => []]);

        // THEN
        $response->assertStatus(403);
    }

    public function test_sync_main_languages_rejects_non_language_classifier_value(): void
    {
        // GIVEN — a classifier value that is NOT of type Language
        $institution = Institution::factory()->create();
        $nonLanguageCV = ClassifierValue::factory()->withType(\App\Enums\ClassifierValueType::TranslationDomain)->create();

        $accessToken = $this->tokenWithEditInstitution($institution->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institutions/main-languages', [
                'languages' => [$nonLanguageCV->id],
            ]);

        // THEN
        $response->assertStatus(422);
    }

    private function tokenWithEditInstitution(string $institutionId, string $institutionName = 'Test Institution'): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institutionId, 'name' => $institutionName],
            'privileges' => [PrivilegeKey::EditInstitution->value],
        ]);
    }
}
