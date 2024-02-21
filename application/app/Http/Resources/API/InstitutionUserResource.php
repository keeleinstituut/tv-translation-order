<?php

namespace App\Http\Resources\API;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin InstitutionUser
 */
#[OA\Schema(
    title: 'Institution User',
    required: ['id', 'email', 'phone', 'user', 'institution', 'department', 'roles'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'email', type: 'string', format: 'email'),
        new OA\Property(property: 'phone', type: 'string', format: 'phone'),
        new OA\Property(property: 'user', type: 'object'),
        new OA\Property(property: 'institution', type: 'object'),
        new OA\Property(property: 'department', type: 'object'),
        new OA\Property(property: 'roles', type: 'object'),
        new OA\Property(property: 'vacations', type: 'object'),
        new OA\Property(property: 'vendor', ref: VendorResource::class, type: 'object'),
    ],
    type: 'object'
)]
class InstitutionUserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'user' => $this->user,
            'institution' => $this->institution,
            'department' => $this->department,
            'roles' => collect($this->roles)->map(fn ($role) => collect($role)->only('id', 'name')),
            'vacations' => $this->vacations,
            'vendor' => VendorResource::make($this->whenLoaded('vendor')),
        ];
    }
}
