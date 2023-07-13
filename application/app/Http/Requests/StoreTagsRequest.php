<?php

namespace App\Http\Requests;

use App\Enums\TagType;
use App\Rules\TagNameRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['tags'],
        properties: [
            new OA\Property(
                property: 'tags',
                type: 'array',
                items: new OA\Items(
                    required: ['type', 'name'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', enum: TagType::class),
                        new OA\Property(property: 'name', type: 'string'),
                    ],
                    type: 'object'
                ),
                minItems: 1
            ),
        ]
    )
)]
class StoreTagsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules()
    {
        return [
            'tags' => ['required', 'array', 'min:1'],
            'tags.*.type' => ['required', 'bail', new Enum(TagType::class), Rule::notIn([TagType::VendorSkill->value])],
            'tags.*.name' => ['required', 'string'],
            'tags.*' => [
                'required',
                'array',
                new TagNameRule($this->getActingUserInstitutionId()),
            ],
        ];
    }

    private function getActingUserInstitutionId(): string
    {
        if (empty($currentUserInstitutionId = Auth::user()?->institutionId)) {
            abort(401);
        }

        return $currentUserInstitutionId;
    }
}
