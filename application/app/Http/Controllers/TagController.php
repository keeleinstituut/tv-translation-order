<?php

namespace App\Http\Controllers;

use App\Enums\TagType;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\StoreTagsRequest;
use App\Http\Requests\TagListRequest;
use App\Http\Requests\UpdateTagsRequest;
use App\Http\Resources\TagResource;
use App\Models\CachedEntities\Institution;
use App\Models\Tag;
use App\Policies\TagPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TagController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/tags',
        summary: 'List tags of current institution, optionally filtering by type (institution inferrred from JWT)',
        tags: ['Tag management'],
        parameters: [
            new OA\QueryParameter(name: 'type', schema: new OA\Schema(enum: TagType::class, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: TagResource::class, description: 'Filtered tags')]
    public function index(TagListRequest $request): ResourceCollection
    {
        $this->authorize('viewAny', Tag::class);

        $tagsQuery = $this->getBaseQuery();
        if ($type = $request->validated('type')) {
            $tagsQuery->where('type', $type);
        }

        $tagsQuery->orderBy('type')->orderBy('name');

        return TagResource::collection($tagsQuery->get());
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/tags/bulk-create',
        summary: 'Bulk create a new tags',
        requestBody: new OAH\RequestBody(StoreTagsRequest::class),
        tags: ['Tag management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: TagResource::class, description: 'Created tags', response: Response::HTTP_CREATED)]
    public function store(StoreTagsRequest $request)
    {
        $this->authorize('create', Tag::class);

        return DB::transaction(function () use ($request): ResourceCollection {
            $institution = Institution::findOrFail(Auth::user()->institutionId);
            $tags = collect($request->validated('tags'))
                ->map(fn (array $tagData) => $this->createTag(
                    $tagData['name'],
                    $tagData['type'],
                    $institution
                ));

            return TagResource::collection($tags);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/tags/bulk-update',
        summary: 'Bulk create, delete and/or update tags of a given type. '.
            'If ID left unspecified, the tag will be created. '.
            'If a previously existing tag is not in request input, it will be deleted.',
        requestBody: new OAH\RequestBody(UpdateTagsRequest::class),
        tags: ['Tag management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: TagResource::class, description: 'Array of modified and/or created tags')]
    public function update(UpdateTagsRequest $request)
    {
        if (collect($request->validated('tags'))->some(fn ($tag) => empty($tag['id']))) {
            $this->authorize('create', Tag::class);
        }

        $institution = Institution::findOrFail(Auth::user()->institutionId);

        return DB::transaction(function () use ($institution, $request): ResourceCollection {
            $tags = collect($request->validated('tags'))
                ->map(fn ($tag) => filled($tag['id'])
                    ? $this->updateTag($tag['id'], $tag['name'])
                    : $this->createTag($tag['name'], $request->validated('type'), $institution)
                );

            $this->deleteNotProcessedTags($tags, $request->getType());

            return TagResource::collection($tags);
        });
    }

    /**
     * @throws Throwable
     */
    private function createTag(string $name, string $type, Institution $institution): Tag
    {
        $tag = new Tag();
        $tag->fill([
            'name' => $name,
            'type' => $type,
        ]);
        $tag->institution()->associate($institution);
        $tag->saveOrFail();

        return $tag->refresh();
    }

    /**
     * @throws Throwable
     */
    private function updateTag(string $id, string $name): Tag
    {
        $tag = Tag::findOrFail($id);
        if ($tag->name !== $name) {
            $this->authorize('update', $tag);
            $tag->name = $name;
            $tag->saveOrFail();
            $tag->refresh();
        }

        return $tag;
    }

    private function deleteNotProcessedTags(Collection $tags, TagType $tagsType)
    {
        $this->getBaseQuery()->where('type', $tagsType)
            ->whereNotIn('id', $tags->pluck('id'))
            ->each(fn (Tag $tag) => $this->authorize('delete', $tag) && $tag->delete());
    }

    private function getBaseQuery(): Builder
    {
        return Tag::query()->withGlobalScope('policy', TagPolicy::scope());
    }
}
