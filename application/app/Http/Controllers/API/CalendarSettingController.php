<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CalendarSettingUpdateRequest;
use App\Http\Resources\API\CalendarSettingResource;
use App\Models\CalendarSetting;
use App\Models\CachedEntities\Institution;
use App\Policies\CalendarSettingPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Throwable;

class CalendarSettingController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/calendar/settings',
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: CalendarSettingResource::class, description: 'Calendar settings of the institution.')]
    #[OA\Response(
        response: \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT,
        description: 'Institution has no calendar settings'
    )]
    public function show(): Response|CalendarSettingResource
    {
        $this->authorize('viewAny', CalendarSetting::class);

        $calendarSetting = $this->baseQuery()->first();

        if (empty($calendarSetting)) {
            return response()->noContent();
        }

        return CalendarSettingResource::make($calendarSetting);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/calendar/settings',
        requestBody: new OAH\RequestBody(CalendarSettingUpdateRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: CalendarSettingResource::class, description: 'Creates or updates calendar settings')]
    public function store(CalendarSettingUpdateRequest $request): CalendarSettingResource
    {
        $this->authorize('update', CalendarSetting::class);

        $institution = Institution::findOrFail(Auth::user()->institutionId);

        /** @var CalendarSetting $calendarSetting */
        $calendarSetting = $this->baseQuery()->firstOrNew()
            ->fill([
                ...$request->validated(),
                'institution_id' => $institution->id,
            ]);

        $calendarSetting->saveOrFail();

        return CalendarSettingResource::make($calendarSetting->refresh());
    }

    private function baseQuery(): Builder
    {
        return CalendarSetting::withGlobalScope('policy', CalendarSettingPolicy::scope());
    }
}
