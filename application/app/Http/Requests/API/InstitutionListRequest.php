<?php

namespace App\Http\Requests\API;

use App\Enums\InstitutionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'type' => ['sometimes', Rule::enum(InstitutionType::class)],
            'not_partner_of_current_institution' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|max:50',
            'sort_by' => 'sometimes|in:name',
            'sort_order' => 'sometimes|in:asc,desc',
        ];
    }
}
