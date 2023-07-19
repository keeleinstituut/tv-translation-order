<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionUserListRequest;
use App\Http\Resources\API\InstitutionUserResource;
use App\Models\CachedEntities\InstitutionUser;
use App\Policies\InstitutionUserPolicy;
use OpenApi\Attributes as OA;

class InstitutionUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/institution-users',
        summary: 'List Institution Users',
        tags: ['Cached entities'],
        parameters: [
            new OA\QueryParameter(name: 'limit', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionUserResource::class)]
    public function index(InstitutionUserListRequest $request)
    {
        $params = collect($request->validated());

        $this->authorize('viewAny', InstitutionUser::class);

        $query = $this->getBaseQuery();
        $data = $query
            ->orderByRaw("CONCAT(\"user\"->>'forename', \"user\"->>'surname') ASC")
            ->paginate($params->get('limit', 10));

        return InstitutionUserResource::collection($data);
    }

    private function getBaseQuery()
    {
        return InstitutionUser::getModel()->withGlobalScope('policy', InstitutionUserPolicy::scope());
    }
}
