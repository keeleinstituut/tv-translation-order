<?php

namespace App\Http\Requests;

use App\Enums\TagType;
use App\Models\Tag;
use App\Rules\TagNameRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateTagsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules()
    {
        return [
            'type' => ['required', 'bail', new Enum(TagType::class), Rule::notIn([TagType::VendorSkill->value])],
            'tags' => ['present', 'array', 'max:10000'],
            'tags.*.name' => ['required', 'string'],
            'tags.*.id' => ['sometimes', 'nullable', 'uuid',
                Rule::exists(app(Tag::class)->getTable(), 'id')->where(function (Builder $query) {
                    $query->where('type', $this->input('type'))
                        ->where('institution_id', $this->getActingUserInstitutionId());

                    return $query;
                }),
            ],
            'tags.*' => [
                'required',
                'array',
                new TagNameRule($this->getActingUserInstitutionId(), $this->input('type')),
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

    public function getType(): TagType
    {
        return TagType::tryFrom($this->validated('type'));
    }
}
