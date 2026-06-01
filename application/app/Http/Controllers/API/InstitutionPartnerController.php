<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionPartnerBulkCreateRequest;
use App\Http\Requests\API\InstitutionPartnerBulkDeleteRequest;
use App\Http\Requests\API\InstitutionPartnerCreateRequest;
use App\Http\Requests\API\InstitutionPartnerListRequest;
use App\Http\Requests\API\InstitutionPartnerUpdateRequest;
use App\Http\Resources\API\InstitutionPartnerResource;
use App\Models\InstitutionPartner;
use App\Policies\InstitutionPartnerPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InstitutionPartnerController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/institution-partners',
        summary: 'List institution partners of current institution (institution inferred from JWT)',
        tags: ['External partners'],
        parameters: [
            new OA\QueryParameter(name: 'partner_institution_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerResource::class)]
    public function index(InstitutionPartnerListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InstitutionPartner::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery();

        if ($param = $params->get('partner_institution_id')) {
            $query->whereIn('partner_institution_id', $param);
        }

        $sortBy = $params->get('sort_by', 'created_at');
        $sortOrder = $params->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $data = $query->paginate($params->get('per_page', 10));

        return InstitutionPartnerResource::collection($data);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/institution-partners/{id}',
        summary: 'Show an institution partner',
        tags: ['External partners'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionPartnerResource::class, description: 'Institution partner')]
    public function show(string $id): InstitutionPartnerResource
    {
        $this->authorize('viewAny', InstitutionPartner::class);

        $partner = $this->getBaseQuery()->findOrFail($id);

        return InstitutionPartnerResource::make($partner);
    }

    /**
     * @throws Throwable
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/institution-partners',
        summary: 'Create an institution partner',
        requestBody: new OAH\RequestBody(InstitutionPartnerCreateRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionPartnerResource::class, description: 'Created institution partner', response: Response::HTTP_CREATED)]
    public function store(InstitutionPartnerCreateRequest $request): InstitutionPartnerResource
    {
        $partner = new InstitutionPartner();
        $partner->fill(array_merge($request->validated(), ['institution_id' => Auth::user()->institutionId]));
        $this->authorize('create', $partner);
        $partner->saveOrFail();

        return InstitutionPartnerResource::make($partner);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/institution-partners/{id}',
        summary: 'Update an institution partner',
        requestBody: new OAH\RequestBody(InstitutionPartnerUpdateRequest::class),
        tags: ['External partners'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionPartnerResource::class, description: 'Updated institution partner')]
    public function update(InstitutionPartnerUpdateRequest $request, string $id): InstitutionPartnerResource
    {
        $partner = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('update', $partner);
        $partner->fill($request->validated());
        $partner->saveOrFail();

        return InstitutionPartnerResource::make($partner);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Delete(
        path: '/institution-partners/{id}',
        summary: 'Delete an institution partner',
        tags: ['External partners'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionPartnerResource::class, description: 'Deleted institution partner')]
    public function destroy(string $id): InstitutionPartnerResource
    {
        $partner = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('delete', $partner);
        $partner->delete();

        return InstitutionPartnerResource::make($partner);
    }

    /**
     * @throws Throwable
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/institution-partners/bulk',
        summary: 'Bulk create institution partners',
        requestBody: new OAH\RequestBody(InstitutionPartnerBulkCreateRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerResource::class, description: 'Created institution partners', response: Response::HTTP_CREATED)]
    public function bulkCreate(InstitutionPartnerBulkCreateRequest $request): AnonymousResourceCollection
    {
        $inputData = collect($request->validated('data'));

        return DB::transaction(function () use ($inputData): AnonymousResourceCollection {
            $data = $inputData->map(function (array $input): InstitutionPartner {
                $partner = new InstitutionPartner();
                $partner->fill(array_merge($input, ['institution_id' => Auth::user()->institutionId]));
                $this->authorize('create', $partner);
                $partner->saveOrFail();

                return $partner;
            });

            return InstitutionPartnerResource::collection($data);
        });
    }

    /**
     * @throws Throwable
     * @throws AuthorizationException
     */
    #[OA\Delete(
        path: '/institution-partners/bulk',
        summary: 'Bulk delete institution partners',
        requestBody: new OAH\RequestBody(InstitutionPartnerBulkDeleteRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerResource::class, description: 'Deleted institution partners')]
    public function bulkDestroy(InstitutionPartnerBulkDeleteRequest $request): AnonymousResourceCollection
    {
        $ids = collect($request->validated('id'));

        $data = $this->getBaseQuery()->whereIn('id', $ids)->get();

        return DB::transaction(function () use ($data): AnonymousResourceCollection {
            $data->each(function (InstitutionPartner $partner): void {
                $this->authorize('delete', $partner);
                $partner->deleteOrFail();
            });

            return InstitutionPartnerResource::collection($data);
        });
    }

    private function getBaseQuery(): Builder
    {
        return InstitutionPartner::query()->withGlobalScope('policy', InstitutionPartnerPolicy::scope())
            ->with(['partnerInstitution']);
    }
}
