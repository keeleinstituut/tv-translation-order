<?php

namespace Feature\Http\Controllers\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Price;
use App\Models\Skill;
use App\Models\Vendor;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Enums\AuditLogEventType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Tests\AuditLogTestCase;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;

class PriceControllerAuditLogPublishedTest extends AuditLogTestCase
{

    public function test_create_audit_log_published(): void
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

        Date::setTestNow(Date::now());
        $this->prepareAuthorizedRequest($accessToken)->postJson('/api/prices', $payload)->assertSuccessful();

        // THEN
        $this->assertModifyVendorMessagePublished($testVendor, 0, 1);
    }


    public function test_bulk_create_audit_log_published(): void
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

        Date::setTestNow(Date::now());
        $this->prepareAuthorizedRequest($accessToken)->postJson('/api/prices/bulk', $payload)->assertSuccessful();

        // THEN
        Collection::times($payloadVendors->count())->map(function () use ($payloadVendors) {
            $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
            $actualVendorId = data_get($actualMessageBody, 'event_parameters.object_identity_subset.id');
            $vendor = collect($payloadVendors)->first(fn(Vendor $vendor) => $vendor->id === $actualVendorId);

            $this->assertMessageRepresentsVendorPriceChange($actualMessageBody, $vendor->refresh(), 0, 1);
        });
    }

    public function test_bulk_delete_audit_log_published(): void
    {
        // GIVEN
        $institutionId = Str::orderedUuid();
        $testPrices = Price::factory(10)->create();
        $targetPrices = $testPrices->random(2)->each(function ($price) use ($institutionId) {
            $institutionUser = $price->vendor->institutionUser;
            $institutionUser->institution['id'] = $institutionId;
            $institutionUser->save();
            $price->refresh();
        });
        $payloadPriceIds = $targetPrices->pluck('id');

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

        Date::setTestNow(Date::now());
        $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/prices/bulk?'.$payload)->assertSuccessful();

        // THEN
        Collection::times($targetPrices->count())->map(function () use ($targetPrices) {
            $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
            $actualVendorId = data_get($actualMessageBody, 'event_parameters.object_identity_subset.id');

            $vendor = collect($targetPrices)
                ->map(fn (Price $price) => $price->vendor)
                ->first(fn(Vendor $vendor) => $vendor->id === $actualVendorId);

            $this->assertMessageRepresentsVendorPriceChange($actualMessageBody, $vendor->refresh(), 1, 0);
        });
    }

    public function test_bulk_update_audit_log_published(): void
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

        Date::setTestNow(Date::now());
        $this->prepareAuthorizedRequest($accessToken)->putJson('/api/prices/bulk', $payload)->assertSuccessful();

        // THEN
        Collection::times($payloadPrices->count())->map(function () use ($payloadPrices) {
            $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
            $actualVendorId = data_get($actualMessageBody, 'event_parameters.object_identity_subset.id');

            $vendor = collect($payloadPrices)
                ->map(fn (Price $price) => $price->vendor)
                ->first(fn(Vendor $vendor) => $vendor->id === $actualVendorId);

            $this->assertMessageRepresentsVendorPriceChange($actualMessageBody, $vendor->refresh(), 1, 1);
        });
    }

    private function assertModifyVendorMessagePublished(Vendor $vendor, int $expectedPricesCountBefore, int $expectedPricesCountAfter): void {
        $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
        $this->assertMessageRepresentsVendorPriceChange($actualMessageBody, $vendor, $expectedPricesCountBefore, $expectedPricesCountAfter);
    }

    private function assertMessageRepresentsVendorPriceChange(array $actualMessageBody, Vendor $vendor, int $expectedPricesCountBefore, int $expectedPricesCountAfter): void {
        $expectedMessageBodySubset = [
            'event_type' => AuditLogEventType::ModifyObject->value,
            'happened_at' => Date::getTestNow()->toISOString(),
            'failure_type' => null
        ];

        $this->assertArrayHasSubsetIgnoringOrder(
            collect($expectedMessageBodySubset)->except('event_parameters')->all(),
            collect($actualMessageBody)->except('event_parameters')->all(),
        );
        $this->assertArraysEqualIgnoringOrder(
            [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'object_identity_subset' => $vendor->getIdentitySubset(),
            ],
            collect(data_get($actualMessageBody, 'event_parameters'))->only(['object_type', 'object_identity_subset'])->all(),
        );

        $this->assertEquals(['prices'], array_keys(data_get($actualMessageBody, 'event_parameters.pre_modification_subset')));
        $this->assertEquals(['prices'], array_keys(data_get($actualMessageBody, 'event_parameters.post_modification_subset')));

        $this->assertIsList(data_get($actualMessageBody, 'event_parameters.pre_modification_subset.prices'));
        $this->assertIsList(data_get($actualMessageBody, 'event_parameters.post_modification_subset.prices'));

        $this->assertCount($expectedPricesCountBefore, data_get($actualMessageBody, 'event_parameters.pre_modification_subset.prices'));
        $this->assertCount($expectedPricesCountAfter, data_get($actualMessageBody, 'event_parameters.post_modification_subset.prices'));
    }
}
