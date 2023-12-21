<?php

namespace App\Http\Requests\API;

use App\Enums\ProjectStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ProjectsExportRequest extends FormRequest
{
    const DATETIME_FORMAT = 'Y-m-d';

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['array'],
            'status.*' => [new Enum(ProjectStatus::class)],
            'date_from' => ['sometimes', 'date_format:' . self::DATETIME_FORMAT],
            'date_to' => ['sometimes', 'after:date_from', 'date_format:' . self::DATETIME_FORMAT]
        ];
    }
}
