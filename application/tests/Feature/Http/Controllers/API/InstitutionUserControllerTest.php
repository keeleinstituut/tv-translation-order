<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Support\Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class InstitutionUserControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        $institutionId = Str::orderedUuid();

        InstitutionUser::factory()
            ->count(10)
            ->setInstitution(['id' => $institutionId])
            ->create();

        // Create 10 more with different institution IDs
        InstitutionUser::factory()
            ->count(10)
            ->create();

        // Re-query to get them in the correct order
        $expectedInstitutionUsers = InstitutionUser::getModel()
            ->where('institution->id', $institutionId)
            ->orderByRaw("CONCAT(\"user\"->>'forename', \"user\"->>'surname') COLLATE \"et-EE-x-icu\" ASC")
            ->get();

        $queryParams = [];

        $this->assertListEndpoint($expectedInstitutionUsers, $institutionId, $queryParams);
    }

    private function assertListEndpoint($expectedDataset, $institutionId, $queryParams)
    {
        // GIVEN
        $accessToken = AuthHelpers::generateAccessToken([
            'privileges' => [
                'EDIT_VENDOR_DB',
            ],
            'selectedInstitution' => [
                'id' => $institutionId,
            ],
        ]);
        $queryString = http_build_query($queryParams);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson("/api/institution-users?$queryString");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($expectedDataset)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJsonCount($expectedDataset->count(), 'data');
    }

    public static function constructRepresentation($obj): array
    {
        return RepresentationHelpers::createInstitutionUserRepresentation($obj);
    }
}
