<?php

namespace App\Http\Requests\API;

use App\Enums\ExternalRequestRecipientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExternalTranslationRequestRecipientListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'nullable|array',
            'status.*' => ['nullable', Rule::enum(ExternalRequestRecipientStatus::class)],
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|string|in:created_at,notified_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }
}
