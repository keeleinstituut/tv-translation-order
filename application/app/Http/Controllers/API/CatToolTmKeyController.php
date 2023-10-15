<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CatToolTmKeysSyncRequest;
use App\Http\Resources\API\CatToolTmKeyResource;
use App\Models\CatToolTmKey;
use App\Models\SubProject;
use App\Policies\CatToolTmKeyPolicy;
use App\Policies\SubProjectPolicy;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CatToolTmKeyController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/cat-tool/tm-keys/{sub_project_id}',
        summary: 'Get CAT tool TM keys by given sub-project UUID',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolTmKeyResource::class, description: 'TM keys of the sub-project')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->findOrFail($request->get('sub_project_id'));

        $this->authorize('viewAny', [CatToolTmKey::class, $subProject]);

        $query = self::getBaseQuery()->where('sub_project_id', $subProject->id)
            ->orderBy('created_at');

        return CatToolTmKeyResource::collection($query->get());
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/cat-tool/tm-keys/sync',
        summary: 'Add new/delete missing/update existing TM keys for the sub-project',
        requestBody: new OAH\RequestBody(CatToolTmKeysSyncRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\NotFound, new OAH\Forbidden, new OAH\Unauthorized, new OAH\InvalidTmKeys]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolTmKeyResource::class, description: 'Affected CAT tool TMs', response: Response::HTTP_OK)]
    public function sync(CatToolTmKeysSyncRequest $request): AnonymousResourceCollection
    {
        return DB::transaction(function () use ($request): AnonymousResourceCollection {
            $subProject = $request->getSubProject();
            $this->authorize('sync', [CatToolTmKey::class, $subProject]);

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::InProgress) {
                abort(Response::HTTP_BAD_REQUEST, 'CAT tool setup is in progress, please try again later');
            }

            $existingTmKeys = $subProject->catToolTmKeys()->get()->keyBy('key');
            $receivedTmKeysData = collect($request->validated('tm_keys'))->keyBy('key');

            // Create new TM keys
            $receivedTmKeysData->keys()->diff($existingTmKeys->keys())
                ->each(function (string $newTmKey) use ($receivedTmKeysData, $subProject) {
                    $newTmKeyData = $receivedTmKeysData->get($newTmKey);
                    (new CatToolTmKey())
                        ->fill([
                            ...$newTmKeyData,
                            'sub_project_id' => $subProject->id,
                        ])->saveOrFail();
                });

            // Update existing
            $receivedTmKeysData->keys()->intersect($existingTmKeys->keys())
                ->each(function (string $existingTmKeyKey) use ($existingTmKeys, $receivedTmKeysData) {
                    /** @var CatToolTmKey $existingTmKey */
                    $existingTmKey = $existingTmKeys->get($existingTmKeyKey);
                    $existingTmKey->fill($receivedTmKeysData->get($existingTmKeyKey))
                        ->saveOrFail();
                });

            // Delete missing
            if (! empty($tmKeysToRemove = $existingTmKeys->keys()->diff($receivedTmKeysData->keys())->toArray())) {
                CatToolTmKey::whereIn('key', $tmKeysToRemove)
                    ->where('sub_project_id', $subProject->id)
                    ->delete();
            }

            if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::Done) {
                try {
                    $subProject->cat()->setTmKeys();
                } catch (RequestException $e) {
                    abort(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
            }

            return CatToolTmKeyResource::collection($subProject->catToolTmKeys()->get());
        });
    }

    private static function getBaseQuery(): Builder
    {
        return CatToolTmKey::withGlobalScope('policy', CatToolTmKeyPolicy::scope());
    }
}
