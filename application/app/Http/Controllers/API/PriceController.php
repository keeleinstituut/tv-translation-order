<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\PriceBulkCreateRequest;
use App\Http\Requests\API\PriceBulkDeleteRequest;
use App\Http\Requests\API\PriceBulkUpdateRequest;
use App\Http\Requests\API\PriceCreateRequest;
use App\Http\Requests\API\PriceListRequest;
use App\Http\Resources\API\PriceResource;
use App\Models\Price;
use App\Policies\PricePolicy;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class PriceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/prices',
        summary: 'List prices of current institution (institution inferrred from JWT)',
        tags: ['Vendor management'],
        parameters: [
            new OA\QueryParameter(name: 'vendor_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'src_lang_classifier_value_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'dst_lang_classifier_value_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'institution_user_name', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'skill_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'limit', schema: new OA\Schema(type: 'number', maximum: 10, nullable: true)),
            new OA\QueryParameter(name: 'order_by', schema: new OA\Schema(type: 'string', enum: ['character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee'])),
            new OA\QueryParameter(name: 'order_direction', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: PriceResource::class)]
    public function index(PriceListRequest $request)
    {
        $this->authorize('viewAny', Price::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with('vendor')
            ->with('vendor.institutionUser')
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('skill');

        if ($param = $params->get('vendor_id')) {
            $query = $query->where('vendor_id', $param);
        }

        if ($param = $params->get('src_lang_classifier_value_id')) {
            $query = $query->whereRelation('sourceLanguageClassifierValue', 'id', $param);
        }

        if ($param = $params->get('dst_lang_classifier_value_id')) {
            $query = $query->whereRelation('destinationLanguageClassifierValue', 'id', $param);
        }

        if ($param = $params->get('institution_user_name')) {
            $query = $query->whereRelation('vendor.institutionUser', DB::raw("CONCAT(\"user\"->>'forename', \"user\"->>'surname')"), 'ILIKE', "%$param%");
        }

        if ($param = $params->get('skill_id')) {
            $query = $query->where('skill_id', $param);
        }

        $data = $query
            ->orderBy($params->get('order_by', 'created_at'), $params->get('order_direction', 'desc'))
            ->paginate($params->get('limit', 10));

        return PriceResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PriceCreateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $obj = new Price();
            $obj->fill($params->toArray());
            $obj->save();
            $this->authorize('create', $obj);

            $obj
                ->load('vendor')
                ->load('vendor.institutionUser')
                ->load('sourceLanguageClassifierValue')
                ->load('destinationLanguageClassifierValue')
                ->load('skill');

            return new PriceResource($obj);
        });
    }

    #[OA\Post(
        path: '/prices/bulk',
        summary: 'Bulk create new prices',
        requestBody: new OAH\RequestBody(PriceBulkCreateRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: PriceResource::class, description: 'Created prices', response: Response::HTTP_CREATED)]
    public function bulkStore(PriceBulkCreateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $inputData = collect($params->get('data'));

            $data = $inputData->map(function ($input) {
                $obj = new Price();
                $obj->fill($input);
                $this->authorize('create', $obj);
                $obj->save();

                return $obj;
            });

            $data = Price::getModel()
                ->whereIn('id', $data->pluck('id'))
                ->with('vendor')
                ->with('vendor.institutionUser')
                ->with('sourceLanguageClassifierValue')
                ->with('destinationLanguageClassifierValue')
                ->with('skill')
                ->get();

            return PriceResource::collection($data);
        });
    }

    #[OA\Put(
        path: '/prices/bulk',
        summary: 'Bulk delete prices',
        requestBody: new OAH\RequestBody(PriceBulkUpdateRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: PriceResource::class, description: 'Updated prices')]
    public function bulkUpdate(PriceBulkUpdateRequest $request)
    {
        $params = collect($request->validated());

        $inputData = collect($params->get('data'));
        $ids = $inputData->pluck('id');
        $mappedById = collect($inputData->keyBy('id'));

        $prices = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with('vendor')
            ->with('vendor.institutionUser')
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('skill')
            ->orderBy('created_at', 'asc')
            ->get();

        return DB::transaction(function () use ($mappedById, $prices) {
            $data = collect($prices)->map(function ($price) use ($mappedById) {
                $this->authorize('update', $price);

                $input = $mappedById->get($price->id);
                $price->fill($input);

                $price->save();

                return $price;
            });

            return PriceResource::collection($data);
        });
    }

    #[OA\Delete(
        path: '/prices/bulk',
        summary: 'Bulk delete prices',
        requestBody: new OAH\RequestBody(PriceBulkDeleteRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: PriceResource::class, description: 'Deleted prices')]
    public function bulkDestroy(PriceBulkDeleteRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $ids = collect($params->get('id'));

            $data = $this->getBaseQuery()
                ->whereIn('id', $ids)
                ->with('vendor')
                ->with('vendor.institutionUser')
                ->with('sourceLanguageClassifierValue')
                ->with('destinationLanguageClassifierValue')
                ->with('skill')
                ->orderBy('created_at', 'asc')
                ->get();

            $data->each(function ($obj) {
                $this->authorize('delete', $obj);
                $obj->delete();
            });

            return PriceResource::collection($data);
        });
    }

    private function getBaseQuery()
    {
        return Price::getModel()->withGlobalScope('policy', PricePolicy::scope());
    }
}
