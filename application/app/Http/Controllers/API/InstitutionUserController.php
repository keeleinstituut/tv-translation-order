<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionUserListRequest;
use App\Http\Resources\API\InstitutionUserResource;
use App\Models\CachedEntities\InstitutionUser;
use App\Policies\InstitutionUserPolicy;
use Illuminate\Support\Facades\DB;
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
            new OA\QueryParameter(name: 'fullname', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'project_role', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionUserResource::class)]
    public function index(InstitutionUserListRequest $request)
    {
        $params = collect($request->validated());

        $this->authorize('viewAny', [InstitutionUser::class, $params->get('project_role')]);

        $query = $this->getBaseQuery();

        if ($fullName = $params->get('fullname')) {
            $query->where(DB::raw("CONCAT(\"user\"->>'forename', ' ', \"user\"->>'surname')"), 'ILIKE', "%$fullName%");
        }

        if ($projectRole = $params->get('project_role')) {
            $map = collect([
                'manager' => PrivilegeKey::ReceiveAndManageProject,
                'client' => PrivilegeKey::CreateProject,
            ]);

            if ($privilege = $map->get($projectRole)) {
                $query->where('roles', '@>', "[{\"privileges\": [{ \"key\": \"$privilege->value\"}]}]");
            }
        }

        $data = $query
            ->with('vendor')
            ->orderByRaw("CONCAT(\"user\"->>'forename', \"user\"->>'surname') COLLATE \"et-EE-x-icu\" ASC")
            ->paginate($params->get('per_page', 10));

        return InstitutionUserResource::collection($data);
    }

    private function getBaseQuery()
    {
        return InstitutionUser::getModel()->withGlobalScope('policy', InstitutionUserPolicy::scope());
    }
}
