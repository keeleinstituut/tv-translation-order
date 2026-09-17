<?php

namespace Tests\Unit\Sync\ApiClients;

use App\Sync\ApiClients\TvAuthorizationApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TvAuthorizationApiClientTest extends TestCase
{
    public function test_get_user_privileges_hits_the_correct_url_without_duplicating_api_prefix(): void
    {
        $baseUrl = rtrim(config('sync.authorization_service_base_url'), '/');

        Http::fake([
            "$baseUrl/user-privileges*" => Http::response(['data' => ['institutionPrivileges' => []]], 200),
        ]);

        app(TvAuthorizationApiClient::class)->getUserPrivileges('39608172765');

        Http::assertSent(function ($request) use ($baseUrl) {
            return $request->url() === "$baseUrl/user-privileges?personal_identification_code=39608172765";
        });
    }

    public function test_get_jwt_claims_hits_the_correct_url_without_duplicating_api_prefix(): void
    {
        $baseUrl = rtrim(config('sync.authorization_service_base_url'), '/');

        Http::fake([
            "$baseUrl/jwt-claims*" => Http::response(['personalIdentificationCode' => '39608172765'], 200),
        ]);

        app(TvAuthorizationApiClient::class)->getJwtClaims('39608172765', 'institution-1');

        Http::assertSent(function ($request) use ($baseUrl) {
            return $request->url() === "$baseUrl/jwt-claims?personal_identification_code=39608172765&institution_id=institution-1";
        });
    }
}
