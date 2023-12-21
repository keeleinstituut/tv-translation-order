<?php

namespace App\Http\Requests\API;

use App\Models\Assignment;
use App\Policies\AssignmentPolicy;
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
            new OA\Property(property: 'event_start_at', type: 'string', format: 'date-time', example: '2020-12-31T12:00:00Z'),
        ]
    )
)]
class AssignmentUpdateRequest extends FormRequest
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
            'comments' => ['nullable', 'string'],
            'deadline_at' => ['required', 'date_format:' . self::DATETIME_FORMAT],
            'event_start_at' => ['sometimes', 'date_format:' . self::DATETIME_FORMAT],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $assignment = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
                    ->find($this->route('id'));

                if (empty($assignment)) {
                    return;
                }

                if ($this->validated('deadline_at') > $assignment->subProject->deadline_at->format(self::DATETIME_FORMAT)) {
                    $validator->errors()->add('deadline_at', 'Assignment deadline should be less or equal to the sub-project deadline');
                }

                $deadline = $this->validated('deadline_at', $assignment->deadline_at?->format(self::DATETIME_FORMAT));
                $eventStart = $this->validated('event_start_at',  $assignment->event_start_at?->format(self::DATETIME_FORMAT));
                if (filled($deadline) && filled($eventStart) && $eventStart > $deadline) {
                    $validator->errors()->add('event_start_at', 'Event start datetime should be less or equal to deadline');
                }
            }
        ];
    }
}
