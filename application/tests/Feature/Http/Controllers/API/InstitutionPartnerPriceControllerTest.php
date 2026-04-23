<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionPartner;
use App\Models\InstitutionPartnerPrice;
use App\Models\Skill;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class InstitutionPartnerPriceControllerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_scoped(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $ownPrices = InstitutionPartnerPrice::factory(3)->create([
            'institution_partner_id' => $partner->id,
        ]);

        // Prices for another institution's partner — must NOT appear
        InstitutionPartnerPrice::factory(2)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/institution-partner-prices');

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount($ownPrices->count(), 'data');
    }

    public function test_index_filters_by_institution_partner(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution1 = Institution::factory()->create();
        $partnerInstitution2 = Institution::factory()->create();

        $partner1 = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution1->id,
        ]);
        $partner2 = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution2->id,
        ]);

        InstitutionPartnerPrice::factory(2)->create(['institution_partner_id' => $partner1->id]);
        InstitutionPartnerPrice::factory(3)->create(['institution_partner_id' => $partner2->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institution-partner-prices?institution_partner_id[]='.$partner1->id);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_happy_path(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $payload = [
            'institution_partner_id' => $partner->id,
            'skill_id' => $skillId,
            'src_lang_classifier_value_id' => $src->id,
            'dst_lang_classifier_value_id' => $dst->id,
            'character_fee' => 1.500,
            'word_fee' => 2.000,
            'page_fee' => 3.000,
            'minute_fee' => 4.000,
            'hour_fee' => 5.000,
            'minimal_fee' => 0.500,
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partner-prices', $payload);

        // THEN
        $response->assertStatus(201);

        $saved = InstitutionPartnerPrice::query()
            ->where('institution_partner_id', $partner->id)
            ->where('skill_id', $skillId)
            ->where('src_lang_classifier_value_id', $src->id)
            ->where('dst_lang_classifier_value_id', $dst->id)
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill')
            ->first();

        $this->assertNotNull($saved);

        $response->assertExactJson([
            'data' => RepresentationHelpers::createInstitutionPartnerPriceRepresentation($saved),
        ]);
    }

    // -------------------------------------------------------------------------
    // bulkStore
    // -------------------------------------------------------------------------

    public function test_bulk_create_happy_path(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $payload = [
            'data' => [
                [
                    'institution_partner_id' => $partner->id,
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
            ->postJson('/api/institution-partner-prices/bulk', $payload);

        // THEN
        $response->assertStatus(201);

        $saved = InstitutionPartnerPrice::query()
            ->where('institution_partner_id', $partner->id)
            ->where('skill_id', $skillId)
            ->where('src_lang_classifier_value_id', $src->id)
            ->where('dst_lang_classifier_value_id', $dst->id)
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill')
            ->first();

        $this->assertNotNull($saved);

        $response->assertExactJson([
            'data' => [RepresentationHelpers::createInstitutionPartnerPriceRepresentation($saved)],
        ]);
    }

    public function test_bulk_create_uniqueness_conflict_with_existing_row(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $existing = InstitutionPartnerPrice::factory()->create(['institution_partner_id' => $partner->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $payload = [
            'data' => [
                [
                    'institution_partner_id' => $partner->id,
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
            ->postJson('/api/institution-partner-prices/bulk', $payload);

        // THEN
        $response->assertStatus(422);
    }

    public function test_bulk_create_within_batch_uniqueness(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();
        $skillId = Skill::query()->inRandomOrder()->first()->id;

        $itemTemplate = [
            'institution_partner_id' => $partner->id,
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
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN — two identical tuples in the same batch
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partner-prices/bulk', ['data' => [$itemTemplate, $itemTemplate]]);

        // THEN
        $response->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // bulkUpdate
    // -------------------------------------------------------------------------

    public function test_bulk_update_happy_path(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $prices = InstitutionPartnerPrice::factory(2)->create(['institution_partner_id' => $partner->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
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
            ->putJson('/api/institution-partner-prices/bulk', $payload);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $updated = InstitutionPartnerPrice::query()
            ->whereIn('id', $prices->pluck('id'))
            ->with('sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill')
            ->orderBy('created_at', 'asc')
            ->get();

        $response->assertExactJson([
            'data' => $updated->map(fn ($p) => RepresentationHelpers::createInstitutionPartnerPriceRepresentation($p))->toArray(),
        ]);
    }

    // -------------------------------------------------------------------------
    // bulkDestroy
    // -------------------------------------------------------------------------

    public function test_bulk_destroy_happy_path(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $prices = InstitutionPartnerPrice::factory(2)->create(['institution_partner_id' => $partner->id]);
        $priceIds = $prices->pluck('id');

        // Prices for another institution's partner — must stay untouched
        InstitutionPartnerPrice::factory(2)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $queryString = $priceIds->map(fn ($id) => 'id[]='.$id)->implode('&');

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-partner-prices/bulk?'.$queryString);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        foreach ($priceIds as $id) {
            $this->assertSoftDeleted('institution_partner_prices', ['id' => $id]);
        }
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
            ->getJson('/api/institution-partner-prices')
            ->assertStatus(403);
    }

    public function test_403_bulk_operations_without_manage_privilege(): void
    {
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $price = InstitutionPartnerPrice::factory()->create(['institution_partner_id' => $partner->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $src = ClassifierValue::factory()->language()->create();
        $dst = ClassifierValue::factory()->language()->create();

        // bulkStore
        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partner-prices/bulk', ['data' => [
                [
                    'institution_partner_id' => $partner->id,
                    'skill_id' => $price->skill_id,
                    'src_lang_classifier_value_id' => $src->id,
                    'dst_lang_classifier_value_id' => $dst->id,
                    'character_fee' => 1.0, 'word_fee' => 1.0, 'page_fee' => 1.0,
                    'minute_fee' => 1.0, 'hour_fee' => 1.0, 'minimal_fee' => 1.0,
                ],
            ]])
            ->assertStatus(403);

        // bulkUpdate
        $this->prepareAuthorizedRequest($accessToken)
            ->putJson('/api/institution-partner-prices/bulk', ['data' => [
                ['id' => $price->id, 'character_fee' => 2.0],
            ]])
            ->assertStatus(403);

        // bulkDestroy
        $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-partner-prices/bulk?id[]='.$price->id)
            ->assertStatus(403);
    }

    public function test_403_cross_institution_bulk_destroy(): void
    {
        // GIVEN — price belongs to another institution's partner
        $otherPrice = InstitutionPartnerPrice::factory()->create();

        $attackerInstitutionId = Str::orderedUuid();
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $attackerInstitutionId],
        ]);

        // WHEN — attacker tries to delete the other institution's price
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-partner-prices/bulk?id[]='.$otherPrice->id);

        // THEN — row not visible via scope; untouched
        $this->assertDatabaseHas('institution_partner_prices', ['id' => $otherPrice->id, 'deleted_at' => null]);
    }
}
