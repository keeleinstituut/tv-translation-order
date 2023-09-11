<?php

namespace App\Http\Resources\API;

use App\Models\CatToolJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CatToolJob
 */
class CatToolJobVolumeAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->only([
            'id',
            'name',
            'volume_analysis'
        ]);
    }
}
