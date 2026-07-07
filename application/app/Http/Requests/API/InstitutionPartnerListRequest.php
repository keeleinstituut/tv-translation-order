<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class InstitutionPartnerListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|max:50',
            'partner_institution_id' => 'sometimes|array',
            'partner_institution_id.*' => 'uuid',
            'sort_by' => 'sometimes|in:created_at',
            'sort_order' => 'sometimes|in:asc,desc',
            'q' => 'nullable|string',
        ];
    }
}
