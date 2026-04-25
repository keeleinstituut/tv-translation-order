<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\InstitutionPartnerPriceBulkCreateRequest;
use App\Http\Requests\API\InstitutionPartnerPriceBulkDeleteRequest;
use App\Http\Requests\API\InstitutionPartnerPriceBulkUpdateRequest;
use App\Http\Requests\API\InstitutionPartnerPriceCreateRequest;
use App\Http\Requests\API\InstitutionPartnerPriceListRequest;
use App\Http\Resources\API\InstitutionPartnerPriceResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionPartner;
use App\Models\InstitutionPartnerPrice;
use App\Policies\InstitutionPartnerPricePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InstitutionPartnerPriceController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/institution-partner-prices',
        summary: 'List institution partner prices (institution inferred from JWT)',
        tags: ['External partners'],
        parameters: [
            new OA\QueryParameter(name: 'institution_partner_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
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
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerPriceResource::class)]
    public function index(InstitutionPartnerPriceListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InstitutionPartnerPrice::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with([
                'sourceLanguageClassifierValue',
                'destinationLanguageClassifierValue',
                'skill'
            ]);

        if ($param = $params->get('institution_partner_id')) {
            $query->whereIn('institution_partner_id', $param);
        }

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
                ->select('institution_partner_prices.*')
                ->orderBy('srccv.value', $sortOrder)
                ->orderBy('dstcv.value', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $data = $query->paginate($params->get('per_page', 10));

        return InstitutionPartnerPriceResource::collection($data);
    }

    /**
     * @throws Throwable
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/institution-partner-prices',
        summary: 'Create an institution partner price',
        requestBody: new OAH\RequestBody(InstitutionPartnerPriceCreateRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: InstitutionPartnerPriceResource::class, description: 'Created institution partner price', response: Response::HTTP_CREATED)]
    public function store(InstitutionPartnerPriceCreateRequest $request): InstitutionPartnerPriceResource
    {
        $price = new InstitutionPartnerPrice();
        $price->fill($request->validated());
        $this->authorize('create', $price);
        $price->saveOrFail();

        $price->load([
            'sourceLanguageClassifierValue',
            'destinationLanguageClassifierValue',
            'skill'
        ]);

        return InstitutionPartnerPriceResource::make($price);
    }

    #[OA\Post(
        path: '/institution-partner-prices/bulk',
        summary: 'Bulk create institution partner prices',
        requestBody: new OAH\RequestBody(InstitutionPartnerPriceBulkCreateRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerPriceResource::class, description: 'Created institution partner prices')]
    public function bulkStore(InstitutionPartnerPriceBulkCreateRequest $request): AnonymousResourceCollection
    {
        $inputData = collect($request->validated('data'));

        $institutionPartners = InstitutionPartner::query()
            ->whereIn('id', $inputData->pluck('institution_partner_id')->unique())
            ->get()
            ->keyBy('id');

        return DB::transaction(function () use ($inputData, $institutionPartners): AnonymousResourceCollection {
            $created = $inputData->map(function (array $input) use ($institutionPartners): InstitutionPartnerPrice {
                $price = new InstitutionPartnerPrice();
                $price->fill($input);
                $price->setRelation('institutionPartner', $institutionPartners->get($input['institution_partner_id']));
                $this->authorize('create', $price);
                $price->saveOrFail();

                return $price;
            });

            $data = InstitutionPartnerPrice::query()
                ->whereIn('id', $created->pluck('id'))
                ->with([
                    'sourceLanguageClassifierValue',
                    'destinationLanguageClassifierValue',
                    'skill'
                ])->get();

            return InstitutionPartnerPriceResource::collection($data);
        });
    }

    #[OA\Put(
        path: '/institution-partner-prices/bulk',
        summary: 'Bulk update institution partner prices',
        requestBody: new OAH\RequestBody(InstitutionPartnerPriceBulkUpdateRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerPriceResource::class, description: 'Updated institution partner prices')]
    public function bulkUpdate(InstitutionPartnerPriceBulkUpdateRequest $request): AnonymousResourceCollection
    {
        $inputData = collect($request->validated('data'));
        $ids = $inputData->pluck('id');
        $mappedById = $inputData->keyBy('id');

        $prices = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with(['sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill', 'institutionPartner'])
            ->orderBy('created_at', 'asc')
            ->get();

        return DB::transaction(function () use ($mappedById, $prices): AnonymousResourceCollection {
            $updated = collect($prices)->map(function (InstitutionPartnerPrice $price) use ($mappedById): InstitutionPartnerPrice {
                $this->authorize('update', $price);
                $input = $mappedById->get($price->id);
                $price->fill($input);
                $price->saveOrFail();

                return $price;
            });

            return InstitutionPartnerPriceResource::collection($updated);
        });
    }

    #[OA\Delete(
        path: '/institution-partner-prices/bulk',
        summary: 'Bulk delete institution partner prices',
        requestBody: new OAH\RequestBody(InstitutionPartnerPriceBulkDeleteRequest::class),
        tags: ['External partners'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionPartnerPriceResource::class, description: 'Deleted institution partner prices')]
    public function bulkDestroy(InstitutionPartnerPriceBulkDeleteRequest $request): AnonymousResourceCollection
    {
        $ids = collect($request->validated('id'));

        $data = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with(['sourceLanguageClassifierValue', 'destinationLanguageClassifierValue', 'skill', 'institutionPartner'])
            ->orderBy('created_at', 'asc')
            ->get();

        DB::transaction(function () use ($data): void {
            $data->each(function (InstitutionPartnerPrice $price): void {
                $this->authorize('delete', $price);
                $price->deleteOrFail();
            });
        });

        return InstitutionPartnerPriceResource::collection($data);
    }

    private function getBaseQuery(): Builder
    {
        return InstitutionPartnerPrice::query()->withGlobalScope('policy', InstitutionPartnerPricePolicy::scope());
    }
}
