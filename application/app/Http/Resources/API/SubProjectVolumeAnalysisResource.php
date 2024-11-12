<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
use App\Models\SubProject;
use App\Services\CatTools\Enums\CatToolAnalyzingStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin SubProject
 */
#[OA\Schema(
    title: 'SubProjectVolumeAnalysisResource',
    required: [
        'cat_jobs',
        'cat_files',
        'analyzing_status',
    ],
    properties: [
        new OA\Property(property: 'analyzing_status', type: 'string', enum: CatToolAnalyzingStatus::class),
        new OA\Property(property: 'setup_status', type: 'string', enum: CatToolAnalyzingStatus::class),
        new OA\Property(property: 'cat_jobs', type: 'array', items: new OA\Items(ref: CatToolJobVolumeAnalysisResource::class)),
        new OA\Property(property: 'cat_files', type: 'array', items: new OA\Items(ref: MediaResource::class)),
    ],
    type: 'object'
)]
class SubProjectVolumeAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'setup_status' => $this->cat()->getSetupStatus(),
            'analyzing_status' => $this->cat()->getAnalyzingStatus(),
            'cat_jobs' => CatToolJobVolumeAnalysisResource::collection($this->whenLoaded('catToolJobs')),
            'cat_files' => MediaResource::collection($this->cat()->getSourceFiles()),
        ];
    }
}
