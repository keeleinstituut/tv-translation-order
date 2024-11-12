<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionDiscountCreateUpdateRequest;
use App\Http\Resources\API\InstitutionDiscountResource;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionDiscount;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;
use Throwable;

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
        Gate::allowIf(Auth::hasPrivilege(PrivilegeKey::ViewInstitutionPriceRate->value));
        if (empty($institutionDiscount = self::getInstitution()->institutionDiscount)) {
            return response()->noContent();
        }

        return InstitutionDiscountResource::make($institutionDiscount);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
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

        $institution = self::getInstitution();
        /** @var InstitutionDiscount|null $institutionDiscount */
        $institutionDiscount = $institution->institutionDiscount()->firstOrNew();

        $saveInstitutionDiscount = function (InstitutionDiscount $institutionDiscount) use ($institution, $request): void {
            $institutionDiscount->fill([
                ...$request->validated(),
                'institution_id' => $institution->id,
            ]);
            $institutionDiscount->saveOrFail();
        };

        if ($institutionDiscount->exists()) {
            $this->auditLogPublisher->publishModifyObjectAfterAction($institutionDiscount, $saveInstitutionDiscount);
        } else {
            $saveInstitutionDiscount($institutionDiscount);
            $this->auditLogPublisher->publishCreateObject($institutionDiscount);
        }

        return InstitutionDiscountResource::make($institutionDiscount->refresh());
    }

    private static function getInstitution(): Institution
    {
        return Institution::findOrFail(Auth::user()->institutionId);
    }
}
