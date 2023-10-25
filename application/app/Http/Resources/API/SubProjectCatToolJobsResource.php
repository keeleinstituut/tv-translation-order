<?php

namespace App\Http\Resources\API;

use App\Models\SubProject;
use App\Services\CatTools\Enums\CatToolAnalyzingStatus;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;
/**
 * @mixin SubProject
 */
#[OA\Schema(
    title: 'SubProjectCatToolJobsResource',
    required: [
        'cat_jobs',
        'setup_status',
        'analyzing_status'
    ],
    properties: [
        new OA\Property(property: 'setup_status', type: 'string', enum: CatToolSetupStatus::class),
        new OA\Property(property: 'analyzing_status', type: 'string', enum: CatToolAnalyzingStatus::class),
        new OA\Property(property: 'cat_jobs', type: 'array', items: new OA\Items(ref: CatToolJobResource::class)),
    ],
    type: 'object'
)]
class SubProjectCatToolJobsResource extends JsonResource
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
            'cat_jobs' => CatToolJobResource::collection($this->whenLoaded('catToolJobs'))
        ];
    }
}
