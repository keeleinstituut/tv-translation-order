<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionPriceBulkCreateRequest;
use App\Http\Requests\API\InstitutionPriceBulkDeleteRequest;
use App\Http\Requests\API\InstitutionPriceBulkUpdateRequest;
use App\Http\Requests\API\InstitutionPriceListRequest;
use App\Http\Resources\API\InstitutionPriceResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionPrice;
use App\Policies\InstitutionPricePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class InstitutionPriceController extends Controller
{
    #[OA\Get(
        path: '/institution-prices',
        summary: 'List institution prices (institution inferred from JWT)',
        tags: ['Institution pricing'],
        parameters: [
            new OA\QueryParameter(name: 'src_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'dst_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'skill_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
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
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee', 'created_at', 'lang_pair'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPriceResource::class)]
    public function index(InstitutionPriceListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InstitutionPrice::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with(['sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill']);

        if ($param = $params->get('src_lang_classifier_value_id')) {
            $query->whereIn('src_lang_classifier_value_id', $param);
        }

        if ($param = $params->get('dst_lang_classifier_value_id')) {
            $query->whereIn('dst_lang_classifier_value_id', $param);
        }

        if ($param = $params->get('skill_id')) {
            $query->whereIn('skill_id', $param);
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

        if ($sortBy === 'lang_pair') {
            $query->join(app(ClassifierValue::class)->getTable().' as srccv', 'src_lang_classifier_value_id', '=', 'srccv.id')
                ->join(app(ClassifierValue::class)->getTable().' as dstcv', 'dst_lang_classifier_value_id', '=', 'dstcv.id')
                ->select('institution_prices.*')
                ->orderBy('srccv.value', $sortOrder)
                ->orderBy('dstcv.value', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $data = $query->paginate($params->get('per_page', 10));

        return InstitutionPriceResource::collection($data);
    }

    #[OA\Post(
        path: '/institution-prices/bulk',
        summary: 'Bulk create institution prices',
        requestBody: new OAH\RequestBody(InstitutionPriceBulkCreateRequest::class),
        tags: ['Institution pricing'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPriceResource::class, description: 'Created institution prices', response: Response::HTTP_CREATED)]
    public function bulkStore(InstitutionPriceBulkCreateRequest $request): AnonymousResourceCollection
    {
        $inputData = collect($request->validated('data'));
        $institutionId = Auth::user()->institutionId;

        return DB::transaction(function () use ($inputData, $institutionId): AnonymousResourceCollection {
            $created = $inputData->map(function (array $input) use ($institutionId): InstitutionPrice {
                $price = new InstitutionPrice();
                $price->fill(array_merge($input, ['institution_id' => $institutionId]));
                $this->authorize('create', $price);
                $price->saveOrFail();

                return $price;
            });

            $data = InstitutionPrice::query()
                ->whereIn('id', $created->pluck('id'))
                ->with([
                    'sourceLanguageClassifierValue',
                    'destinationLanguageClassifierValue',
                    'skill'
                ])->get();

            return InstitutionPriceResource::collection($data);
        });
    }

    #[OA\Put(
        path: '/institution-prices/bulk',
        summary: 'Bulk update institution prices',
        requestBody: new OAH\RequestBody(InstitutionPriceBulkUpdateRequest::class),
        tags: ['Institution pricing'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPriceResource::class, description: 'Updated institution prices')]
    public function bulkUpdate(InstitutionPriceBulkUpdateRequest $request): AnonymousResourceCollection
    {
        $inputData = collect($request->validated('data'));
        $ids = $inputData->pluck('id');
        $mappedById = $inputData->keyBy('id');

        $prices = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with(['sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill'])
            ->orderBy('created_at', 'asc')
            ->get();

        return DB::transaction(function () use ($mappedById, $prices): AnonymousResourceCollection {
            $updated = collect($prices)->map(function (InstitutionPrice $price) use ($mappedById): InstitutionPrice {
                $this->authorize('update', $price);
                $input = $mappedById->get($price->id);
                $price->fill($input);
                $price->saveOrFail();

                return $price;
            });

            return InstitutionPriceResource::collection($updated);
        });
    }

    #[OA\Delete(
        path: '/institution-prices/bulk',
        summary: 'Bulk delete institution prices',
        requestBody: new OAH\RequestBody(InstitutionPriceBulkDeleteRequest::class),
        tags: ['Institution pricing'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPriceResource::class, description: 'Deleted institution prices')]
    public function bulkDestroy(InstitutionPriceBulkDeleteRequest $request): AnonymousResourceCollection
    {
        $ids = collect($request->validated('id'));

        $data = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with(['sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill'])
            ->orderBy('created_at', 'asc')
            ->get();

        DB::transaction(function () use ($data): void {
            $data->each(function (InstitutionPrice $price): void {
                $this->authorize('delete', $price);
                $price->deleteOrFail();
            });
        });

        return InstitutionPriceResource::collection($data);
    }

    private function getBaseQuery(): Builder
    {
        return InstitutionPrice::query()->withGlobalScope('policy', InstitutionPricePolicy::scope());
    }
}
