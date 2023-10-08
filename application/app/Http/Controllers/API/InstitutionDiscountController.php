<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\InstitutionDiscountCreateUpdateRequest;
use App\Http\Resources\API\InstitutionDiscountResource;
use App\Models\CachedEntities\Institution;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use App\Http\OpenApiHelpers as OAH;

class InstitutionDiscountController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/institution-discounts',
        tags: ['Institution management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionDiscountResource::class, description: 'Discounts of the institution.')]
    #[OA\Response(
        response: \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT,
        description: 'Institution has no discounts'
    )]
    public function show(): Response|InstitutionDiscountResource
    {
        Gate::allowIf(Auth::hasPrivilege(PrivilegeKey::EditInstitutionPriceRate->value));
        if (empty($institutionDiscount = self::getInstitution()->institutionDiscount)) {
            return response()->noContent();
        }
        return InstitutionDiscountResource::make($institutionDiscount);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Put(
        path: '/institution-discounts',
        requestBody: new OAH\RequestBody(InstitutionDiscountCreateUpdateRequest::class),
        tags: ['Institution management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionDiscountResource::class, description: 'Creates or updates institution discounts')]
    public function store(InstitutionDiscountCreateUpdateRequest $request): InstitutionDiscountResource
    {
        Gate::allowIf(Auth::hasPrivilege(PrivilegeKey::EditInstitutionPriceRate->value));

        $institutionDiscount = self::getInstitution()->institutionDiscount()
            ->updateOrCreate($request->validated());

        return InstitutionDiscountResource::make($institutionDiscount);
    }

    private static function getInstitution(): Institution
    {
        if (!$institution = Institution::find(Auth::user()->institutionId)) {
            abort(401);
        }

        return $institution;
    }
}