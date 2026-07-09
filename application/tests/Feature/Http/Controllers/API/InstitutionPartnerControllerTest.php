<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionPartner;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class InstitutionPartnerControllerTest extends TestCase
{
    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_scoped(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitutions = Institution::factory(3)->create();

        $ownPartners = $partnerInstitutions->map(fn ($pi) => InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $pi->id,
        ]));

        // Partners of another institution — must NOT appear
        InstitutionPartner::factory(2)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/institution-partners');

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount($ownPartners->count(), 'data');
    }

    public function test_index_filters_by_q(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();

        $matchingPartner = Institution::factory()->create([
            'name' => 'Unique Translation Agency',
            'email' => 'contact@unique-agency.com',
            'phone' => '+3725551234',
            'short_name' => 'UTA',
        ]);

        $nonMatchingPartner = Institution::factory()->create([
            'name' => 'Other Company',
            'email' => 'info@other.com',
            'phone' => '+3729999999',
            'short_name' => 'OC',
        ]);

        $expectedPartner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $matchingPartner->id,
        ]);

        InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $nonMatchingPartner->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN — search by partial name match
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/institution-partners?q=Unique');

        // THEN
        $response
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expectedPartner->id);
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_happy_path(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $payload = [
            'partner_institution_id' => $partnerInstitution->id,
            'discount_percentage_101' => 50.00,
            'discount_percentage_repetitions' => 25.50,
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partners', $payload);

        // THEN
        $response->assertStatus(201);

        $saved = InstitutionPartner::query()
            ->where('institution_id', $institution->id)
            ->where('partner_institution_id', $partnerInstitution->id)
            ->first();

        $this->assertNotNull($saved);
        $this->assertEquals($institution->id, $saved->institution_id);
        $this->assertEquals(50.00, $saved->discount_percentage_101);
        $this->assertEquals(25.50, $saved->discount_percentage_repetitions);

        $response->assertExactJson([
            'data' => RepresentationHelpers::createInstitutionPartnerRepresentation($saved),
        ]);
    }

    public function test_store_self_partner_422(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        Institution::factory()->create(['id' => $institutionId]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        // WHEN — partner_institution_id same as own institution
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partners', ['partner_institution_id' => $institutionId]);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_institution_id']);
    }

    public function test_store_duplicate_422(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN — same pair again
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partners', ['partner_institution_id' => $partnerInstitution->id]);

        // THEN
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['partner_institution_id']);
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_happy_path(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
            'discount_percentage_101' => null,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/institution-partners/{$partner->id}", [
                'discount_percentage_101' => 75.00,
                'discount_percentage_100' => 50.00,
            ]);

        // THEN
        $response->assertStatus(200);

        $updated = $partner->fresh();
        $this->assertEquals(75.00, $updated->discount_percentage_101);
        $this->assertEquals(50.00, $updated->discount_percentage_100);
        $this->assertEquals($partnerInstitution->id, $updated->partner_institution_id);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_soft_deletes(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $partner = InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution->id,
        ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson("/api/institution-partners/{$partner->id}");

        // THEN
        $response->assertStatus(200);
        $this->assertSoftDeleted('institution_partners', ['id' => $partner->id]);

        // Re-partnering (new row) is allowed after soft-delete
        $response2 = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partners', ['partner_institution_id' => $partnerInstitution->id]);

        $response2->assertStatus(201);
    }

    // -------------------------------------------------------------------------
    // bulkCreate
    // -------------------------------------------------------------------------

    public function test_bulk_create(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitution1 = Institution::factory()->create();
        $partnerInstitution2 = Institution::factory()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $payload = [
            'data' => [
                ['partner_institution_id' => $partnerInstitution1->id, 'discount_percentage_101' => 10.00],
                ['partner_institution_id' => $partnerInstitution2->id],
            ],
        ];

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partners/bulk', $payload);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('institution_partners', [
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution1->id,
        ]);
        $this->assertDatabaseHas('institution_partners', [
            'institution_id' => $institution->id,
            'partner_institution_id' => $partnerInstitution2->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // bulkDestroy
    // -------------------------------------------------------------------------

    public function test_bulk_destroy(): void
    {
        // GIVEN
        $institution = Institution::factory()->create();
        $partnerInstitutions = Institution::factory(2)->create();

        $partners = $partnerInstitutions->map(fn ($pi) => InstitutionPartner::factory()->create([
            'institution_id' => $institution->id,
            'partner_institution_id' => $pi->id,
        ]));

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->deleteJson('/api/institution-partners/bulk', ['id' => $partners->pluck('id')->all()]);

        // THEN
        $response->assertStatus(200)->assertJsonCount(2, 'data');

        $partners->each(fn ($partner) => $this->assertSoftDeleted('institution_partners', ['id' => $partner->id]));
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
            ->getJson('/api/institution-partners')
            ->assertStatus(403);
    }

    public function test_403_write_without_manage_privilege(): void
    {
        $institution = Institution::factory()->create();
        $partnerInstitution = Institution::factory()->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ViewExternalPartner->value],
            'selectedInstitution' => ['id' => $institution->id],
        ]);

        $this->prepareAuthorizedRequest($accessToken)
            ->postJson('/api/institution-partners', ['partner_institution_id' => $partnerInstitution->id])
            ->assertStatus(403);
    }

    public function test_403_cross_institution_update(): void
    {
        // GIVEN — partner belongs to another institution
        $otherPartner = InstitutionPartner::factory()->create();

        $attackerInstitutionId = Str::orderedUuid();
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [PrivilegeKey::ManageExternalPartner->value],
            'selectedInstitution' => ['id' => $attackerInstitutionId],
        ]);

        // WHEN — attacker tries to update another institution's partner
        $response = $this->prepareAuthorizedRequest($accessToken)
            ->putJson("/api/institution-partners/{$otherPartner->id}", [
                'discount_percentage_101' => 99.00,
            ]);

        // THEN — 404 because scope filters it out
        $response->assertStatus(404);
    }
}
