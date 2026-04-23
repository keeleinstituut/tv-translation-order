<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorSkillLanguagePairBulkCreateRequest;
use App\Http\Requests\API\VendorSkillLanguagePairBulkDeleteRequest;
use App\Http\Requests\API\VendorSkillLanguagePairListRequest;
use App\Http\Resources\API\VendorSkillLanguagePairResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\VendorSkillLanguagePair;
use App\Policies\VendorSkillLanguagePairPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class VendorSkillLanguagePairController extends Controller
{
    #[OA\Get(
        path: '/vendor-skill-language-pairs',
        summary: 'List vendor skill language pairs of current institution (institution inferred from JWT)',
        tags: ['Vendor management'],
        parameters: [
            new OA\QueryParameter(name: 'vendor_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
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
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at', 'lang_pair'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguagePairResource::class)]
    public function index(VendorSkillLanguagePairListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorSkillLanguagePair::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('skill')
            ->with('vendor');

        if ($param = $params->get('vendor_id')) {
            $query->whereIn('vendor_id', $param);
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
                ->select('vendor_skill_language_pairs.*')
                ->orderBy('srccv.value', $sortOrder)
                ->orderBy('dstcv.value', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $data = $query->paginate($params->get('per_page', 10));

        return VendorSkillLanguagePairResource::collection($data);
    }

    #[OA\Post(
        path: '/vendor-skill-language-pairs/bulk',
        summary: 'Bulk create vendor skill language pairs',
        requestBody: new OAH\RequestBody(VendorSkillLanguagePairBulkCreateRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguagePairResource::class, description: 'Created vendor skill language pairs')]
    public function bulkStore(VendorSkillLanguagePairBulkCreateRequest $request): AnonymousResourceCollection
    {
        $inputData = collect($request->validated('data'));

        return DB::transaction(function () use ($inputData): AnonymousResourceCollection {
            $created = $inputData->map(function (array $input): VendorSkillLanguagePair {
                $pair = new VendorSkillLanguagePair();
                $pair->fill($input);
                $this->authorize('create', $pair);
                $pair->saveOrFail();

                return $pair;
            });

            $data = VendorSkillLanguagePair::query()
                ->whereIn('id', $created->pluck('id'))
                ->with('sourceLanguageClassifierValue')
                ->with('destinationLanguageClassifierValue')
                ->with('skill')
                ->with('vendor')
                ->get();

            return VendorSkillLanguagePairResource::collection($data);
        });
    }

    #[OA\Delete(
        path: '/vendor-skill-language-pairs/bulk',
        summary: 'Bulk delete vendor skill language pairs',
        requestBody: new OAH\RequestBody(VendorSkillLanguagePairBulkDeleteRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguagePairResource::class, description: 'Deleted vendor skill language pairs')]
    public function bulkDestroy(VendorSkillLanguagePairBulkDeleteRequest $request): AnonymousResourceCollection
    {
        $ids = collect($request->validated('id'));

        $data = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('skill')
            ->with('vendor.institutionUser')
            ->orderBy('created_at', 'asc')
            ->get();

        DB::transaction(function () use ($data): void {
            $data->each(function (VendorSkillLanguagePair $pair): void {
                $this->authorize('delete', $pair);
                $pair->deleteOrFail();
            });
        });

        return VendorSkillLanguagePairResource::collection($data);
    }

    private function getBaseQuery(): Builder
    {
        return VendorSkillLanguagePair::query()->withGlobalScope('policy', VendorSkillLanguagePairPolicy::scope());
    }
}
