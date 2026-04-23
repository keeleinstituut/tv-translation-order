<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
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

class VendorSkillLanguagePairController extends Controller
{
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

    public function bulkDestroy(VendorSkillLanguagePairBulkDeleteRequest $request): AnonymousResourceCollection
    {
        $ids = collect($request->validated('id'));

        $data = $this->getBaseQuery()
            ->whereIn('id', $ids)
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('skill')
            ->with('vendor')
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
