<?php

namespace App\Http\Requests;

use App\Enums\TagType;
use App\Policies\TagPolicy;
use App\Rules\EnumWithExcludedItems;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
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
            'tags.*' => Rule::forEach(fn() => [
                function ($attr, $value, $fail) {
                    $subValidator = Validator::make($value, [
                        'name' => [
                            'required', 'string',
                            Rule::unique('tags', 'name')->using(fn(Builder $query) => $query
                                ->where('institution_id', $this->getActingUserInstitutionId())
                                ->where('type', $value['type'])
                            )
                        ],
                        'type' => ['required', new EnumWithExcludedItems(TagType::class, [TagType::VendorSkill])]
                    ]);

                    if ($subValidator->fails()) {
                        $fail($subValidator->errors()->first());
                    }
                }
            ])
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
