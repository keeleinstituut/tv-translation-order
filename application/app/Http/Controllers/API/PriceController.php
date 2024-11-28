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
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Price;
use App\Models\Vendor;
use App\Policies\PricePolicy;
use Illuminate\Support\Collection;
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
            new OA\QueryParameter(name: 'institution_user_name', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'src_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'dst_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'skill_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee', 'created_at', 'lang_pair'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
            new OA\QueryParameter(
                name: 'lang_pair[]',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'src', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'dst', type: 'string', format: 'uuid'),
                        ]
                    ),
                    nullable: true
                )
            ),
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
            ->with('vendor.tags')
            ->with('vendor.institutionUser')
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('skill');

        if ($param = $params->get('vendor_id')) {
            $query = $query->where('vendor_id', $param);
        }

        if ($param = $params->get('src_lang_classifier_value_id')) {
            $query = $query->whereRelation('sourceLanguageClassifierValue', function ($query) use ($param) {
                $query->whereIn('id', $param);
            });
        }

        if ($param = $params->get('dst_lang_classifier_value_id')) {
            $query = $query->whereRelation('destinationLanguageClassifierValue', function ($query) use ($param) {
                $query->whereIn('id', $param);
            });
        }

        if ($param = $params->get('institution_user_name')) {
            $query = $query->whereRelation('vendor.institutionUser', DB::raw("CONCAT(\"user\"->>'forename', ' ', \"user\"->>'surname')"), 'ILIKE', "%$param%");
        }

        if ($param = $params->get('skill_id')) {
            $query = $query->whereIn('skill_id', $param);
        }

        if ($param = $params->get('lang_pair')) {
            $query->where(function ($query) use ($param) {
                collect($param)->each(function ($langPair) use ($query) {
                    $query->orWhere(function ($query) use ($langPair) {
                        $query
                            ->where('src_lang_classifier_value_id', $langPair['src'])
                            ->where('dst_lang_classifier_value_id', $langPair['dst']);
                    });
                });
            });
        }

        $sortBy = $params->get('sort_by', 'created_at');
        $sortOrder = $params->get('sort_order', 'desc');

        if ($sortBy == 'lang_pair') {
            $query->join(app(ClassifierValue::class)->getTable() . ' as srccv', 'src_lang_classifier_value_id', '=', 'srccv.id')
                ->join(app(ClassifierValue::class)->getTable() . ' as dstcv', 'dst_lang_classifier_value_id', '=', 'dstcv.id')
                ->select('prices.*')
                ->orderBy('srccv.value', $sortOrder)
                ->orderBy('dstcv.value', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $minCreatedAt = $query->min('prices.created_at');
        $maxUpdatedAt = $query->max('prices.updated_at');

        $data = $query->paginate($params->get('per_page', 10));
        $resource = PriceResource::collection($data);

        $resource->additional([
            'aggregation' => [
                'min_created_at' => $minCreatedAt,
                'max_updated_at' => $maxUpdatedAt,
            ],
        ]);

        return $resource;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PriceCreateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $vendor = Vendor::findOrFail($params['vendor_id']);

            $obj = $this->auditLogPublisher->publishModifyObjectAfterAction(
                $vendor,
                function () use ($params): Price {
                    $obj = new Price();
                    $obj->fill($params->toArray());
                    $this->authorize('create', $obj);
                    $obj->saveOrFail();

                    return $obj;
                }
            );

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
        $inputData = collect($request->validated('data'));

        return DB::transaction(function () use ($inputData) {
            $affectedVendors = $inputData->pluck('vendor_id')->unique()->map(fn (string $id) => Vendor::findOrFail($id));
            $data = $this->auditLogPublisher->publishModifyObjectsAfterAction(
                $affectedVendors,
                function () use ($inputData): Collection {
                    return $inputData->map(function ($input) {
                        $obj = new Price();
                        $obj->fill($input);
                        $this->authorize('create', $obj);
                        $obj->saveOrFail();

                        return $obj;
                    });
                }
            );

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
            $affectedVendors = $prices->pluck('vendor_id')->unique()->map(fn (string $id) => Vendor::findOrFail($id));
            $data = $this->auditLogPublisher->publishModifyObjectsAfterAction(
                $affectedVendors,
                function () use ($mappedById, $prices): Collection {
                    return collect($prices)->map(function ($price) use ($mappedById) {
                        $this->authorize('update', $price);

                        $input = $mappedById->get($price->id);
                        $price->fill($input);

                        $price->saveOrFail();

                        return $price;
                    });
                }
            );

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

            $affectedVendors = $data->pluck('vendor_id')->unique()->map(fn (string $id) => Vendor::findOrFail($id));
            $this->auditLogPublisher->publishModifyObjectsAfterAction(
                $affectedVendors,
                function () use ($data): void {
                    $data->each(function ($obj) {
                        $this->authorize('delete', $obj);
                        $obj->deleteOrFail();
                    });
                }
            );

            return PriceResource::collection($data);
        });
    }

    private function getBaseQuery()
    {
        return Price::getModel()->withGlobalScope('policy', PricePolicy::scope());
    }
}
