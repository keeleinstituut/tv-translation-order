<?php

namespace App\Http\Resources\API;

use App\Models\SubProject;
use App\Services\CatTools\Enums\CatToolAnalyzingStatus;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * @mixin SubProject
 */
#[OA\Schema(
    title: 'SubProjectCatToolJobsResource',
    required: [
        'cat_jobs',
        'setup_status',
        'analyzing_status',
        'can_download_xliff',
        'can_download_translations',
    ],
    properties: [
        new OA\Property(property: 'setup_status', type: 'string', enum: CatToolSetupStatus::class),
        new OA\Property(property: 'analyzing_status', type: 'string', enum: CatToolAnalyzingStatus::class),
        new OA\Property(property: 'cat_jobs', type: 'array', items: new OA\Items(ref: CatToolJobResource::class)),
        new OA\Property(property: 'can_download_xliff', type: 'boolean'),
        new OA\Property(property: 'can_download_translations', type: 'boolean'),
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
            'cat_jobs' => CatToolJobResource::collection($this->whenLoaded('catToolJobs')),
            'can_download_xliff' => Gate::forUser($request->user())->allows('downloadXliff', $this->resource),
            'can_download_translations' => Gate::forUser($request->user())->allows('downloadTranslations', $this->resource),
        ];
    }
}
