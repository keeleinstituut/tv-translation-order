<?php

namespace App\Http\Resources\API;

use App\Models\SubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubProject
 */
class CatToolMTEngineStatusResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'mt_enabled' => $this->cat()->hasMTEnabled(),
        ];
    }
}
