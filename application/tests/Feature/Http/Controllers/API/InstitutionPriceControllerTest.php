<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionPrice;
use App\Models\Skill;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class InstitutionPriceControllerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_scoped(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $ownPrices = InstitutionPrice::factory(3)->create(['institution_id' => $institution->id]);

        // Prices for another institution — must NOT appear
        InstitutionPrice::factory(4)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewInstitutionPricelist->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/institution-prices');

        // THEN
        $saved = InstitutionPrice::query()
            ->where('institution_id', $institution->id)
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill')
            ->orderBy('created_at', 'desc')
            ->get();

        $this->assertCount($ownPrices->count(), $saved);
        $response
            ->assertStatus(200)
            ->assertJsonCount($ownPrices->count(), 'data');
    }

    public function test_index_filters_by_skill(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);
        $targetSkillId = Skill::query()->inRandomOrder()->first()->id;
        $otherSkillId = Skill::query()->where('id', '!=', $targetSkillId)->value('id');
        if ($otherSkillId === null) {
            $this->markTestSkipped('This test requires at least two skills.');
        }

        InstitutionPrice::factory(2)->create(['institution_id' => $institutionId, 'skill_id' => $targetSkillId]);
        InstitutionPrice::factory(3)->create(['institution_id' => $institutionId, 'skill_id' => $otherSkillId]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewInstitutionPricelist->value],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institution-prices?skill_id[]='.$targetSkillId);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_lang_pair(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);
        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillIds = Skill::query()->pluck('id');
        if ($skillIds->count() < 2) {
            $this->markTestSkipped('This test requires at least two skills.');
        }

        InstitutionPrice::factory(2)
            ->sequence(fn ($sequence) => ['skill_id' => $skillIds[$sequence->index]])
            ->create([
                'institution_id' => $institutionId,
                'src_lang_classifier_value_id' => $src->id,
                'dst_lang_classifier_value_id' => $dst->id,
            ]);
        InstitutionPrice::factory(3)->create(['institution_id' => $institutionId]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewInstitutionPricelist->value],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson("/api/institution-prices?src_lang_classifier_value_id[]={$src->id}&dst_lang_classifier_value_id[]={$dst->id}");

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    // -------------------------------------------------------------------------
    // bulkStore
    // -------------------------------------------------------------------------

    public function test_bulk_create_happy_path(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => [
                [
                    'skill_id' => $skillId,
                    'src_lang_classifier_value_id' => $src->id,
                    'dst_lang_classifier_value_id' => $dst->id,
                    'character_fee' => 1.500,
                    'word_fee' => 2.000,
                    'page_fee' => 3.000,
                    'minute_fee' => 4.000,
                    'hour_fee' => 5.000,
                    'minimal_fee' => 0.500,
                ],
            ],
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-prices/bulk', $payload);

        // THEN
        $response->assertStatus(200);

        $saved = InstitutionPrice::query()
            ->where('institution_id', $institutionId)
            ->where('skill_id', $skillId)
            ->where('src_lang_classifier_value_id', $src->id)
            ->where('dst_lang_classifier_value_id', $dst->id)
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill')
            ->first();

        $this->assertNotNull($saved);
        $this->assertEquals($institutionId, $saved->institution_id);

        $response->assertExactJson([
            'data' => [RepresentationHelpers::createInstitutionPriceRepresentation($saved)],
        ]);
    }

    public function test_bulk_create_uniqueness_conflict_with_existing_row(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);
        $existing = InstitutionPrice::factory()->create(['institution_id' => $institutionId]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => [
                [
                    'skill_id' => $existing->skill_id,
                    'src_lang_classifier_value_id' => $existing->src_lang_classifier_value_id,
                    'dst_lang_classifier_value_id' => $existing->dst_lang_classifier_value_id,
                    'character_fee' => 1.0,
                    'word_fee' => 1.0,
                    'page_fee' => 1.0,
                    'minute_fee' => 1.0,
                    'hour_fee' => 1.0,
                    'minimal_fee' => 1.0,
                ],
            ],
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-prices/bulk', $payload);

        // THEN
        $response->assertStatus(422);
    }

    public function test_bulk_create_within_batch_uniqueness(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $itemTemplate = [
            'skill_id' => $skillId,
            'src_lang_classifier_value_id' => $src->id,
            'dst_lang_classifier_value_id' => $dst->id,
            'character_fee' => 1.0,
            'word_fee' => 1.0,
            'page_fee' => 1.0,
            'minute_fee' => 1.0,
            'hour_fee' => 1.0,
            'minimal_fee' => 1.0,
        ];

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // WHEN — two identical (src, dst, skill) tuples in the same batch
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-prices/bulk', ['data' => [$itemTemplate, $itemTemplate]]);

        // THEN
        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // bulkUpdate
    // -------------------------------------------------------------------------

    public function test_bulk_update_happy_path(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);
        $prices = InstitutionPrice::factory(2)->create(['institution_id' => $institutionId]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => $prices->map(fn ($p) => [
                'id' => $p->id,
                'character_fee' => 99.999,
                'word_fee' => 88.888,
                'page_fee' => 77.777,
                'minute_fee' => 66.666,
                'hour_fee' => 55.555,
                'minimal_fee' => 11.111,
            ])->toArray(),
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson('/api/institution-prices/bulk', $payload);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $updated = InstitutionPrice::query()
            ->whereIn('id', $prices->pluck('id'))
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill')
            ->orderBy('created_at', 'asc')
            ->get();

        $response->assertExactJson([
            'data' => $updated->map(fn ($p) => RepresentationHelpers::createInstitutionPriceRepresentation($p))->toArray(),
        ]);
    }

    // -------------------------------------------------------------------------
    // bulkDestroy
    // -------------------------------------------------------------------------

    public function test_bulk_destroy_happy_path(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);
        $prices = InstitutionPrice::factory(2)->create(['institution_id' => $institutionId]);
        $priceIds = $prices->pluck('id');

        // Prices for another institution — must stay untouched
        InstitutionPrice::factory(3)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $queryString = $priceIds->map(fn ($id) => 'id[]='.$id)->implode('&');

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-prices/bulk?'.$queryString);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        // Rows must be soft-deleted
        foreach ($priceIds as $id) {
            $this->assertSoftDeleted('institution_prices', ['id' => $id]);
        }

        // Other institution's prices untouched
        $this->assertEquals(3, InstitutionPrice::query()->whereNotIn('institution_id', [$institutionId])->count());
    }

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    public function test_403_index_without_view_privilege(): void
    {
        $institutionId = Str::orderedUuid();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institution-prices')
            ->assertStatus(403);
    }

    public function test_403_bulk_operations_without_edit_privilege(): void
    {
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);
        $prices = InstitutionPrice::factory(1)->create(['institution_id' => $institutionId]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewInstitutionPricelist->value],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // bulkStore
        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-prices/bulk', ['data' => [
                [
                    'skill_id' => $prices->first()->skill_id,
                    'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                    'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                    'character_fee' => 1.0, 'word_fee' => 1.0, 'page_fee' => 1.0,
                    'minute_fee' => 1.0, 'hour_fee' => 1.0, 'minimal_fee' => 1.0,
                ],
            ]])
            ->assertStatus(403);

        // bulkUpdate
        $this->prepareAuthorizedRequest($accessToken)
            ->putJson('/api/institution-prices/bulk', ['data' => [
                ['id' => $prices->first()->id, 'character_fee' => 2.0],
            ]])
            ->assertStatus(403);

        // bulkDestroy
        $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-prices/bulk?id[]='.$prices->first()->id)
            ->assertStatus(403);
    }

    public function test_403_cross_institution_bulk_update(): void
    {
        // GIVEN — price belongs to another institution
        $otherPrice = InstitutionPrice::factory()->create();

        $attackerInstitutionId = Str::orderedUuid();
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $attackerInstitutionId],
        ]);

        // WHEN — attacker tries to update the other institution's row
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson('/api/institution-prices/bulk', ['data' => [
                ['id' => $otherPrice->id, 'character_fee' => 99.0],
            ]]);

        // THEN — 422 because id does not exist for attacker's institution (Rule::exists scoped)
        $response->assertStatus(422);
    }

    public function test_403_cross_institution_bulk_destroy(): void
    {
        // GIVEN — price belongs to another institution
        $otherPrice = InstitutionPrice::factory()->create();

        $attackerInstitutionId = Str::orderedUuid();
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_INSTITUTION_PRICELIST'],
            'selectedInstitution' => ['id' => $attackerInstitutionId],
        ]);

        // WHEN — attacker tries to delete the other institution's row
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-prices/bulk?id[]='.$otherPrice->id);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id.0']);
        $this->assertDatabaseHas('institution_prices', ['id' => $otherPrice->id, 'deleted_at' => null]);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    public static function constructRepresentation(InstitutionPrice $obj): array
    {
        return RepresentationHelpers::createInstitutionPriceRepresentation($obj);
    }
}
