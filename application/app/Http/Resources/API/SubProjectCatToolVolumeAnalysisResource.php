<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
use App\Models\SubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin SubProject
 */
#[OA\Schema(
    required: [
        'jobs',
        'files',
    ],
    properties: [
        new OA\Property(property: 'jobs', type: 'array', items: new OA\Items(ref: CatToolJobVolumeAnalysisResource::class)),
        new OA\Property(property: 'files', type: 'array', items: new OA\Items(ref: MediaResource::class)),
    ],
    type: 'object'
)]
class SubProjectCatToolVolumeAnalysisResource extends JsonResource
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
