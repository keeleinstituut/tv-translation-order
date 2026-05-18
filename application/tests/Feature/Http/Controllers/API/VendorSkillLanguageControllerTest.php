<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorSkillLanguage;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class VendorSkillLanguageControllerTest extends TestCase
{
    public function test_list(): void
    {
        $institutionId = Str::orderedUuid();
        $expected = collect();
        for ($i = 0; $i < 4; $i++) {
            $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
            $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
            $expected->push(VendorSkillLanguage::factory()->create(['vendor_id' => $vendor->id]));
        }

        VendorSkillLanguage::factory(6)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['VIEW_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/vendor-skill-languages');

        $saved = VendorSkillLanguage::query()
            ->whereRelation('vendor.institutionUser', 'institution->id', $institutionId)
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'desc')
            ->get();

        $this->assertCount($expected->count(), $saved);

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => $saved->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJsonCount($expected->count(), 'data');
    }

    public function test_create(): void
    {
        $institutionId = Str::orderedUuid();
        $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
        $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'vendor_id' => $vendor->id,
            'skill_id' => fake()->randomElement(Skill::pluck('id')),
            'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
            'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/vendor-skill-languages', $payload);

        $saved = VendorSkillLanguage::query()
            ->where('vendor_id', $vendor->id)
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->first();

        $response
            ->assertStatus(201)
            ->assertExactJson([
                'data' => $this->constructRepresentation($saved),
            ])
            ->assertJson([
                'data' => $payload,
            ]);
    }

    public function test_bulk_create(): void
    {
        $institutionId = Str::orderedUuid();
        $vendors = collect();
        for ($i = 0; $i < 2; $i++) {
            $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
            $vendors->push(Vendor::factory()->create(['institution_user_id' => $institutionUser->id]));
        }

        Vendor::factory(8)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => $vendors->map(fn ($vendor) => [
                'vendor_id' => $vendor->id,
                'skill_id' => fake()->randomElement(Skill::pluck('id')),
                'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
            ])->toArray(),
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/vendor-skill-languages/bulk', $payload);

        $saved = VendorSkillLanguage::query()
            ->whereIn('vendor_id', $vendors->pluck('id'))
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => $saved->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJson($payload);
    }

    public function test_bulk_update(): void
    {
        $institutionId = Str::orderedUuid();
        $rows = collect();
        for ($i = 0; $i < 2; $i++) {
            $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
            $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
            $rows->push(VendorSkillLanguage::factory()->create(['vendor_id' => $vendor->id]));
        }

        VendorSkillLanguage::factory(8)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $payload = [
            'data' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
            ])->toArray(),
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->putJson('/api/vendor-skill-languages/bulk', $payload);

        $saved = VendorSkillLanguage::query()
            ->whereIn('id', $rows->pluck('id'))
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => $saved->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ]);
    }

    public function test_bulk_delete(): void
    {
        $institutionId = Str::orderedUuid();
        $rows = collect();
        for ($i = 0; $i < 2; $i++) {
            $institutionUser = InstitutionUser::factory()->setInstitution(['id' => $institutionId])->create();
            $vendor = Vendor::factory()->create(['institution_user_id' => $institutionUser->id]);
            $rows->push(VendorSkillLanguage::factory()->create(['vendor_id' => $vendor->id]));
        }
        $ids = $rows->pluck('id');

        VendorSkillLanguage::factory(8)->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => ['EDIT_VENDOR_DB'],
            'selectedInstitution' => ['id' => $institutionId],
        ]);

        $query = $ids->map(fn ($id) => 'id[]=' . $id)->implode('&');
        $response = $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/vendor-skill-languages/bulk?' . $query);

        $deleted = VendorSkillLanguage::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => $deleted->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ]);

        $this->assertCount(0, VendorSkillLanguage::query()->whereIn('id', $ids)->get());
    }

    public static function constructRepresentation(VendorSkillLanguage $obj): array
    {
        return RepresentationHelpers::createVendorSkillLanguageRepresentation($obj);
    }
}
