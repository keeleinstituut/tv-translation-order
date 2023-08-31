<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'cat_project_created' => collect($this->cat_metadata)->isNotEmpty(),
            'cat_features' => $this->cat()->getSupportedFeatures(),
            'cat_files' => $this->cat()->getFiles(),
            'cat_jobs' => $this->cat()->getJobs(),
            'cat_analyzis' => $this->cat()->getAnalyzis(),
        ];
    }
}
