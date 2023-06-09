<?php

namespace Tests\Feature\Http\Controllers\API;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\InstitutionUser;
use App\Models\Vendor;
use Illuminate\Support\Str;


class VendorControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        $testIUsers = InstitutionUser::factory()
            ->count(10)
            ->has(Vendor::factory())
            ->create();

        $institutionId = $testIUsers->first()->institution_id;
        $accessToken = $this->generateAccessToken([
            'privileges' => [
                'VIEW_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);


        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/vendors');

        $savedVendors = Vendor::getModel()
            ->whereRelation('institutionUser', 'institution_id', $institutionId)
            ->get();

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($savedVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_bulk_create(): void
    {
        $institutionId = Str::orderedUuid();
        $testIUsers = InstitutionUser::factory(10)->create([
            'institution_id' => $institutionId,
        ]);

        $payload = [
            "data" => collect($testIUsers)->map(function ($iuser) {
                return [
                    'institution_user_id' => $iuser->id,
                ];
            })->toArray(),
        ];

        $accessToken = $this->generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/vendors/bulk', $payload);

        $savedVendors = Vendor::all();

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($savedVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
            ])
            ->assertJson($payload);
    }

    public function test_bulk_delete(): void
    {
        $institutionId = Str::orderedUuid();

        $testIUsers = InstitutionUser::factory()
            ->count(10)
            ->has(Vendor::factory())
            ->create([
                'institution_id' => $institutionId,
            ]);
        $testVendors = collect($testIUsers)->pluck('vendor');

        $randomVendors = collect($testVendors->random(3))->sortBy('created_at');

        $accessToken = $this->generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $payload = $randomVendors
            ->map(fn ($vendor) => 'id[]=' . $vendor->id)
            ->implode('&');

        $response = $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/vendors/bulk?' . $payload);

        $response
            ->assertStatus(200)
            ->assertSimilarJson([
                'data' => collect($randomVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
            ]);

        $deletedVendors = Vendor::whereIn('id', $randomVendors->pluck('id'))->get();
        $this->assertCount(0, $deletedVendors);
    }

    public static function constructRepresentation($obj)
    {
        return [
            'id' => $obj->id,
            'institution_user_id' => $obj->institution_user_id,
            'company_name' => $obj->company_name,
            'created_at' => $obj->created_at->toIsoString(),
            'updated_at' => $obj->updated_at->toIsoString(),
            'discount_percentage_101' => $obj->discount_percentage_101,
            'discount_percentage_repetitions' => $obj->discount_percentage_repetitions,
            'discount_percentage_100' => $obj->discount_percentage_100,
            'discount_percentage_95_99' => $obj->discount_percentage_95_99,
            'discount_percentage_85_94' => $obj->discount_percentage_85_94,
            'discount_percentage_75_84' => $obj->discount_percentage_75_84,
            'discount_percentage_50_74' => $obj->discount_percentage_50_74,
            'discount_percentage_0_49' => $obj->discount_percentage_0_49,
            'institution_user' => InstitutionUserControllerTest::constructRepresentation($obj->institutionUser),
        ];
    }
}
