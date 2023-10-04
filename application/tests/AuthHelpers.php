<?php

namespace Tests;

use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\InstitutionUser;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

readonly class AuthHelpers
{
    public static function generateAccessToken(array $tolkevaravPayload = [], string $azp = null): string
    {
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

    public static function generateServiceAccountJwt(string $role = null, int $expiresIn = null): string
    {
        return JWT::encode([
            'iss' => config('keycloak.base_url').'/realms/'.config('keycloak.realm'),
            'exp' => time() + ($expiresIn ?: 300),
            'realm_access' => [
                'roles' => [$role ?: config('keycloak.service_account_sync_role')],
            ],
        ], static::getPrivateKey(), 'RS256');
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
    public static function createJsonHeaderWithTokenParams(string $institutionId, array $privileges): array
    {
        $defaultToken = self::generateAccessToken([
            'selectedInstitution' => ['id' => $institutionId],
            'privileges' => Arr::map($privileges, fn ($privilege) => $privilege->value),
        ]);

        return [
            'Authorization' => "Bearer $defaultToken",
            'Accept' => 'application/json',
        ];
    }

    public static function createHeadersForInstitutionUser(InstitutionUser $institutionUser): array
    {
        $accessToken = self::generateAccessToken(
            self::makeTolkevaravClaimsForInstitutionUser($institutionUser)
        );

        return [
            'Authorization' => "Bearer $accessToken",
        ];
    }

    /** @return array{
     *     personalIdentificationCode: string,
     *     userId: string,
     *     institutionUserId: string,
     *     forename: string,
     *     surname: string,
     *     selectedInstitution: array{
     *         id: string,
     *         name: string
     *     },
     *     privileges: array<string>
     * } */
    public static function makeTolkevaravClaimsForInstitutionUser(InstitutionUser $institutionUser): array
    {
        return [
            'institutionUserId' => $institutionUser->id,
            'personalIdentificationCode' => $institutionUser->user['personal_identification_code'],
            'userId' => $institutionUser->user['id'],
            'forename' => $institutionUser->user['forename'],
            'surname' => $institutionUser->user['surname'],
            'selectedInstitution' => [
                'id' => $institutionUser->institution['id'],
                'name' => $institutionUser->institution['name'],
            ],
            'privileges' => collect($institutionUser->roles)
                ->flatMap(fn (array $role) => $role['privileges'])
                ->unique()
                ->all(),
        ];
    }
}
