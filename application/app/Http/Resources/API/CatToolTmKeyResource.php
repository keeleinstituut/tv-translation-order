<?php

namespace App\Http\Resources\API;

use App\Models\CatToolTmKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatToolTmKey
 */
class CatToolTmKeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(
                'id',
                'sub_project_id',
                'key',
                'is_writable',
            ),
            'sub_project' => $this->whenLoaded('subProject')
        ];
    }
}
