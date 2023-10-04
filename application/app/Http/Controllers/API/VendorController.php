<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorBulkCreateRequest;
use App\Http\Requests\API\VendorBulkDeleteRequest;
use App\Http\Requests\API\VendorListRequest;
use App\Http\Requests\API\VendorUpdateRequest;
use App\Http\Resources\API\VendorResource;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/vendors',
        summary: 'List vendors of current institution (institution inferrred from JWT)',
        tags: ['Vendor management'],
        parameters: [
            new OA\QueryParameter(name: 'fullname', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'role_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'tag_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'src_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'dst_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'limit', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorResource::class)]
    public function index(VendorListRequest $request)
    {
        $this->authorize('viewAny', Vendor::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with('prices')
            ->with('prices.sourceLanguageClassifierValue')
            ->with('prices.destinationLanguageClassifierValue')
            ->with('tags')
            ->with('institutionUser');

        if ($param = $params->get('fullname')) {
            $query = $query->whereRelation('institutionUser', function ($query) use ($param) {
                $query->where(DB::raw("CONCAT(\"user\"->>'forename', ' ', \"user\"->>'surname')"), 'ILIKE', "%$param%");
            });
        }

        if ($param = $params->get('role_id')) {
            $query = $query->whereRelation('institutionUser', function ($query) use ($param) {
                // Constructs SQL OR statement to check roleId existence in roles array
                $constructValue = fn ($value) => "[{ \"id\": \"$value\"}]";
                $roleIds = collect($param);
                $query->where('roles', '@>', $constructValue($roleIds->shift()));

                collect($roleIds)->each(function ($roleId) use ($query, $constructValue) {
                    $query->orWhere('roles', '@>', $constructValue($roleId));
                });
            });
        }

        if ($param = $params->get('tag_id')) {
            $query = $query->whereRelation('tags', function ($query) use ($param) {
                $query->whereIn('tags.id', $param);
            });
        }

        if ($param = $params->get('src_lang_classifier_value_id')) {
            $query = $query->whereRelation('prices.sourceLanguageClassifierValue', function ($query) use ($param) {
                $query->whereIn('id', $param);
            });
        }

        if ($param = $params->get('dst_lang_classifier_value_id')) {
            $query = $query->whereRelation('prices.destinationLanguageClassifierValue', function ($query) use ($param) {
                $query->whereIn('id', $param);
            });
        }

        $data = $query
            ->join('entity_cache.cached_institution_users', 'vendors.institution_user_id', '=', 'entity_cache.cached_institution_users.id')
            ->orderByRaw("CONCAT(\"user\"->>'forename', \"user\"->>'surname') ASC")
            ->select('vendors.*')
            ->paginate($params->get('limit', 10));

        return VendorResource::collection($data);
    }

    /**
     * Update the specified resource in storage.
     */
    #[OA\Put(
        path: '/vendors/{id}',
        summary: 'Update existing vendor',
        requestBody: new OAH\RequestBody(VendorUpdateRequest::class),
        tags: ['Vendor management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VendorResource::class, description: 'Updated vendor', response: Response::HTTP_CREATED)]
    public function update(VendorUpdateRequest $request)
    {
        $id = $request->route('id');
        $params = collect($request->validated());

        $vendor = $this->getBaseQuery()->find($id) ?? abort(404);
        $this->authorize('update', $vendor);

        return DB::transaction(function () use ($vendor, $params) {
            // Collect certain keys from input params, filter null values
            // and fill model with result from filter
            tap(collect($params)->only([
                'comment',
                'company_name',
                'discount_percentage_101',
                'discount_percentage_repetitions',
                'discount_percentage_100',
                'discount_percentage_95_99',
                'discount_percentage_85_94',
                'discount_percentage_75_84',
                'discount_percentage_50_74',
                'discount_percentage_0_49',
            ])->filter()->toArray(), $vendor->fill(...));

            $vendor->save();

            if ($tagsInput = $params->get('tags')) {
                $vendor->tags()->delete();
                $vendor->tags()->attach($tagsInput);
            }

            $vendor->load('institutionUser', 'tags');

            return new VendorResource($vendor);
        });
    }

    #[OA\Get(
        path: '/vendors/{id}',
        summary: 'Get existing vendor',
        tags: ['Vendor management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\ResourceResponse(dataRef: VendorResource::class, description: 'Vendor resource', response: Response::HTTP_OK)]
    public function show(Request $request): VendorResource
    {
        $vendor = $this->getBaseQuery()->findOrFail($request->route('id'));
        $this->authorize('view', $vendor);
        return new VendorResource($vendor);
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Post(
        path: '/vendors/bulk',
        summary: 'Bulk create new vendors',
        requestBody: new OAH\RequestBody(VendorBulkCreateRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorResource::class, description: 'Created vendors', response: Response::HTTP_CREATED)]
    public function bulkCreate(VendorBulkCreateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $inputData = collect($params->get('data'));

            $data = $inputData->map(function ($input) {
                $vendor = new Vendor();
                $vendor->fill($input);
                $this->authorize('create', $vendor);

                $vendor->save();

                return $vendor;
            });

            return VendorResource::collection($data);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    #[OA\Delete(
        path: '/vendors/bulk',
        summary: 'Bulk delete vendors',
        requestBody: new OAH\RequestBody(VendorBulkDeleteRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorResource::class, description: 'Deleted vendors')]
    public function bulkDestroy(VendorBulkDeleteRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $ids = collect($params->get('id'));

            $data = $this->getBaseQuery()
                ->whereIn('id', $ids)
                ->with('prices')
                ->get();

            $data->each(function ($vendor) {
                $this->authorize('delete', $vendor);
                $vendor->delete();
            });

            return VendorResource::collection($data);
        });
    }

    private function getBaseQuery()
    {
        return Vendor::getModel()->withGlobalScope('policy', VendorPolicy::scope())
            ->with('institutionUser');
    }
}
