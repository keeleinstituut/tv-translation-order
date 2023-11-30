<?php

namespace App\Http\Requests;

use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'comments', type: 'string', nullable: true),
            new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time', example: '2020-12-31T12:00:00Z'),
        ]
    )
)]
class SubProjectUpdateRequest extends FormRequest
{
    const DATETIME_FORMAT = 'Y-m-d\\TH:i:s\\Z'; //only UTC (zero offset)

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'deadline_at' => ['required', 'date_format:' . self::DATETIME_FORMAT, 'after:now'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                    ->find($this->route('id'));

                if (empty($subProject)) {
                    return;
                }

                if ($this->validated('deadline_at') > $subProject->project->deadline_at->format(self::DATETIME_FORMAT)) {
                    $validator->errors()->add('deadline_at', 'Sub-project deadline should be less or equal to the project deadline');
                }
            }
        ];
    }
}
