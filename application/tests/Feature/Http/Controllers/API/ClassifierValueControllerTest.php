<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class ClassifierValueControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */

    public function test_list(): void
    {
        ClassifierValue::factory()
            ->count(20)
            ->create();

        $expectedClassifierValues = ClassifierValue::getModel()
            ->orderBy('type', 'asc')
            ->orderBy('name', 'asc')
            ->get();
        $queryParams = [];

        $this->assertListEndpoint($expectedClassifierValues, $queryParams);
    }

    public function test_list_with_type_filter(): void
    {
        $testClassifierValues = ClassifierValue::factory()
            ->count(20)
            ->create();

        $testClassifierType = $testClassifierValues->first()->type;
        $expectedClassifierValues = ClassifierValue::getModel()
            ->where('type', $testClassifierType)
            ->orderBy('type', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $queryParams = [
            'type' => $testClassifierType->value,
        ];

        $this->assertListEndpoint($expectedClassifierValues, $queryParams);
    }

    private function assertListEndpoint($expectedDataset, $queryParams)
    {
        // GIVEN
        $accessToken = AuthHelpers::generateAccessToken();
        $queryString = http_build_query($queryParams);

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson("/api/classifier-values?$queryString");

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($expectedDataset)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray()
            ])
            ->assertJsonCount($expectedDataset->count(), 'data');
    }

    public static function constructRepresentation($obj): array
    {
        return RepresentationHelpers::createClassifierValueRepresentation($obj);
    }
}
