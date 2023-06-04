<?php

namespace Tests;

use App\Models\ClassifierValue;
use App\Models\Institution;
use App\Models\InstitutionUser;
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
                'access_token' => 'eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJjc2ZMMllPbFhLVWRiR18yV1AwVjQwX21Uc1VCUjdpN1pKRU1pa2hUdnNFIn0.eyJleHAiOjE2ODU0NDQyMDUsImlhdCI6MTY4NTQ0MzkwNSwianRpIjoiOWZiZjhjMjMtOWVhMC00MTIxLWE5NGMtNzA2ZGZiYmExNWRkIiwiaXNzIjoiaHR0cDovL2xvY2FsaG9zdDo4MDgwL3JlYWxtcy90b2xrZXZhcmF2LWRldiIsImF1ZCI6ImFjY291bnQiLCJzdWIiOiIxZTIyMDdiMy1jZWY0LTQyOTQtOTg4NC0yZjE1NDk4YzJiNzIiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiJkZW1vYXBwIiwiYWNyIjoiMSIsImFsbG93ZWQtb3JpZ2lucyI6WyIvKiJdLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsib2ZmbGluZV9hY2Nlc3MiLCJkZWZhdWx0LXJvbGVzLXRvbGtldmFyYXYtZGV2IiwidW1hX2F1dGhvcml6YXRpb24iXX0sInJlc291cmNlX2FjY2VzcyI6eyJhY2NvdW50Ijp7InJvbGVzIjpbIm1hbmFnZS1hY2NvdW50IiwibWFuYWdlLWFjY291bnQtbGlua3MiLCJ2aWV3LXByb2ZpbGUiXX19LCJzY29wZSI6InByb2ZpbGUgZW1haWwiLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsImNsaWVudEhvc3QiOiIxNzIuMTcuMC4xIiwicHJlZmVycmVkX3VzZXJuYW1lIjoic2VydmljZS1hY2NvdW50LWRlbW9hcHAiLCJjbGllbnRBZGRyZXNzIjoiMTcyLjE3LjAuMSIsImNsaWVudF9pZCI6ImRlbW9hcHAifQ.PccmNCJ_6xKFtqfIdzEARi83LAhu2HlF7MuwnDrb8xK9R-lkc5rW3bwZh1vyp9kmMM76BumMiiOO5dT6_ENk6Cabc4iXbg4Dn58URU5ZEEidE-a28vLB5GhXBQRidEvMKyfd8dAaOC1XTlXgmVvTObswoL1faMz07VTQVaZvdLR2xZiCDk_GYo0PWH4bsRsZGoR7_a1RyudRS0pL-6sSwhBcIgSMociFu2edrHRIfrRtgvcHYvWuk5ZhSgwcSLbZY_U4k7aoVTx8jT3iuciO_2BzJnLxeGtP_fONynHygVEeWyFjvugyzlGU6zkge16D-1jBktt4xb-GLMwKy_9YjQ',
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

    protected function generateClassifierValueResponseData(?string $id = null): array
    {
        $classifierValueAttributes = ClassifierValue::factory()->make()->getAttributes();
        $classifierValueAttributes['id'] = $id ?: Str::orderedUuid()->toString();
        $classifierValueAttributes['deleted_at'] = Carbon::now();
        if (filled($classifierValueAttributes['meta'])) {
            $classifierValueAttributes['meta'] = json_decode($classifierValueAttributes['meta'], true);
        }

        return $classifierValueAttributes;
    }

    protected function generateInstitutionResponseData(?string $id = null): array
    {
        $institutionAttributes = Institution::factory()->make()->getAttributes();

        $institutionAttributes['id'] = $id ?: Str::orderedUuid()->toString();
        $institutionAttributes['created_at'] = Carbon::now();
        $institutionAttributes['updated_at'] = Carbon::now();
        $institutionAttributes['deleted_at'] = Carbon::now();

        return $institutionAttributes;
    }

    protected function generateInstitutionUserResponseData(?string $id = null): array
    {
        $institutionUserAttributes = InstitutionUser::factory()->make()->getAttributes();

        $institutionUserAttributes['user'] = [
            'id' => $institutionUserAttributes['user_id'],
            'forename' => $institutionUserAttributes['forename'],
            'surname' => $institutionUserAttributes['surname'],
            'personal_identification_code' => $institutionUserAttributes['personal_identification_code'],
        ];

        $institutionUserAttributes['department'] = [
            'id' => $institutionUserAttributes['department_id'],
        ];

        unset(
            $institutionUserAttributes['user_id'],
            $institutionUserAttributes['forename'],
            $institutionUserAttributes['surname'],
            $institutionUserAttributes['personal_identification_code'],
            $institutionUserAttributes['department_id']
        );

        $institutionUserAttributes['id'] = $id ?: Str::orderedUuid()->toString();
        $institutionUserAttributes['created_at'] = Carbon::now();
        $institutionUserAttributes['updated_at'] = Carbon::now();
        $institutionUserAttributes['deleted_at'] = Carbon::now();

        return $institutionUserAttributes;
    }

    protected function assertInstitutionUserHasAttributesValuesFromResponseData(Model $institutionUser, array $responseData): void
    {
        foreach ($responseData as $attribute => $value) {
            if (is_array($value) || $attribute === 'status') {
                continue;
            }

            $this->assertEquals($value, $institutionUser->getAttributeValue($attribute), $attribute);
        }

        $this->assertEquals($responseData['user']['id'], $institutionUser->getAttributeValue('user_id'));
        $this->assertEquals($responseData['user']['forename'], $institutionUser->getAttributeValue('forename'));
        $this->assertEquals($responseData['user']['surname'], $institutionUser->getAttributeValue('surname'));
        $this->assertEquals($responseData['user']['personal_identification_code'], $institutionUser->getAttributeValue('personal_identification_code'));
        $this->assertEquals($responseData['department']['id'], $institutionUser->getAttributeValue('department_id'));
        $this->assertEquals($responseData['status']?->value, $institutionUser->status);
    }
}
