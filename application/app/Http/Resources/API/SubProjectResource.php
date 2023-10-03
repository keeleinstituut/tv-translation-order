<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
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
        $result = [
//            ...collect(parent::toArray($request))
//                ->except('cat_metadata')
//                ->toArray(),
            'id' => $this->id,
            'ext_id' => $this->ext_id,
            'project_id' => $this->project_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'features' => $this->project->typeClassifierValue->projectTypeConfig->features,
            'cat_project_created' => collect($this->cat_metadata)->isNotEmpty(),
            'cat_features' => $this->cat()->getSupportedFeatures(),
            'cat_files' => $this->cat()->getFiles(),
            'cat_jobs' => $this->cat()->getJobs(),
            'cat_analyzis' => $this->cat()->getAnalyzis(),
            'project' => new ProjectResource($this->whenLoaded('project')),
            'source_language_classifier_value_id' => $this->source_language_classifier_value_id,
            'source_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value_id' => $this->destination_language_classifier_value_id,
            'destination_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('destinationLanguageClassifierValue')),
            'assignments' => $this->whenLoaded('assignments'),
            'source_files' => MediaResource::collection($this->whenLoaded('sourceFiles')),
            'final_files' => MediaResource::collection($this->whenLoaded('finalFiles')),
        ];

        return $result;
    }
}
