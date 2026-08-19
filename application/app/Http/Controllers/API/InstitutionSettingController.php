<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionSettingUpdateRequest;
use App\Http\Resources\API\InstitutionSettingResource;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionSetting;
use App\Policies\InstitutionSettingPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Throwable;

class InstitutionSettingController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/institution/settings',
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionSettingResource::class, description: 'Settings of the institution.')]
    #[OA\Response(
        response: \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT,
        description: 'Institution has no settings'
    )]
    public function show(): Response|InstitutionSettingResource
    {
        $this->authorize('viewAny', InstitutionSetting::class);

        $institutionSetting = $this->baseQuery()->first();

        if (empty($institutionSetting)) {
            return response()->noContent();
        }

        return InstitutionSettingResource::make($institutionSetting);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/institution/settings',
        requestBody: new OAH\RequestBody(InstitutionSettingUpdateRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionSettingResource::class, description: 'Creates or updates institution settings')]
    public function store(InstitutionSettingUpdateRequest $request): InstitutionSettingResource
    {
        $this->authorize('update', InstitutionSetting::class);

        $institution = Institution::findOrFail(Auth::user()->institutionId);

        /** @var InstitutionSetting $institutionSetting */
        $institutionSetting = $this->baseQuery()->firstOrNew()
            ->fill([
                ...$request->validated(),
                'institution_id' => $institution->id,
            ]);

        $institutionSetting->saveOrFail();

        return InstitutionSettingResource::make($institutionSetting->refresh());
    }

    private function baseQuery(): Builder
    {
        return InstitutionSetting::withGlobalScope('policy', InstitutionSettingPolicy::scope());
    }
}
