<?php

namespace Tests\Feature\Http\Controllers\API;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\InstitutionUser;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class InstitutionUserControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
//    public function test_list(): void
//    {
//        $testIUsers = InstitutionUser::factory()
//            ->count(10)
//            ->has(Vendor::factory())
//            ->create();
//
//        $institutionId = $testIUsers->first()->institution_id;
//        $accessToken = AuthHelpers::generateAccessToken([
//            'privileges' => [
//                'VIEW_VENDOR_DB',
//            ],
//            'selectedInstitution' => [
//                'id' => $institutionId,
//            ],
//        ]);
//
//
//        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/vendors');
//
//        $savedVendors = Vendor::getModel()
//            ->whereRelation('institutionUser', 'institution_id', $institutionId)
//            ->get();
//
//        $response
//            ->assertStatus(200)
//            ->assertJson([
//                'data' => collect($savedVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
//            ], true)
//            ->assertJsonCount(1, 'data');
//    }
//
//    public function test_bulk_create(): void
//    {
//        $institutionId = Str::orderedUuid();
//        $testIUsers = InstitutionUser::factory(10)->create([
//            'institution_id' => $institutionId,
//        ]);
//
//        $payload = [
//            "data" => collect($testIUsers)->map(function ($iuser) {
//                return [
//                    'institution_user_id' => $iuser->id,
//                ];
//            }),
//        ];
//
//        $accessToken = AuthHelpers::generateAccessToken([
//            'privileges' => [
//                'EDIT_VENDOR_DB',
//            ],
//            'selectedInstitution' => [
//                'id' => $institutionId,
//            ],
//        ]);
//
//        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/vendors/bulk', $payload);
//
//        $savedVendors = Vendor::all();
//
//        $response
//            ->assertStatus(200)
//            ->assertJson([
//                'data' => collect($savedVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
//            ]);
//    }
//
//    public function test_bulk_delete(): void
//    {
//        $institutionId = Str::orderedUuid();
//
//        $testIUsers = InstitutionUser::factory()
//            ->count(10)
//            ->has(Vendor::factory())
//            ->create([
//                'institution_id' => $institutionId,
//            ]);
//        $testVendors = collect($testIUsers)->pluck('vendor');
//
//        $randomVendors = collect($testVendors->random(3));
//        $payload = $randomVendors
//            ->map(fn ($vendor) => 'id[]=' . $vendor->id)
//            ->implode('&');
//
//        $accessToken = AuthHelpers::generateAccessToken([
//            'privileges' => [
//                'EDIT_VENDOR_DB',
//            ],
//            'selectedInstitution' => [
//                'id' => $institutionId,
//            ],
//        ]);
//
//        $response = $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/vendors/bulk?' . $payload);
//
//        $response
//            ->assertStatus(200)
//            ->assertJson([
//                'data' => collect($randomVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
//            ]);
//
//        $deletedVendors = Vendor::whereIn('id', $randomVendors->pluck('id'))->get();
//        $this->assertCount(0, $deletedVendors);
//    }

    public static function constructRepresentation($obj)
    {
        return [
            'id' => $obj->id,
            'user_id' => $obj->user_id,
            'forename' => $obj->forename,
            'surname' => $obj->surname,
            'personal_identification_code' => $obj->personal_identification_code,
            'status' => $obj->status,
            'email' => $obj->email,
            'phone' => $obj->phone,
            'created_at' => $obj->created_at->toIsoString(),
            'updated_at' => $obj->updated_at->toIsoString(),
            'institution_id' => $obj->institution_id,
        ];
    }
}
