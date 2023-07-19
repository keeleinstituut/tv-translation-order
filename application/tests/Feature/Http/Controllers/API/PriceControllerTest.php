<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Price;
use App\Models\Skill;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class PriceControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testPrices = Price::factory(10)->create();
        $randomPrices = fake()->randomElements($testPrices, 4);
        collect($randomPrices)->each(function ($price) use ($institutionId) {
            $institutionUser = $price->vendor->institutionUser;
            $institutionUser->institution['id'] = $institutionId;
            $institutionUser->save();
        });

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'VIEW_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/prices');

        // THEN
        $savedPrices = Price::getModel()
            ->whereRelation('vendor.institutionUser', 'institution->id', $institutionId)
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'desc')
            ->get();
        $this->assertCount(count($randomPrices), $savedPrices);

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($savedPrices)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJsonCount(count($randomPrices), 'data');
    }

    public function test_create(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testVendor = Vendor::factory()->create();
        $testVendor->institutionUser->institution['id'] = $institutionId;
        $testVendor->institutionUser->save();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = [
            'vendor_id' => $testVendor->id,
            'skill_id' => fake()->randomElement(Skill::pluck('id')),
            'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
            'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
            'character_fee' => fake()->randomFloat(2, 0, 1000),
            'word_fee' => fake()->randomFloat(2, 0, 1000),
            'page_fee' => fake()->randomFloat(2, 0, 1000),
            'minute_fee' => fake()->randomFloat(2, 0, 1000),
            'hour_fee' => fake()->randomFloat(2, 0, 1000),
            'minimal_fee' => fake()->randomFloat(2, 0, 1000),
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/prices', $payload);

        // THEN
        $savedPrice = Price::getModel()
            ->where('vendor_id', $testVendor->id)
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->first();

        $response
            ->assertStatus(201)
            ->assertExactJson([
                'data' => $this->constructRepresentation($savedPrice),
            ])
            ->assertJson([
                'data' => $payload,
            ]);
    }

    public function test_bulk_create(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testVendors = Vendor::factory(10)->create();
        $payloadVendors = $testVendors->random(2)->each(function ($vendor) use ($institutionId) {
            $institutionUser = $vendor->institutionUser;
            $institutionUser->institution['id'] = $institutionId;
            $institutionUser->save();
            $vendor->refresh();
        });

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = [
            'data' => collect($payloadVendors)->map(function ($vendor) {
                return [
                    'vendor_id' => $vendor->id,
                    'skill_id' => fake()->randomElement(Skill::pluck('id')),
                    'src_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                    'dst_lang_classifier_value_id' => ClassifierValue::factory()->language()->create()->id,
                    'character_fee' => fake()->randomFloat(2, 0, 1000),
                    'word_fee' => fake()->randomFloat(2, 0, 1000),
                    'page_fee' => fake()->randomFloat(2, 0, 1000),
                    'minute_fee' => fake()->randomFloat(2, 0, 1000),
                    'hour_fee' => fake()->randomFloat(2, 0, 1000),
                    'minimal_fee' => fake()->randomFloat(2, 0, 1000),
                ];
            })->toArray(),
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/prices/bulk', $payload);

        // THEN
        $savedPrices = Price::getModel()
            ->whereIn('vendor_id', $payloadVendors->pluck('id'))
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => collect($savedPrices)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJson($payload);
    }

    public function test_bulk_delete(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testPrices = Price::factory(10)->create();
        $payloadPriceIds = $testPrices->random(2)->each(function ($price) use ($institutionId) {
            $institutionUser = $price->vendor->institutionUser;
            $institutionUser->institution['id'] = $institutionId;
            $institutionUser->save();
            $price->refresh();
        })->pluck('id');

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = $payloadPriceIds
            ->map(fn ($id) => 'id[]='.$id)
            ->implode('&');

        $response = $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/prices/bulk?'.$payload);

        // THEN
        $payloadPrices = Price::getModel()
            ->withTrashed()
            ->whereIn('id', $payloadPriceIds)
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => collect($payloadPrices)->map(fn ($price) => $this->constructRepresentation($price))->toArray(),
            ]);

        $deletedVendors = Vendor::whereIn('id', $payloadPrices->pluck('id'))->get();
        $this->assertCount(0, $deletedVendors);
    }

    public function test_bulk_update(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testPrices = Price::factory(10)->create();
        $payloadPrices = $testPrices->random(2)->each(function ($price) use ($institutionId) {
            $institutionUser = $price->vendor->institutionUser;
            $institutionUser->institution['id'] = $institutionId;
            $institutionUser->save();
            $price->refresh();
        });

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = [
            'data' => collect($payloadPrices)->map(function ($price) {
                return [
                    'id' => $price->id,
                    'character_fee' => fake()->randomFloat(2, 0, 1000),
                    'word_fee' => fake()->randomFloat(2, 0, 1000),
                    'page_fee' => fake()->randomFloat(2, 0, 1000),
                    'minute_fee' => fake()->randomFloat(2, 0, 1000),
                    'hour_fee' => fake()->randomFloat(2, 0, 1000),
                    'minimal_fee' => fake()->randomFloat(2, 0, 1000),
                ];
            })->toArray(),
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->putJson('/api/prices/bulk', $payload);

        // THEN
        $savedPrices = Price::getModel()
            ->whereIn('id', $payloadPrices->pluck('id'))
            ->with('vendor', 'vendor.institutionUser')
            ->with('skill', 'sourceLanguageClassifierValue', 'destinationLanguageClassifierValue')
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => collect($savedPrices)->map(fn ($price) => $this->constructRepresentation($price))->toArray(),
            ]);
    }

    public static function constructRepresentation($obj): array
    {
        return RepresentationHelpers::createPriceRepresentation($obj);
    }
}
