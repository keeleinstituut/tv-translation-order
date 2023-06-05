<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     institution_id: string,
     *     name: string,
     *     type: string,
     *     updated_at: Carbon,
     *     created_at: Carbon
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->only([
            'id',
            'institution_id',
            'name',
            'type',
            'created_at',
            'updated_at'
        ]);
    }
}
