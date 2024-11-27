<?php

namespace Feature\Http\Controllers\API;

use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Tag;
use App\Models\Vendor;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Enums\AuditLogEventType;
use Carbon\CarbonInterval;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\Assertions;
use Tests\AuditLogTestCase;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;

class VendorControllerAuditLogPublishedTest extends AuditLogTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Date::setTestNow(Date::now());
    }

    public function test_bulk_create_audit_log_published(): void
    {
        $institutionId = Str::orderedUuid();
        $testIUsers = InstitutionUser::factory(10)
            ->setInstitution([
                'id' => $institutionId,
            ])
            ->create();

        $payload = [
            'data' => collect($testIUsers)->map(function ($iuser) {
                return [
                    'institution_user_id' => $iuser->id,
                ];
            })->toArray(),
        ];

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $this->prepareAuthorizedRequest($accessToken)->postJson('/api/vendors/bulk', $payload)->assertSuccessful();

        $sortedActualMessageBodies = Collection::times($testIUsers->count())
            ->map(function () {
                Sleep::for(CarbonInterval::milliseconds(100));

                return $this->retrieveLatestAuditLogMessageBody();
            })
            ->sortBy('event_parameters.object_identity_subset.id')
            ->values();

        $testIUsers->sortBy('id')->values()->zip($sortedActualMessageBodies)
            ->eachSpread(function (InstitutionUser $institutionUser, array $actualMessageBody) {
                $this->assertMessageRepresentsVendorCreation($actualMessageBody, function (array $objectData, array $objectIdentitySubset) use ($institutionUser) {
                    $this->assertEquals(
                        $institutionUser->id,
                        data_get($objectData, 'institution_user_id')
                    );

                    Assertions::assertArraysEqualIgnoringOrder(
                        [
                            'id' => $institutionUser->id,
                            'user' => [
                                'id' => data_get($institutionUser->user, 'id'),
                                'personal_identification_code' => data_get($institutionUser->user, 'personal_identification_code'),
                                'forename' => data_get($institutionUser->user, 'forename'),
                                'surname' => data_get($institutionUser->user, 'surname'),
                            ],
                        ],
                        data_get($objectIdentitySubset, 'institution_user')
                    );
                });
            });
    }

    public function test_bulk_delete_audit_log_published(): void
    {
        $institutionId = Str::orderedUuid();

        $testIUsers = InstitutionUser::factory()
            ->count(10)
            ->has(Vendor::factory())
            ->setInstitution([
                'id' => $institutionId,
            ])
            ->create();
        $testVendors = collect($testIUsers)->pluck('vendor');

        $randomVendorsIds = collect($testVendors->random(3))->pluck('id');
        /** @var \Illuminate\Database\Eloquent\Collection $randomVendors */
        $randomVendors = Vendor::getModel()
            ->whereIn('id', $randomVendorsIds)
            ->with('institutionUser', 'prices')
            ->orderBy('created_at')
            ->get();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $payload = $randomVendors
            ->map(fn ($vendor) => 'id[]='.$vendor->id)
            ->implode('&');

        $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/vendors/bulk?'.$payload)->assertSuccessful();

        $sortedActualMessageBodies = Collection::times($randomVendors->count())
            ->map(function () {
                Sleep::for(CarbonInterval::milliseconds(100));

                return $this->retrieveLatestAuditLogMessageBody();
            })
            ->takeWhile(fn (?array $messageBody) => $messageBody !== null)
            ->sortBy('event_parameters.object_identity_subset.id')
            ->values();

        $this->assertCount($randomVendors->count(), $sortedActualMessageBodies);

        $randomVendors->sortBy('id')->values()->zip($sortedActualMessageBodies)
            ->eachSpread(function (Vendor $vendor, array $actualMessageBody) {
                $this->assertMessageRepresentsVendorRemoval($actualMessageBody, $vendor);
            });
    }

    public function test_update_audit_log_published(): void
    {
        $institution = Institution::factory()->create();
        $institutionId = $institution->id;

        $testIUser = InstitutionUser::factory()
            ->has(Vendor::factory())
            ->setInstitution([
                'id' => $institutionId,
            ])
            ->create();
        $testVendor = $testIUser->vendor;
        $testVendorBeforeRequest = $testVendor->getAuditLogRepresentation();
        $testTags = Tag::factory()
            ->count(3)
            ->typeVendor()
            ->create([
                'institution_id' => $institutionId,
            ]);

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $payload = [
            'tags' => $testTags->pluck('id')->toArray(),
            'comment' => fake()->text(),
            'company_name' => fake()->text(),
            'discount_percentage_101' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_repetitions' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_100' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_95_99' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_85_94' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_75_84' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_50_74' => fake()->randomFloat(2, 0, 100),
            'discount_percentage_0_49' => fake()->randomFloat(2, 0, 100),
        ];

        $response = $this->prepareAuthorizedRequest($accessToken)->putJson("/api/vendors/$testVendor->id", $payload);
        $response->assertSuccessful();

        $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
        $this->assertMessageRepresentsVendorModification(
            $actualMessageBody,
            $testVendor,
            function (array $eventParameters) use ($testVendorBeforeRequest, $payload): void {
                Assertions::assertArraysEqualIgnoringOrder(
                    collect($testVendorBeforeRequest)->intersectByKeys($payload)->except('tags')->all(),
                    data_get($eventParameters, 'pre_modification_subset')
                );

                Assertions::assertArraysEqualIgnoringOrder(
                    Arr::except($payload, 'tags'),
                    data_get($eventParameters, 'post_modification_subset')
                );
            }
        );
    }

    public static function constructRepresentation($obj): array
    {
        return RepresentationHelpers::createVendorRepresentation($obj);
    }

    /**
     * @param  Closure(array): void  $assertOnObjectData
     */
    private function assertMessageRepresentsVendorCreation(array $actualMessageBody, Closure $assertOnObjectData): void
    {
        $expectedMessageBodySubset = [
            'event_type' => AuditLogEventType::CreateObject->value,
            'happened_at' => Date::getTestNow()->toISOString(),
            'failure_type' => null,
        ];

        Assertions::assertArrayHasSubsetIgnoringOrder(
            collect($expectedMessageBodySubset)->except('event_parameters')->all(),
            collect($actualMessageBody)->except('event_parameters')->all(),
        );

        $eventParameters = data_get($actualMessageBody, 'event_parameters');
        $this->assertIsArray($eventParameters);

        Assertions::assertArraysEqualIgnoringOrder(
            [
                'object_type' => AuditLogEventObjectType::Vendor->value,
            ],
            collect($eventParameters)->only(['object_type'])->all(),
        );

        $objectData = data_get($eventParameters, 'object_data');
        $this->assertIsArray($objectData);
        $objectIdentitySubset = data_get($eventParameters, 'object_identity_subset');
        $this->assertIsArray($objectIdentitySubset);
        $assertOnObjectData($objectData, $objectIdentitySubset);
    }

    /**
     * @param  Closure(array): void  $assertOnEventParameters
     */
    private function assertMessageRepresentsVendorModification(array $actualMessageBody, Vendor $vendor, Closure $assertOnEventParameters): void
    {
        $expectedMessageBodySubset = [
            'event_type' => AuditLogEventType::ModifyObject->value,
            'happened_at' => Date::getTestNow()->toISOString(),
            'failure_type' => null,
        ];

        Assertions::assertArrayHasSubsetIgnoringOrder(
            collect($expectedMessageBodySubset)->except('event_parameters')->all(),
            collect($actualMessageBody)->except('event_parameters')->all(),
        );

        $eventParameters = data_get($actualMessageBody, 'event_parameters');
        $this->assertIsArray($eventParameters);

        Assertions::assertArraysEqualIgnoringOrder(
            [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'object_identity_subset' => $vendor->getIdentitySubset(),
            ],
            collect($eventParameters)->only(['object_type', 'object_identity_subset'])->all(),
        );

        $assertOnEventParameters($eventParameters);
    }

    private function assertMessageRepresentsVendorRemoval(array $actualMessageBody, Vendor $vendor): void
    {
        $expectedMessageBodySubset = [
            'event_type' => AuditLogEventType::RemoveObject->value,
            'happened_at' => Date::getTestNow()->toISOString(),
            'failure_type' => null,
        ];

        Assertions::assertArrayHasSubsetIgnoringOrder(
            $expectedMessageBodySubset,
            Arr::except($actualMessageBody, 'event_parameters'),
        );

        $eventParameters = data_get($actualMessageBody, 'event_parameters');

        Assertions::assertArraysEqualIgnoringOrder(
            [
                'object_type' => AuditLogEventObjectType::Vendor->value,
                'object_identity_subset' => $vendor->getIdentitySubset(),
            ],
            $eventParameters,
        );
    }
}
