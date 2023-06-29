<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Support\Arr;

class RepresentationHelpers
{
    public static function createTagFlatRepresentation(Tag $tag): array
    {
        return Arr::only(
            $tag->toArray(),
            ['id', 'institution_id', 'name', 'type', 'created_at', 'updated_at']
        );
    }
}
