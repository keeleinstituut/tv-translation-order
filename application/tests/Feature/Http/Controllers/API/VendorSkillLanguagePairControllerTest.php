<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorSkillLanguagePair;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class VendorSkillLanguagePairControllerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_scoped(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $ownPairs = VendorSkillLanguagePair::factory(3)->create(['vendor_id' => $vendor->id]);

        // Pairs for another institution — must NOT appear
        VendorSkillLanguagePair::factory(4)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['VIEW_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/vendor-skill-language-pairs');

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount($ownPairs->count(), 'data');
    }

    // -------------------------------------------------------------------------
    // bulkStore
    // -------------------------------------------------------------------------

    public function test_bulk_create_happy_path(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => [
                [
                    'vendor_id' => $vendor->id,
                    'skill_id' => $skillId,
                    'src_lang_classifier_value_id' => $src->id,
                    'dst_lang_classifier_value_id' => $dst->id,
                ],
            ],
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/vendor-skill-language-pairs/bulk', $payload);

        // THEN
        $response->assertStatus(200);

        $saved = VendorSkillLanguagePair::query()
            ->where('vendor_id', $vendor->id)
            ->where('skill_id', $skillId)
            ->where('src_lang_classifier_value_id', $src->id)
            ->where('dst_lang_classifier_value_id', $dst->id)
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill', 'vendor')
            ->first();

        $this->assertNotNull($saved);

        $response->assertExactJson([
            'data' => [RepresentationHelpers::createVendorSkillLanguagePairRepresentation($saved)],
        ]);
    }

    public function test_bulk_create_uniqueness_conflict(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $existing = VendorSkillLanguagePair::factory()->create(['vendor_id' => $vendor->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => [
                [
                    'vendor_id' => $existing->vendor_id,
                    'skill_id' => $existing->skill_id,
                    'src_lang_classifier_value_id' => $existing->src_lang_classifier_value_id,
                    'dst_lang_classifier_value_id' => $existing->dst_lang_classifier_value_id,
                ],
            ],
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/vendor-skill-language-pairs/bulk', $payload);

        // THEN
        $response->assertStatus(422);
    }

    public function test_bulk_create_within_batch_uniqueness(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $itemTemplate = [
            'vendor_id' => $vendor->id,
            'skill_id' => $skillId,
            'src_lang_classifier_value_id' => $src->id,
            'dst_lang_classifier_value_id' => $dst->id,
        ];

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // WHEN — two identical tuples in the same batch
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/vendor-skill-language-pairs/bulk', ['data' => [$itemTemplate, $itemTemplate]]);

        // THEN
        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // bulkDestroy
    // -------------------------------------------------------------------------

    public function test_bulk_destroy_happy_path(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $pairs = VendorSkillLanguagePair::factory(2)->create(['vendor_id' => $vendor->id]);
        $pairIds = $pairs->pluck('id');

        // Pairs for another institution — must stay untouched
        VendorSkillLanguagePair::factory(3)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $queryString = $pairIds->map(fn ($id) => 'id[]='.$id)->implode('&');

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/vendor-skill-language-pairs/bulk?'.$queryString);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        foreach ($pairIds as $id) {
            $this->assertSoftDeleted('vendor_skill_language_pairs', ['id' => $id]);
        }

        // Other institution's pairs untouched
        $this->assertEquals(3, VendorSkillLanguagePair::query()
            ->whereNotIn('vendor_id', [$vendor->id])
            ->count());
    }

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    public function test_403_without_view_privilege(): void
    {
        $institutionId = Str::orderedUuid();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/vendor-skill-language-pairs')
            ->assertStatus(403);
    }

    public function test_403_bulk_operations_without_edit_privilege(): void
    {
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
        $pair = VendorSkillLanguagePair::factory()->create(['vendor_id' => $vendor->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['VIEW_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // bulkStore
        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/vendor-skill-language-pairs/bulk', ['data' => [
                [
                    'vendor_id' => $vendor->id,
                    'skill_id' => $pair->skill_id,
                    'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                    'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                ],
            ]])
            ->assertStatus(403);

        // bulkDestroy
        $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/vendor-skill-language-pairs/bulk?id[]='.$pair->id)
            ->assertStatus(403);
    }

    public function test_403_cross_institution_bulk_destroy(): void
    {
        // GIVEN — pair belongs to another institution
        $otherPair = VendorSkillLanguagePair::factory()->create();

        $attackerInstitutionId = Str::orderedUuid();
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $attackerInstitutionId],
        ]);

        // WHEN — attacker tries to delete the other institution's row
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/vendor-skill-language-pairs/bulk?id[]='.$otherPair->id);

        // THEN — policy scope means the row won't load; returns 200 with empty data
        $response->assertStatus(200)->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('vendor_skill_language_pairs', ['id' => $otherPair->id, 'deleted_at' => null]);
    }
}
