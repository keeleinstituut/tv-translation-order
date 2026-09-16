<?php

namespace App\Sync\ApiClients;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class TvAuthorizationApiClient
{
    private string $baseUrl;

    public function __construct(private readonly ServiceAccountJwtRetrieverInterface $jwtRetriever)
    {
        $this->baseUrl = rtrim(config('sync.authorization_service_base_url'), '/');
    }

    public function getInstitution(string $id): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institutions/$id")
            ->json('data');
    }

    public function getInstitutions(): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institutions")
            ->json('data');
    }

    public function getInstitutionUser(string $id): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institution-users/$id")
            ->json('data');
    }

    public function getInstitutionUsers(?int $page = null): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/sync/institution-users", [
            'page' => $page ?: 1,
        ])->json();
    }

    /**
     * Which institutions the given person belongs to, and their privileges in each.
     *
     * @return array{user: array, institutionPrivileges: array<string, array<int, string>>}
     */
    public function getUserPrivileges(string $personalIdentificationCode): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/user-privileges", [
            'personal_identification_code' => $personalIdentificationCode,
        ])->json('data');
    }

    /**
     * Full JWT-claims shape (personalIdentificationCode, userId, institutionUserId,
     * forename, surname, selectedInstitution, privileges) for one institution — the
     * same shape Keycloak's own claims mapper receives, suitable for constructing an
     * AuthUser instance directly.
     *
     * @return array<string, mixed>
     */
    public function getJwtClaims(string $personalIdentificationCode, string $institutionId): array
    {
        return $this->getBaseRequest()->get("$this->baseUrl/jwt-claims", [
            'personal_identification_code' => $personalIdentificationCode,
            'institution_id' => $institutionId,
        ])->json();
    }

    private function getBaseRequest(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->jwtRetriever->getJwt(),
        ])->throw();
    }
}
