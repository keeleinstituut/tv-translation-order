<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
use App\Models\SubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubProject
 */
class SubProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...collect(parent::toArray($request))
                ->except('cat_metadata')
                ->toArray(),
            'features' => $this->project->typeClassifierValue->projectTypeConfig->features,
            'cat_files' => MediaResource::collection($this->cat()->getSourceFiles()),
            'cat_jobs' => CatToolJobResource::collection($this->catToolJobs),
            'mt_enabled' => $this->cat()->hasMTEnabled(),
        ];
    }
}
