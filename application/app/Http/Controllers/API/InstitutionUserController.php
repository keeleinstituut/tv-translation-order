<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Helpers\DateUtil;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionUserListRequest;
use App\Http\Requests\API\PinLanguageRequest;
use App\Http\Resources\API\InstitutionUserPinnedLanguageResource;
use App\Http\Resources\API\InstitutionUserResource;
use App\Http\Resources\API\VendorResource;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\InstitutionUserPinnedLanguage;
use App\Models\Vendor;
use App\Policies\InstitutionUserPinnedLanguagePolicy;
use App\Policies\InstitutionUserPolicy;
use App\Policies\VendorPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Throwable;

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

        $query->whereNull('archived_at')->where(
            fn($groupedClause) => $groupedClause
                ->whereNull('deactivation_date')
                ->orWhereDate('deactivation_date', '>', Date::now(DateUtil::TIMEZONE)->format('Y-m-d'))
        );

        if ($projectRole = $params->get('project_role')) {
            $map = collect([
                'manager' => PrivilegeKey::ReceiveProject,
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

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/institution-users/pinned-languages',
        summary: 'Pin a language for the current user',
        requestBody: new OAH\RequestBody(PinLanguageRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionUserPinnedLanguageResource::class, description: 'Pinned language')]
    public function pinLanguage(PinLanguageRequest $request): JsonResource
    {
        $this->authorize('create', InstitutionUserPinnedLanguage::class);

        $institutionUserId = Auth::user()->institutionUserId;
        $mainLanguageId = $request->validated('institution_main_language_id');

        $pinnedLanguage = InstitutionUserPinnedLanguage::firstOrCreate([
            'institution_user_id' => $institutionUserId,
            'institution_main_language_id' => $mainLanguageId,
        ]);

        $pinnedLanguage->load('mainLanguage.language');

        return InstitutionUserPinnedLanguageResource::make($pinnedLanguage);
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/institution-users/pinned-languages',
        summary: 'Unpin a language for the current user',
        requestBody: new OAH\RequestBody(PinLanguageRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    public function unpinLanguage(PinLanguageRequest $request): JsonResponse
    {
        $mainLanguageId = $request->validated('institution_main_language_id');

        $pinnedLanguage = InstitutionUserPinnedLanguage::withGlobalScope('policy', InstitutionUserPinnedLanguagePolicy::scope())
            ->where('institution_main_language_id', $mainLanguageId)
            ->firstOrFail();

        $this->authorize('delete', $pinnedLanguage);

        $pinnedLanguage->deleteOrFail();

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/institution-users/{institution_user_id}/vendor',
        summary: 'Get vendor by institution user ID',
        tags: ['Vendor management'],
        parameters: [new OAH\UuidPath('institution_user_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: VendorResource::class, description: 'Vendor resource')]
    public function vendor(Request $request): VendorResource
    {
        $vendor = Vendor::getModel()
            ->withGlobalScope('policy', VendorPolicy::scope())
            ->with(['tags'])
            ->where('institution_user_id', $request->route('institution_user_id'))
            ->firstOrFail();

        $this->authorize('view', $vendor);

        return VendorResource::make($vendor);
    }

    private function getBaseQuery(): Builder
    {
        return InstitutionUser::getModel()->withGlobalScope('policy', InstitutionUserPolicy::scope());
    }
}
