<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionSetting;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Tests\AuthHelpers;
use Tests\TestCase;

class InstitutionSettingControllerTest extends TestCase
{
    private Institution $institution;

    private string $oralTranslationTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        $this->institution = Institution::factory()->create();
        $this->oralTranslationTypeId = ClassifierValue::where('type', ClassifierValueType::ProjectType)
            ->where('value', 'ORAL_TRANSLATION')
            ->firstOrFail()->id;
    }

    public function test_show_returns_no_content_when_no_settings_exist(): void
    {
        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->getJson('/api/institution/settings');

        $response->assertNoContent();
    }

    public function test_show_returns_institution_settings(): void
    {
        InstitutionSetting::create([
            'institution_id' => $this->institution->id,
            'reaction_time_minutes' => 60,
            'buffer_before_minutes' => 15,
            'buffer_after_minutes' => 10,
            'default_project_type_id' => $this->oralTranslationTypeId,
        ]);

        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->getJson('/api/institution/settings');

        $response->assertOk()
            ->assertJsonFragment([
                'reaction_time_minutes' => 60,
                'buffer_before_minutes' => 15,
                'buffer_after_minutes' => 10,
                'default_project_type_id' => $this->oralTranslationTypeId,
            ]);
    }

    public function test_store_creates_new_settings_with_partial_payload(): void
    {
        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->putJson('/api/institution/settings', [
                'buffer_before_minutes' => 30,
            ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'buffer_before_minutes' => 30,
            ]);

        $this->assertDatabaseHas('institution_settings', [
            'institution_id' => $this->institution->id,
            'buffer_before_minutes' => 30,
        ]);
    }

    public function test_store_creates_settings_with_full_payload(): void
    {
        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->putJson('/api/institution/settings', [
                'reaction_time_minutes' => 45,
                'buffer_before_minutes' => 20,
                'buffer_after_minutes' => 10,
                'default_project_type_id' => $this->oralTranslationTypeId,
            ]);

        $response->assertCreated()
            ->assertJsonFragment([
                'reaction_time_minutes' => 45,
                'buffer_before_minutes' => 20,
                'buffer_after_minutes' => 10,
                'default_project_type_id' => $this->oralTranslationTypeId,
            ]);
    }

    public function test_store_updates_only_provided_fields(): void
    {
        InstitutionSetting::create([
            'institution_id' => $this->institution->id,
            'reaction_time_minutes' => 30,
            'buffer_before_minutes' => 10,
            'buffer_after_minutes' => 5,
            'default_project_type_id' => $this->oralTranslationTypeId,
        ]);

        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->putJson('/api/institution/settings', [
                'buffer_before_minutes' => 99,
            ]);

        $response->assertOk()
            ->assertJsonFragment([
                'reaction_time_minutes' => 30,
                'buffer_before_minutes' => 99,
                'buffer_after_minutes' => 5,
                'default_project_type_id' => $this->oralTranslationTypeId,
            ]);
    }

    public function test_store_returns_forbidden_without_privilege(): void
    {
        $accessToken = AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $this->institution->id],
            'privileges' => [],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson('/api/institution/settings', [
                'buffer_before_minutes' => 30,
            ]);

        $response->assertForbidden();
    }

    public function test_store_validates_project_type_must_be_calendar_supported(): void
    {
        $languageId = ClassifierValue::where('type', ClassifierValueType::Language)
            ->firstOrFail()->id;

        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->putJson('/api/institution/settings', [
                'default_project_type_id' => $languageId,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('default_project_type_id');
    }

    public function test_store_validates_negative_buffer_rejected(): void
    {
        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->putJson('/api/institution/settings', [
                'buffer_before_minutes' => -5,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('buffer_before_minutes');
    }

    public function test_settings_scoped_to_own_institution(): void
    {
        $otherInstitution = Institution::factory()->create();
        InstitutionSetting::create([
            'institution_id' => $otherInstitution->id,
            'reaction_time_minutes' => 999,
            'buffer_before_minutes' => 999,
            'buffer_after_minutes' => 999,
            'default_project_type_id' => $this->oralTranslationTypeId,
        ]);

        $response = $this->prepareAuthorizedRequest($this->actAsInstitutionEditor())
            ->getJson('/api/institution/settings');

        $response->assertNoContent();
    }

    private function actAsInstitutionEditor(): string
    {
        return AuthHelpers::generateAccessToken([
            'selectedInstitution' => ['id' => $this->institution->id, 'name' => $this->institution->name],
            'privileges' => [PrivilegeKey::EditInstitution->value],
        ]);
    }
}
