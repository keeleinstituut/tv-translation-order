<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\InstitutionType;
use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionMainLanguage;
use App\Models\InstitutionPartner;
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

    // -------------------------------------------------------------------------
    // index — list institutions (partner-creation lookup)
    // -------------------------------------------------------------------------

    public function test_index_returns_paginated_institutions_with_no_filters(): void
    {
        // GIVEN — caller plus three other institutions
        $caller = Institution::factory()->create();
        Institution::factory(3)->create();

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/institutions');

        // THEN — caller's institution is included by default
        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'short_name', 'email', 'phone', 'logo_url', 'type']]])
            ->assertJsonFragment(['id' => $caller->id]);
        $this->assertGreaterThanOrEqual(4, count($response->json('data')));
    }

    public function test_index_name_filter_narrows_results_case_insensitive(): void
    {
        // GIVEN
        $caller = Institution::factory()->create();
        $matching = Institution::factory()->create(['name' => 'Acme Translations']);
        $other = Institution::factory()->create(['name' => 'Globex Corporation']);

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN — lowercase fragment of name
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?name=acme');

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $matching->id])
            ->assertJsonMissing(['id' => $other->id]);
    }

    public function test_index_type_filter_narrows_results(): void
    {
        // GIVEN
        $caller = Institution::factory()->create();
        $agency = Institution::factory()->create(['type' => InstitutionType::TranslationAgency]);
        $regular = Institution::factory()->create(['type' => InstitutionType::Institution]);

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?type=' . InstitutionType::TranslationAgency->value);

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $agency->id])
            ->assertJsonMissing(['id' => $regular->id]);
    }

    public function test_index_not_partner_filter_excludes_self_and_already_partnered(): void
    {
        // GIVEN
        $caller = Institution::factory()->create();
        $alreadyPartner = Institution::factory()->create();
        $candidate = Institution::factory()->create();

        InstitutionPartner::factory()->create([
            'institution_id' => $caller->id,
            'partner_institution_id' => $alreadyPartner->id,
        ]);

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?not_partner_of_current_institution=1');

        // THEN — caller and already-partnered institution excluded; candidate present
        $response->assertOk()
            ->assertJsonFragment(['id' => $candidate->id])
            ->assertJsonMissing(['id' => $caller->id])
            ->assertJsonMissing(['id' => $alreadyPartner->id]);
    }

    public function test_index_not_partner_filter_ignores_soft_deleted_partner_rows(): void
    {
        // GIVEN — partner row exists but is soft-deleted, so the institution should reappear as a candidate
        $caller = Institution::factory()->create();
        $previouslyPartner = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $caller->id,
            'partner_institution_id' => $previouslyPartner->id,
        ]);
        $partner->delete();

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?not_partner_of_current_institution=1');

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $previouslyPartner->id]);
    }

    public function test_index_has_current_as_partner_filter_returns_only_owner_institutions(): void
    {
        // GIVEN
        $caller = Institution::factory()->create();
        $owner = Institution::factory()->create();
        $reverseOnly = Institution::factory()->create();
        $unrelated = Institution::factory()->create();

        // owner registered the caller as its partner → owner can send offers to the caller
        InstitutionPartner::factory()->create([
            'institution_id' => $owner->id,
            'partner_institution_id' => $caller->id,
        ]);
        // caller registered reverseOnly as its partner (opposite direction) → must NOT match
        InstitutionPartner::factory()->create([
            'institution_id' => $caller->id,
            'partner_institution_id' => $reverseOnly->id,
        ]);

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?has_current_institution_as_partner=1');

        // THEN
        $response->assertOk()
            ->assertJsonFragment(['id' => $owner->id])
            ->assertJsonMissing(['id' => $reverseOnly->id])
            ->assertJsonMissing(['id' => $unrelated->id])
            ->assertJsonMissing(['id' => $caller->id]);
    }

    public function test_index_has_current_as_partner_filter_ignores_soft_deleted_partner_rows(): void
    {
        // GIVEN — partner row exists but is soft-deleted, so the owner should not appear
        $caller = Institution::factory()->create();
        $owner = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $owner->id,
            'partner_institution_id' => $caller->id,
        ]);
        $partner->delete();

        $accessToken = $this->tokenWithManageExternalPartner($caller->id);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?has_current_institution_as_partner=1');

        // THEN
        $response->assertOk()
            ->assertJsonMissing(['id' => $owner->id]);
    }

    public function test_index_has_current_as_partner_filter_allows_offer_viewer_without_manage_partner(): void
    {
        // GIVEN — caller can respond to outsource offers but cannot manage external partners
        $caller = Institution::factory()->create();
        $owner = Institution::factory()->create();

        InstitutionPartner::factory()->create([
            'institution_id' => $owner->id,
            'partner_institution_id' => $caller->id,
        ]);

        $accessToken = $this->tokenWithRespondOutsourceRequest($caller->id);

        // WHEN — filtered call is authorized
        $filtered = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions?has_current_institution_as_partner=1');

        // THEN
        $filtered->assertOk()->assertJsonFragment(['id' => $owner->id]);

        // WHEN — the unfiltered global list still requires ManageExternalPartner
        $unfiltered = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institutions');

        // THEN
        $unfiltered->assertForbidden();
    }

    public function test_index_returns_403_without_manage_external_partner_privilege(): void
    {
        // GIVEN — caller has an unrelated privilege only
        $caller = Institution::factory()->create();
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $caller->id, 'name' => 'Test'],
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/institutions');

        // THEN
        $response->assertForbidden();
    }

    public function test_index_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/institutions');
        $response->assertUnauthorized();
    }

    private function tokenWithEditInstitution(string $institutionId, string $institutionName = 'Test Institution'): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institutionId, 'name' => $institutionName],
            'privileges' => [PrivilegeKey::EditInstitution->value],
        ]);
    }

    private function tokenWithManageExternalPartner(string $institutionId, string $institutionName = 'Test Institution'): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institutionId, 'name' => $institutionName],
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
        ]);
    }

    private function tokenWithRespondOutsourceRequest(string $institutionId, string $institutionName = 'Test Institution'): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $institutionId, 'name' => $institutionName],
            'privileges' => [PrivilegeKey::RespondOutsourceRequest->value],
        ]);
    }
}
