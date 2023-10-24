<?php

namespace App\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ClassifierValueListRequest;
use App\Http\Resources\API\ClassifierValueResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Policies\ClassifierValuePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use phpseclib3\Math\BigInteger\Engines\PHP;

class ClassifierValueController extends Controller
{
    /**
     * Display a listing of the resource.
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/classifier-values',
        summary: 'List Classifier Values',
        tags: ['Cached entities'],
        parameters: [
            new OA\QueryParameter(name: 'type', schema: new OA\Schema(type: 'string', enum: ClassifierValueType::class, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: ClassifierValueResource::class)]
    public function index(ClassifierValueListRequest $request): AnonymousResourceCollection
    {
        $params = collect($request->validated());

        $this->authorize('viewAny', ClassifierValue::class);

        $query = $this->getBaseQuery()->with('projectTypeConfig.jobDefinitions');

        if ($type = $params->get('type')) {
            $query->where('type', $type);
        }

        return ClassifierValueResource::collection($query
            ->orderBy('type')
            ->orderBy('name')
            ->get());
    }

    private function getBaseQuery(): Builder
    {
        return ClassifierValue::getModel()->withGlobalScope('policy', ClassifierValuePolicy::scope());
    }
}
