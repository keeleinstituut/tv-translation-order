<?php

namespace Tests;

use App\Enums\PrivilegeKey;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait AuthHelpers
{
    public static function generateAccessToken(array $tolkevaravPayload = [], string $azp = null): string
    {
        // TODO: would be good to have full example JWT here with
        // TODO: all relevant claims to simulate real JWT.
        // TODO: This JWT should be overwrittable to support
        // TODO: different edge cases.
        $payload = collect([
            'azp' => $azp ?? Str::of(config('keycloak.accepted_authorized_parties'))
                ->explode(',')
                ->first(),
            'iss' => config('keycloak.base_url').'/realms/'.config('keycloak.realm'),
            'tolkevarav' => collect([
                'userId' => 1,
                'personalIdentificationCode' => '11111111111',
                'privileges' => [],
            ])->merge($tolkevaravPayload)->toArray(),
        ]);

        return static::createJwt($payload->toArray());
    }

    public function prepareAuthorizedRequest($accessToken)
    {
        return $this->withHeaders([
            'Authorization' => "Bearer $accessToken",
        ]);
    }

    private static function createJwt(array $payload): string
    {
        $privateKeyPem = static::getPrivateKey();

        return JWT::encode($payload, $privateKeyPem, 'RS256');
    }

    private static function getPrivateKey(): string
    {
        $key = env('KEYCLOAK_REALM_PRIVATE_KEY');

        return "-----BEGIN PRIVATE KEY-----\n".
            wordwrap($key, 64, "\n", true).
            "\n-----END PRIVATE KEY-----";
    }

    /**
     * @param  array<PrivilegeKey>  $privileges
     */
    public function createJsonHeaderWithTokenParams(string $institutionId, array $privileges): array
    {
        $defaultToken = $this->generateAccessToken([
            'selectedInstitution' => ['id' => $institutionId],
            'privileges' => Arr::map($privileges, fn ($privilege) => $privilege->value),
        ]);

        return [
            'Authorization' => "Bearer $defaultToken",
            'Accept' => 'application/json',
        ];
    }
}
