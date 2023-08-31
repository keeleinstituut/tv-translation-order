<?php

namespace Tests;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use Arr;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait ApiResponseHelpers
{
    protected function getFakeKeycloakServiceAccountJwtResponse(): array
    {
        return [
            rtrim(config('keycloak.base_url'), '/').'/*' => Http::response([
                'access_token' => AuthHelpers::generateServiceAccountJwt(),
                'expires_in' => 300,
            ]),
        ];
    }

    protected function getFakeClassifierValuesResponse(array $responseData = []): array
    {
        return [
            rtrim(config('sync.classifier_service_base_url'), '/').'/sync/classifier-values' => Http::response([
                'data' => $responseData,
            ]),
        ];
    }

    protected function getFakeClassifierValueResponse(array $responseData): array
    {
        return [
            rtrim(config('sync.classifier_service_base_url'), '/').'/sync/classifier-values/*' => Http::response([
                'data' => $responseData,
            ]),
        ];
    }

    protected function getFakeNotFoundClassifierValueResponse(): array
    {
        return [
            rtrim(config('sync.classifier_service_base_url'), '/').'/sync/classifier-values/*' => Http::response(status: 404),
        ];
    }

    protected function getFakeInstitutionsResponse(array $responseData = []): array
    {
        return [
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institutions' => Http::response([
                'data' => $responseData,
            ]),
        ];
    }

    protected function getFakeInstitutionResponse(array $responseData = []): array
    {
        return [
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institutions/*' => Http::response([
                'data' => $responseData,
            ]),
        ];
    }

    protected function getFakeNotFoundInstitutionResponse(): array
    {
        return [
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institutions/*' => Http::response(status: 404),
        ];
    }

    protected function getFakeInstitutionUsersResponse(array $responseData = []): array
    {
        return [
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institution-users?*' => Http::response([
                'data' => $responseData,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]),
        ];
    }

    protected function getFakeInstitutionUserResponse(array $responseData = []): array
    {
        return [
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institution-users/*' => Http::response([
                'data' => $responseData,
            ]),
        ];
    }

    protected function getFakeNotFoundInstitutionUserResponse(): array
    {
        return [
            rtrim(config('sync.authorization_service_base_url'), '/').'/sync/institution-users/*' => Http::response(status: 404),
        ];
    }

    protected function generateClassifierValueResponseData(string $id = null): array
    {
        $classifierValueAttributes = ClassifierValue::factory()->make()->getAttributes();
        $classifierValueAttributes['id'] = $id ?: Str::orderedUuid()->toString();
        $classifierValueAttributes['deleted_at'] = Carbon::now();
        if (filled($classifierValueAttributes['meta'])) {
            $classifierValueAttributes['meta'] = json_decode($classifierValueAttributes['meta'], true);
        }

        return $classifierValueAttributes;
    }

    protected function generateInstitutionResponseData(string $id = null, bool $isDeleted = false): array
    {
        $institutionAttributes = Institution::factory()->make()->getAttributes();

        $institutionAttributes['id'] = $id ?: Str::orderedUuid()->toString();
        $institutionAttributes['deleted_at'] = $isDeleted ? Carbon::now() : null;

        return $institutionAttributes;
    }

    protected function generateInstitutionUserResponseData(string $id = null, bool $isDeleted = false): array
    {
        $institutionUser = InstitutionUser::factory()->make();
        $institutionUserAttributes = $institutionUser->getAttributes();
        $institutionUserAttributes['id'] = $id ?: Str::orderedUuid()->toString();
        $institutionUserAttributes['deleted_at'] = $isDeleted ? Carbon::now()->toISOString() : null;

        $institutionUserAttributes['user'] = $institutionUser->user;
        $institutionUserAttributes['department'] = $institutionUser->department;
        $institutionUserAttributes['institution'] = $institutionUser->institution;
        $institutionUserAttributes['roles'] = $institutionUser->roles;

        return $institutionUserAttributes;
    }

    protected function assertInstitutionUserHasAttributesValuesFromResponseData(Model $institutionUser, array $responseData): void
    {
        collect(['id', 'phone', 'email', 'deactivation_date'])
            ->each(fn ($attribute) => $this->assertEquals(
                $responseData[$attribute],
                $institutionUser->getAttribute($attribute), $attribute)
            );

        collect(['archived_at', 'deleted_at'])
            ->each(fn ($attribute) => $this->assertEquals(
                $responseData[$attribute],
                filled($institutionUser->getAttribute($attribute)) ?
                    $institutionUser->getAttribute($attribute)->toISOString() : null,
                $attribute
            ));

        collect(['id', 'forename', 'surname', 'personal_identification_code'])
            ->each(fn ($attribute) => $this->assertEquals(
                $responseData['user'][$attribute],
                Arr::get($institutionUser->user, $attribute),
                "user.$attribute"
            ));

        collect(['id', 'name', 'institution_id'])
            ->each(fn ($attribute) => $this->assertEquals(
                $responseData['department'][$attribute],
                Arr::get($institutionUser->department, $attribute),
                "department.$attribute"
            ));

        collect(['id', 'name', 'short_name', 'phone', 'email', 'logo_url'])
            ->each(fn ($attribute) => $this->assertEquals(
                $responseData['institution'][$attribute],
                Arr::get($institutionUser->institution, $attribute),
                "institution.$attribute"
            ));
    }
}
