<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
use App\Models\SubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubProject
 */
class CatToolVolumeAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'jobs' => CatToolJobVolumeAnalysisResource::collection($this->catToolJobs),
            'files' => MediaResource::collection($this->cat()->getSourceFiles()),
        ];
    }
}
