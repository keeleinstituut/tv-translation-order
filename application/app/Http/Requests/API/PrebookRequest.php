<?php

namespace App\Http\Requests\API;

use App\Enums\PrivilegeKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['start_at', 'end_at', 'language_id'],
        properties: [
            new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid', nullable: true),
            new OA\Property(property: 'tag_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        ]
    )
)]
class PrebookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'language_id' => ['required', 'uuid'],
            'vendor_id' => [
                Rule::requiredIf(fn() => Auth::hasPrivilege(PrivilegeKey::ManageProject->value)),
                Rule::prohibitedIf(fn() => !Auth::hasPrivilege(PrivilegeKey::ManageProject->value)),
                'nullable',
                'uuid',
                'exists:vendors,id',
            ],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['uuid'],
        ];
    }
}
