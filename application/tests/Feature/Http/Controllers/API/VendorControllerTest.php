<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Price;
use App\Models\Tag;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class VendorControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        $institutionId = Str::orderedUuid();
        $testIUsers = InstitutionUser::factory()
            ->count(10)
            ->has(
                Vendor::factory()
                    ->has(
                        Tag::factory()
                            ->typeVendor()
                            ->count(10)
                    )
            )
            ->create();
        $randomTestIUserIds = collect(fake()->randomElements($testIUsers, 3))
            ->each(function ($institutionUser) use ($institutionId) {
                $institutionUser->institution['id'] = $institutionId;
                $institutionUser->save();
            })
            ->pluck('id');
        $expectedVendors = Vendor::getModel()
            ->whereIn('institution_user_id', $randomTestIUserIds)
            ->with('prices', 'institutionUser', 'tags')
            ->join('entity_cache.cached_institution_users as institution_users', 'vendors.institution_user_id', '=', 'institution_users.id')
            ->orderByRaw("CONCAT(institution_users.\"user\"->>'forename', institution_users.\"user\"->>'surname') COLLATE \"et-EE-x-icu\" ASC")
            ->select('vendors.*')
            ->get();

        $queryParams = [];
        $this->assertListEndpoint($expectedVendors, $institutionId, $queryParams);
    }

    public function test_list_with_name_filter(): void
    {
        $institutionId = Str::orderedUuid();
        $testIUsers = InstitutionUser::factory()
            ->count(10)
            ->has(Vendor::factory())
            ->setInstitution([
                'id' => $institutionId,
            ])
            ->create();

        $testIUser = $testIUsers->first();
        $expectedVendors = collect([$testIUser->vendor]);

        $queryParams = [
            'fullname' => $testIUser->user['forename'],
        ];
        $this->assertListEndpoint($expectedVendors, $institutionId, $queryParams);
    }

    public function test_list_with_src_and_dst_lang_filter(): void
    {
        $institutionId = Str::orderedUuid();
        $testLanguageClassifiers = ClassifierValue::factory()
            ->language()
            ->count(10)
            ->create();
        [$testSourceLanguageClassifiers, $testDestinationLanguageClassifiers] = $testLanguageClassifiers->split(2);

        InstitutionUser::factory()
            ->count(5)
            ->has(
                Vendor::factory()
                    ->has(
                        Price::factory()
                            ->count(1)
                            ->state(new Sequence(fn (Sequence $seq) => [
                                'src_lang_classifier_value_id' => $testSourceLanguageClassifiers->random(),
                                'dst_lang_classifier_value_id' => $testDestinationLanguageClassifiers->random(),
                            ])),
                        'prices',
                    ),
                'vendor',
            )
            ->setInstitution([
                'id' => $institutionId,
            ])
            ->create();

        collect()->times(30)->each(fn () => DB::statement("select '1'"));

        $requestedSourceLangs = fake()->randomElements($testSourceLanguageClassifiers->pluck('id'), 2);
        $requestedDestinationLangs = fake()->randomElements($testDestinationLanguageClassifiers->pluck('id'), 2);

        $expectedVendors = Vendor::getModel()
            ->whereRelation('institutionUser', 'institution->id', $institutionId)
            ->whereRelation('prices.sourceLanguageClassifierValue', fn ($query) => $query->whereIn('id', $requestedSourceLangs))
            ->whereRelation('prices.destinationLanguageClassifierValue', fn ($query) => $query->whereIn('id', $requestedDestinationLangs))
            ->join('entity_cache.cached_institution_users as institution_users', 'vendors.institution_user_id', '=', 'institution_users.id')
            ->orderByRaw("CONCAT(institution_users.\"user\"->>'forename', institution_users.\"user\"->>'surname') ASC")
            ->select('vendors.*')
            ->get();

        $queryParams = [
            'src_lang_classifier_value_id' => $requestedSourceLangs,
            'dst_lang_classifier_value_id' => $requestedDestinationLangs,
        ];
        $this->assertListEndpoint($expectedVendors, $institutionId, $queryParams);
    }

    public function assertListEndpoint($expectedDataset, $institutionId, $queryParams)
    {
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'VIEW_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $queryString = http_build_query($queryParams);
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson("/api/vendors?$queryString");

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($expectedDataset)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray(),
            ])
            ->assertJsonCount($expectedDataset->count(), 'data');
    }

    public function test_showing(): void
    {
        $institutionUser = InstitutionUser::factory()->has(
            Vendor::factory()
                ->has(Tag::factory()->typeVendor())
        )->create();
        $institutionId = $institutionUser->institution['id'];
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
                'VIEW_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);

        $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/vendors/'.$institutionUser->vendor->id)
            ->assertStatus(200)
            ->assertJson([
                'data' => $this->constructRepresentation($institutionUser->vendor),
            ]);
    }

    public function test_showing_vendor_from_another_institution(): void
    {
        $institutionUser = InstitutionUser::factory()->has(
            Vendor::factory()
                ->has(Tag::factory()->typeVendor())
        )->create();

        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => Str::orderedUuid(),
            ],
        ]);

        $this->prepareAuthorizedRequest($accessToken)
            ->getJson('/api/vendors/'.$institutionUser->vendor->id)
            ->assertStatus(404);
    }

    public function test_bulk_create(): void
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

        $response = $this->prepareAuthorizedRequest($accessToken)->postJson('/api/vendors/bulk', $payload);

        $savedVendors = Vendor::all();

        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($savedVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray(),
            ])
            ->assertJson($payload);
    }

    public function test_bulk_delete(): void
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

        $response = $this->prepareAuthorizedRequest($accessToken)->deleteJson('/api/vendors/bulk?'.$payload);

        $response
            ->assertStatus(200)
            ->assertSimilarJson([
                'data' => collect($randomVendors)->map(fn ($vendor) => $this->constructRepresentation($vendor))->toArray(),
            ]);

        $deletedVendors = Vendor::whereIn('id', $randomVendors->pluck('id'))->get();
        $this->assertCount(0, $deletedVendors);
    }

    public function test_update(): void
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

        $savedVendor = Vendor::find($testVendor->id)
            ->load('institutionUser', 'tags');
        $response
            ->assertStatus(200)
            ->assertSimilarJson([
                'data' => self::constructRepresentation($savedVendor),
            ]);

        $this->assertEqualsCanonicalizing($payload['tags'], $savedVendor->tags->pluck('id')->toArray());
        $this->assertEquals($payload['comment'], $savedVendor->comment);
    }

    public static function constructRepresentation($obj)
    {
        return RepresentationHelpers::createVendorRepresentation($obj);
    }
}
