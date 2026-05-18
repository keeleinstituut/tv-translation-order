<?php

namespace App\Http\Controllers\API;

use App\Enums\InstitutionType;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionListRequest;
use App\Http\Resources\API\InstitutionResource;
use App\Models\CachedEntities\Institution;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class InstitutionController extends Controller
{
    #[OA\Get(
        path: '/institutions',
        summary: 'List institutions (cached entity); supports filters used by partner-creation UI',
        tags: ['Institutions'],
        parameters: [
            new OA\QueryParameter(name: 'name', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'type', schema: new OA\Schema(type: 'string', enum: InstitutionType::class, nullable: true)),
            new OA\QueryParameter(name: 'not_partner_of_current_institution', schema: new OA\Schema(type: 'boolean', default: false, nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'name', enum: ['name'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'asc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionResource::class)]
    /**
     * @throws AuthorizationException
     */
    public function index(InstitutionListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Institution::class);

        $params = collect($request->validated());
        $query = Institution::query();

        if ($name = $params->get('name')) {
            $query->where('name', 'ILIKE', "%{$name}%");
        }

        if ($type = $params->get('type')) {
            $query->where('type', InstitutionType::from($type));
        }

        if ($request->boolean('not_partner_of_current_institution')) {
            $currentInstitutionId = Auth::user()->institutionId;
            $query
                ->where('id', '!=', $currentInstitutionId)
                ->whereDoesntHave(
                    'partnerOf',
                    fn (Builder $q) => $q->where('institution_id', $currentInstitutionId),
                );
        }

        $sortBy = $params->get('sort_by', 'name');
        $sortOrder = $params->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        return InstitutionResource::collection(
            $query->paginate($params->get('per_page', 10))
        );
    }
}
