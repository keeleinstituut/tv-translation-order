<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\ClassifierValueListRequest;
use App\Http\Resources\API\ClassifierValueResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Policies\ClassifierValuePolicy;
use OpenApi\Attributes as OA;
use App\Http\OpenApiHelpers as OAH;

class ClassifierValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    #[OA\Get(
        path: '/classifier-values',
        summary: 'List Classifier Values',
        tags: ['Cached entities'],
        parameters: [
            new OA\QueryParameter(name: 'type', schema: new OA\Schema(type: 'string', nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: ClassifierValueResource::class)]
    public function index(ClassifierValueListRequest $request)
    {
        $params = collect($request->validated());

        $this->authorize('viewAny', ClassifierValue::class);

        $query = $this->getBaseQuery();

        if ($type = $params->get('type')) {
            $query = $query->where('type', $type);
        }

        $data = $query
            ->orderBy('type', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        return ClassifierValueResource::collection($data);
    }

    private function getBaseQuery() {
        return ClassifierValue::getModel()->withGlobalScope('policy', ClassifierValuePolicy::scope());
    }
}
