<?php

namespace App\Http\Requests;

use App\Enums\TagType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

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
            'tags' => ['required', 'array', 'min'],
            'tags.*.id' => ['bail', 'uuid'], // TODO: add validation of the ID with institution_id
            'tags.*.name' => ['required', 'string'], // TODO: add unique validation with institution_id
            'tags.*.type' => ['required',  new Enum(TagType::class)],
        ];
    }

    public function hasNewTags(): bool
    {
        return true;
    }
}
