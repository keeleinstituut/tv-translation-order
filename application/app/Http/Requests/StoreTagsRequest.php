<?php

namespace App\Http\Requests;

use App\Enums\TagType;
use App\Rules\EnumWithExcludedItems;
use App\Rules\TagNameRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StoreTagsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => Rule::forEach(fn () => [
                function ($attr, $value, $fail) {
                    $subValidator = Validator::make($value, [
                        'type' => ['required', new EnumWithExcludedItems(TagType::class, [TagType::VendorSkill])],
                        'name' => [
                            'required', 'string',
                            new TagNameRule(
                                $this->getActingUserInstitutionId(),
                                $value['type']
                            ),
                        ],
                    ]);

                    if ($subValidator->fails()) {
                        $fail($subValidator->errors()->first());
                    }
                },
            ]),
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
