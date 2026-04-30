<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorSkillLanguageBulkCreateRequest;
use App\Http\Requests\API\VendorSkillLanguageBulkDeleteRequest;
use App\Http\Requests\API\VendorSkillLanguageBulkUpdateRequest;
use App\Http\Requests\API\VendorSkillLanguageCreateRequest;
use App\Http\Requests\API\VendorSkillLanguageListRequest;
use App\Http\Resources\API\VendorSkillLanguageResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\VendorSkillLanguage;
use App\Policies\VendorSkillLanguagePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class VendorSkillLanguageController extends Controller
{
    #[OA\Get(
        path: '/vendor-skill-languages',
        summary: 'List vendor skill-language relations of current institution (institution inferred from JWT)',
        tags: ['Vendor management'],
        parameters: [
            new OA\QueryParameter(name: 'vendor_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'institution_user_name', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'src_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'dst_lang_classifier_value_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'skill_id[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at', 'lang_pair'])),
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
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguageResource::class)]
    public function index(VendorSkillLanguageListRequest $request)
    {
        $this->authorize('viewAny', VendorSkillLanguage::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with([
                'vendor',
                'vendor.tags',
                'vendor.institutionUser',
                'sourceLanguageClassifierValue',
                'destinationLanguageClassifierValue',
                'skill',
            ]);

        if ($param = $params->get('vendor_id')) {
            $query->where('vendor_id', $param);
        }

        if ($param = $params->get('src_lang_classifier_value_id')) {
            $query->whereIn('src_lang_classifier_value_id', $param);
        }

        if ($param = $params->get('dst_lang_classifier_value_id')) {
            $query->whereIn('dst_lang_classifier_value_id', $param);
        }

        if ($param = $params->get('institution_user_name')) {
            $query->whereRelation('vendor.institutionUser', DB::raw("CONCAT(\"user\"->>'forename', ' ', \"user\"->>'surname')"), 'ILIKE', "%$param%");
        }

        if ($param = $params->get('skill_id')) {
            $query->whereIn('skill_id', $param);
        }

        if ($param = $params->get('lang_pair')) {
            $query->where(function (Builder $query) use ($param) {
                collect($param)->each(function ($langPair) use ($query) {
                    $query->orWhere(function (Builder $query) use ($langPair) {
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
            $query->join(app(ClassifierValue::class)->getTable() . ' as srccv', 'src_lang_classifier_value_id', '=', 'srccv.id')
                ->join(app(ClassifierValue::class)->getTable() . ' as dstcv', 'dst_lang_classifier_value_id', '=', 'dstcv.id')
                ->select('vendor_skill_languages.*')
                ->orderBy('srccv.value', $sortOrder)
                ->orderBy('dstcv.value', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $minCreatedAt = $query->min('vendor_skill_languages.created_at');
        $maxUpdatedAt = $query->max('vendor_skill_languages.updated_at');

        $data = $query->paginate($params->get('per_page', 10));
        $resource = VendorSkillLanguageResource::collection($data);

        $resource->additional([
            'aggregation' => [
                'min_created_at' => $minCreatedAt,
                'max_updated_at' => $maxUpdatedAt,
            ],
        ]);

        return $resource;
    }

    public function store(VendorSkillLanguageCreateRequest $request): VendorSkillLanguageResource
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $obj = new VendorSkillLanguage();
            $obj->fill($params->toArray());
            $this->authorize('create', $obj);
            $obj->saveOrFail();

            $obj->load([
                'vendor',
                'vendor.institutionUser',
                'sourceLanguageClassifierValue',
                'destinationLanguageClassifierValue',
                'skill',
            ]);

            return VendorSkillLanguageResource::make($obj);
        });
    }

    #[OA\Post(
        path: '/vendor-skill-languages/bulk',
        summary: 'Bulk create new vendor skill-language relations',
        requestBody: new OAH\RequestBody(VendorSkillLanguageBulkCreateRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguageResource::class, description: 'Created relations', response: Response::HTTP_CREATED)]
    public function bulkStore(VendorSkillLanguageBulkCreateRequest $request)
    {
        $inputData = collect($request->validated('data'));

        return DB::transaction(function () use ($inputData) {
            $created = $inputData->map(function ($input) {
                $obj = new VendorSkillLanguage();
                $obj->fill($input);
                $this->authorize('create', $obj);
                $obj->saveOrFail();

                return $obj;
            });

            $data = VendorSkillLanguage::query()
                ->whereIn('id', $created->pluck('id'))
                ->with([
                    'vendor',
                    'vendor.institutionUser',
                    'sourceLanguageClassifierValue',
                    'destinationLanguageClassifierValue',
                    'skill',
                ])
                ->get();

            return VendorSkillLanguageResource::collection($data);
        });
    }

    #[OA\Put(
        path: '/vendor-skill-languages/bulk',
        summary: 'Bulk update vendor skill-language relations',
        requestBody: new OAH\RequestBody(VendorSkillLanguageBulkUpdateRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguageResource::class, description: 'Updated relations')]
    public function bulkUpdate(VendorSkillLanguageBulkUpdateRequest $request)
    {
        $inputData = collect($request->validated('data'));
        $mappedById = $inputData->keyBy('id');

        $rows = $this->getBaseQuery()
            ->whereIn('id', $inputData->pluck('id'))
            ->with([
                'vendor',
                'vendor.institutionUser',
                'sourceLanguageClassifierValue',
                'destinationLanguageClassifierValue',
                'skill',
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return DB::transaction(function () use ($mappedById, $rows): AnonymousResourceCollection {
            $updated = $rows->map(function (VendorSkillLanguage $row) use ($mappedById) {
                $this->authorize('update', $row);
                $row->fill($mappedById->get($row->id));
                $row->saveOrFail();

                return $row;
            });

            return VendorSkillLanguageResource::collection($updated);
        });
    }

    #[OA\Delete(
        path: '/vendor-skill-languages/bulk',
        summary: 'Bulk delete vendor skill-language relations',
        requestBody: new OAH\RequestBody(VendorSkillLanguageBulkDeleteRequest::class),
        tags: ['Vendor management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorSkillLanguageResource::class, description: 'Deleted relations')]
    public function bulkDestroy(VendorSkillLanguageBulkDeleteRequest $request)
    {
        $ids = collect($request->validated('id'));

        return DB::transaction(function () use ($ids) {
            $rows = $this->getBaseQuery()
                ->whereIn('id', $ids)
                ->with([
                    'vendor',
                    'vendor.institutionUser',
                    'sourceLanguageClassifierValue',
                    'destinationLanguageClassifierValue',
                    'skill',
                ])
                ->orderBy('created_at', 'asc')
                ->get();

            $rows->each(function (VendorSkillLanguage $row) {
                $this->authorize('delete', $row);
                $row->deleteOrFail();
            });

            return VendorSkillLanguageResource::collection($rows);
        });
    }

    private function getBaseQuery(): Builder
    {
        return VendorSkillLanguage::query()->withGlobalScope('policy', VendorSkillLanguagePolicy::scope());
    }
}
