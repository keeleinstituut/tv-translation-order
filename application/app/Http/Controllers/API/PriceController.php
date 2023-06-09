<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\PriceBulkCreateRequest;
use App\Http\Requests\API\PriceBulkDeleteRequest;
use App\Http\Requests\API\PriceBulkUpdateRequest;
use App\Http\Requests\API\PriceListRequest;
use App\Http\Requests\API\PriceCreateRequest;
use App\Http\Resources\API\PriceResource;
use App\Policies\PricePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Price;

class PriceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(PriceListRequest $request)
    {
         $this->authorize('viewAny', Price::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with('vendor')
            ->with('vendor.institutionUser')
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue');

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
            $query = $query->whereRelation('vendor.institutionUser', DB::raw("CONCAT(forename, ' ', surname)"), 'ILIKE', "%$param%");
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
                ->load('destinationLanguageClassifierValue');

            return new PriceResource($obj);
        });
    }

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
                ->get();

            return PriceResource::collection($data);
        });
    }

    /**
     * Display the specified resource.
     */
    // public function show(string $id)
    // {
    //     //
    // }

    /**
     * Update the specified resource in storage.
     */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

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
