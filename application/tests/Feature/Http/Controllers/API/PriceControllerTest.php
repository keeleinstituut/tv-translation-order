<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\ClassifierValue;
use App\Models\Price;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Vendor;
use Illuminate\Support\Str;


class PriceControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        // GIVEN
        $testPrices = Price::factory(10)->create();
        $institutionId = $testPrices->first()->vendor->institutionUser->institution_id;

        $accessToken = $this->generateAccessToken([
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
            ->whereRelation('vendor.institutionUser', 'institution_id', $institutionId)
            ->get();
        $this->assertCount(1, $savedPrices);

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($savedPrices)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray()
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_bulk_create(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testVendors = Vendor::factory(10)->create();
        $payloadVendors = $testVendors->random(2)->each(function ($vendor) use ($institutionId) {
            $institutionUser = $vendor->institutionUser;
            $institutionUser->institution_id = $institutionId;
            $institutionUser->save();
            $vendor->refresh();
        });

        $sourceLang = ClassifierValue::factory()->language()->create();
        $destinationLang = ClassifierValue::factory()->language()->create();

        $accessToken = $this->generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = [
            "data" => collect($payloadVendors)->map(function ($vendor) use ($sourceLang, $destinationLang) {
                return [
                    'vendor_id' => $vendor->id,
                    'src_lang_classifier_value_id' => $sourceLang->id,
                    'dst_lang_classifier_value_id' => $destinationLang->id,
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
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => collect($savedPrices)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray()
            ])
            ->assertJson($payload);
    }

    public function test_bulk_delete(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testPrices = Price::factory(10)->create();
        $payloadPrices = $testPrices->random(2)->each(function ($price) use ($institutionId) {
            $institutionUser = $price->vendor->institutionUser;
            $institutionUser->institution_id = $institutionId;
            $institutionUser->save();
            $price->refresh();
        });

        $accessToken = $this->generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = $payloadPrices
            ->map(fn ($vendor) => 'id[]=' . $vendor->id)
            ->implode('&');

        $response = $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/prices/bulk?' . $payload);

        // THEN
        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => collect($payloadPrices)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
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
            $institutionUser->institution_id = $institutionId;
            $institutionUser->save();
            $price->refresh();
        });

        $accessToken = $this->generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        // WHEN
        $payload = [
            "data" => collect($payloadPrices)->map(function ($price) {
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
            ->orderBy('created_at', 'asc')
            ->get();

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'data' => collect($savedPrices)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray()
            ])
            ->assertJson($payload);
    }

    public static function constructRepresentation($obj): array
    {
        return [
            'id' => $obj->id,
            'vendor_id' => $obj->vendor_id,
            //
            // TODO: add skill
            //
            'src_lang_classifier_value_id' => $obj->src_lang_classifier_value_id,
            'dst_lang_classifier_value_id' => $obj->dst_lang_classifier_value_id,
            'created_at' => $obj->created_at->toIsoString(),
            'updated_at' => $obj->updated_at->toIsoString(),
            'character_fee' => $obj->character_fee,
            'word_fee' => $obj->word_fee,
            'page_fee' => $obj->page_fee,
            'minute_fee' => $obj->minute_fee,
            'hour_fee' => $obj->hour_fee,
            'minimal_fee' => $obj->minimal_fee,
            'vendor' => VendorControllerTest::constructRepresentation($obj->vendor),
            'source_language_classifier_value' => ClassifierValueControllerTest::constructRepresentation($obj->sourceLanguageClassifierValue),
            'destination_language_classifier_value' => ClassifierValueControllerTest::constructRepresentation($obj->destinationLanguageClassifierValue),
        ];
    }
}
