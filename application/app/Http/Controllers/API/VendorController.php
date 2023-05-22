<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\API\VendorListRequest;
use App\Http\Requests\API\VendorBulkCreateRequest;
use App\Http\Requests\API\VendorBulkDeleteRequest;
use App\Http\Resources\API\VendorResource;
use App\Models\Vendor;
use App\Policies\VendorPolicy;

class VendorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(VendorListRequest $request)
    {
        $this->authorize('viewAny', Vendor::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery();
        $data = $query->get();

        return VendorResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function bulkCreate(VendorBulkCreateRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $inputData = collect($params->get('data'));

            $data = $inputData->map(function ($input) {
                $vendor = new Vendor();
                $vendor->fill($input);
                $vendor->save();
                $this->authorize('create', $vendor);
                return $vendor;
            });

            return VendorResource::collection($data);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function bulkDestroy(VendorBulkDeleteRequest $request)
    {
        $params = collect($request->validated());

        return DB::transaction(function () use ($params) {
            $ids = collect($params->get('id'));

            $data = $this->getBaseQuery()->whereIn('id', $ids)->get();

            $data->each(function ($vendor) {
                $this->authorize('delete', $vendor);
                $vendor->delete();
            });

            return VendorResource::collection($data);
        });
    }

    private function getBaseQuery()
    {
        return Vendor::getModel()->withGlobalScope('policy', VendorPolicy::scope());
    }
}
