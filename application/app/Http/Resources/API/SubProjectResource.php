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
            'id' => $this->id,
            'ext_id' => $this->ext_id,
            'project_id' => $this->project_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'price' => $this->price,
            'features' => $this->project->typeClassifierValue->projectTypeConfig->features,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'source_language_classifier_value_id' => $this->source_language_classifier_value_id,
            'source_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value_id' => $this->destination_language_classifier_value_id,
            'destination_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('destinationLanguageClassifierValue')),
            'assignments' => $this->whenLoaded('assignments'),
            'cat_files' => MediaResource::collection($this->cat()->getSourceFiles()),
            'cat_jobs' => CatToolJobResource::collection($this->catToolJobs),
            'source_files' => MediaResource::collection($this->whenLoaded('sourceFiles')),
        ];
    }
}
