<?php

namespace App\Http\Requests\API;

use App\Enums\OutsourceRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutsourceRequestListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignment_id' => 'nullable|uuid',
            'sub_project_id' => 'nullable|uuid',
            'project_id' => 'nullable|uuid',
            'status' => 'nullable|array',
            'status.*' => ['nullable', Rule::enum(OutsourceRequestStatus::class)],
            'per_page' => 'nullable|integer|min:1|max:50',
            'sort_by' => 'nullable|string|in:created_at',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }
}
