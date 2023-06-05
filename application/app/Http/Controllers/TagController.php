<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagsRequest;
use App\Http\Requests\TagListRequest;
use App\Http\Resources\TagResource;
use App\Models\Institution;
use App\Models\Tag;
use App\Policies\TagPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\ResourceCollection;
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
        $tagsQuery->when($request->validated('type'), function (Builder $query, string $type) {
            $query->where('type', $type);
        });

        $tagsQuery->orderBy('type')->orderBy('name');
        return TagResource::collection($tagsQuery->get());
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    function store(StoreTagsRequest $request)
    {
        $this->authorize('create', Tag::class);
        return DB::transaction(function () use ($request): ResourceCollection {
            $currentInstitution = Institution::findOrFail(Auth::user()->institutionId);
            $tags = collect();
            foreach ($request->validated('tags') as $tagData) {
                $tag = new Tag($tagData);
                $tag->institution()->associate($currentInstitution);
                $tag->saveOrFail();
                $tags->add($tag->refresh());
            }

            return TagResource::collection($tags);
        });
    }

    private function getBaseQuery(): Builder
    {
        return Tag::query()->withGlobalScope('policy', TagPolicy::scope());
    }
}
