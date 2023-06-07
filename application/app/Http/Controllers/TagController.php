<?php

namespace App\Http\Controllers;

use App\Enums\TagType;
use App\Http\Requests\StoreTagsRequest;
use App\Http\Requests\TagListRequest;
use App\Http\Requests\UpdateTagsRequest;
use App\Http\Resources\TagResource;
use App\Models\Institution;
use App\Models\Tag;
use App\Policies\TagPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class TagController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(TagListRequest $request): ResourceCollection
    {
        $this->authorize('viewAny', Tag::class);

        $tagsQuery = $this->getBaseQuery();
        $tagsQuery->when(
            $request->validated('type'),
            fn(Builder $query, string $type) => $query->where('type', $type)
        );

        $tagsQuery->orderBy('type')->orderBy('name');

        return TagResource::collection($tagsQuery->get());
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    public function store(StoreTagsRequest $request)
    {
        $this->authorize('create', Tag::class);

        return DB::transaction(function () use ($request): ResourceCollection {
            $currentInstitution = Institution::findOrFail(Auth::user()->institutionId);
            $tags = collect();
            foreach ($request->validated('tags') as $tagData) {
                $tags->add(
                    $this->getCreatedTag(
                        $tagData['name'],
                        TagType::from($tagData['type']),
                        $currentInstitution
                    )
                );
            }

            return TagResource::collection($tags);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    public function update(UpdateTagsRequest $request)
    {
        return DB::transaction(function () use ($request): ResourceCollection {
            $currentInstitution = Institution::findOrFail(Auth::user()->institutionId);
            $tags = collect();
            $tagsType = TagType::from($request->validated('type'));
            $createAbilityChecked = false;
            foreach ($request->validated('tags') as $tagData) {
                if (filled($tagData['id'])) {
                    $tags->add(
                        $this->getUpdatedTag(
                            $tagData['id'],
                            $tagData['name']
                        )
                    );
                    continue;
                }

                !$createAbilityChecked && $this->authorize('create', Tag::class);
                $createAbilityChecked = true;

                $tags->add(
                    $this->getCreatedTag(
                        $tagData['name'],
                        $tagsType,
                        $currentInstitution
                    )
                );
            }

            $processedTagsIds = $tags->map(fn(Tag $tag) => $tag->id);
            $this->deleteNotProcessedTags($processedTagsIds, $tagsType);
            return TagResource::collection($tags);
        });
    }

    /**
     * @throws Throwable
     */
    private function getCreatedTag(string $name, TagType $type, Institution $institution): Tag
    {
        $tag = new Tag([
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
    private function getUpdatedTag(string $id, string $name): Tag
    {
        $tag = Tag::findOrFail($id);
        $this->authorize('update', $tag);
        $tag->name = $name;
        if ($tag->isDirty()) {
            $tag->saveOrFail();
            $tag->refresh();
        }

        return $tag;
    }

    private function deleteNotProcessedTags(Collection $processedTagsIds, TagType $tagsType)
    {
        $this->getBaseQuery()->where('type', $tagsType)
            ->whereNotIn('id', $processedTagsIds)
            ->each(fn(Tag $tag) => $this->authorize('delete', $tag) && $tag->delete());
    }

    private function getBaseQuery(): Builder
    {
        return Tag::query()->withGlobalScope('policy', TagPolicy::scope());
    }
}
