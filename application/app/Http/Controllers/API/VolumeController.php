<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;
use App\Http\Requests\API\CatToolVolumeCreateRequest;
use App\Http\Requests\API\CatToolVolumeUpdateRequest;
use App\Http\Requests\API\VolumeCreateRequest;
use App\Http\Requests\API\VolumeUpdateRequest;
use App\Http\Resources\API\VolumeResource;
use App\Models\Volume;
use App\Policies\VolumePolicy;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VolumeController extends Controller
{
    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/volumes',
        summary: 'Create a new volume for assignment',
        requestBody: new OAH\RequestBody(VolumeCreateRequest::class),
        tags: ['Volume management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VolumeResource::class, description: 'Created volume', response: Response::HTTP_CREATED)]
    public function store(VolumeCreateRequest $request): VolumeResource
    {
        $this->authorize('create', Volume::class);

        return DB::transaction(function () use ($request) {
            $volume = (new Volume)->fill($request->validated());
            $volume->saveOrFail();

            return VolumeResource::make($volume);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/volumes/cat-tool',
        summary: 'Create a new volume for assignment with CAT tool',
        requestBody: new OAH\RequestBody(CatToolVolumeCreateRequest::class),
        tags: ['Volume management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VolumeResource::class, description: 'Created volume', response: Response::HTTP_CREATED)]
    public function storeCatToolVolume(CatToolVolumeCreateRequest $request)
    {
        $this->authorize('create', Volume::class);

        return DB::transaction(function () use ($request) {
            $volume = (new Volume)->fill($request->validated());
            $volume->unit_quantity = $volume->getVolumeAnalysis()?->total;
            $volume->unit_type = $volume->catToolJob?->volume_unit_type;
            $volume->saveOrFail();

            return VolumeResource::make($volume);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/volumes/{id}',
        summary: 'Update volume for an assignment',
        requestBody: new OAH\RequestBody(VolumeUpdateRequest::class),
        tags: ['Volume management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VolumeResource::class, description: 'Updated volume', response: Response::HTTP_OK)]
    public function update(VolumeUpdateRequest $request): VolumeResource
    {
        return DB::transaction(function () use ($request) {
            $volume = self::getBaseQuery()->findOrFail($request->route('id'))
                ->fill($request->validated());

            $this->authorize('update', $volume);

            $volume->saveOrFail();

            return VolumeResource::make($volume);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/volumes/cat-tool/{id}',
        summary: 'Update volume for an assignment with CAT tool',
        requestBody: new OAH\RequestBody(CatToolVolumeUpdateRequest::class),
        tags: ['Volume management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VolumeResource::class, description: 'Updated volume', response: Response::HTTP_OK)]
    public function updateCatToolVolume(CatToolVolumeUpdateRequest $request): VolumeResource
    {
        return DB::transaction(function () use ($request) {
            $volume = self::getBaseQuery()->findOrFail($request->route('id'))
                ->fill($request->validated());
            $volume->unit_quantity = $volume->getVolumeAnalysis()?->total;

            $this->authorize('update', $volume);

            $volume->saveOrFail();

            return VolumeResource::make($volume);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/volumes/{id}',
        summary: 'Delete volume',
        tags: ['Volume management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Volume deleted')]
    public function destroy(Request $request): \Illuminate\Http\Response
    {
        DB::transaction(function () use ($request) {
            $volume = self::getBaseQuery()->findOrFail($request->route('id'));

            $this->authorize('delete', $volume);

            $volume->delete();
        });

        return response()->noContent();
    }

    private static function getBaseQuery(): Builder|Volume
    {
        return Volume::withGlobalScope('policy', VolumePolicy::scope())->with([
            'assignment.assignee',
            'catToolJob',
        ]);
    }
}
