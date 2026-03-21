<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\TagType;
use App\Http\Requests\Helpers\MaxLengthValue;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\Tag;
use App\Models\Vendor;
use App\Policies\ProjectPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpFoundation\Response;

class ProjectUpdateRequest extends ProjectCreateRequest
{
    const string DATETIME_FORMAT = 'Y-m-d\\TH:i:s\\Z'; //only UTC (zero offset)

    private ?Project $project;


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type_classifier_value_id' => [
                'sometimes',
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'translation_domain_classifier_value_id' => [
                'sometimes',
                'uuid',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::TranslationDomain),
            ],
            'source_language_classifier_value_id' => [
                'sometimes',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
            'destination_language_classifier_value_ids' => ['sometimes', 'array'],
            'destination_language_classifier_value_ids.*' => [
                'required',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
            'manager_institution_user_id' => [
                'sometimes',
                'uuid',
                'bail',
                $this->userCanBeSelectedAsManagerRule(),
            ],
            'client_institution_user_id' => [
                'sometimes',
                'uuid',
                'bail',
                $this->userCanBeSelectedAsClientRule(),
            ],
            'reference_number' => ['nullable', 'string'],
            'comments' => ['sometimes', 'nullable', 'string', 'max:'. MaxLengthValue::TEXT],
            'deadline_at' => ['sometimes', 'date_format:' . self::DATETIME_FORMAT],
            'event_start_at' => [
                'sometimes',
                'date_format:' . self::DATETIME_FORMAT,
                Rule::prohibitedIf(fn() => !$this->isCalendarProject() && !ClassifierValue::isProjectTypeSupportingEventStartDate(
                    $this->get(
                        'type_classifier_value_id',
                        $this->getProject()->type_classifier_value_id
                    )
                )),
            ],
            'event_end_at' => [
                'sometimes',
                'date_format:' . self::DATETIME_FORMAT,
                Rule::prohibitedIf(fn() => !$this->isCalendarProject()),
            ],
            'service_type' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string'],
            'meeting_link' => ['sometimes', 'nullable', 'string'],
            'candidate_vendor_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'bail',
                Rule::prohibitedIf(fn() => !Auth::hasPrivilege(PrivilegeKey::ManageProject->value)),
                Rule::exists(Vendor::class, 'id'),
            ],
            'use_external_vendor' => ['sometimes', 'nullable', 'boolean'],
            'tags' => 'sometimes|array',
            'tags.*' => [
                'required',
                Rule::exists(Tag::class, 'id')->where('type', TagType::Order->value),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $validated = $validator->validated();
            $deadline = data_get($validated, 'deadline_at', $this->getProject()->deadline_at?->format(self::DATETIME_FORMAT));
            $eventStart = data_get($validated, 'event_start_at', $this->getProject()->event_start_at?->format(self::DATETIME_FORMAT));
            $eventEnd = data_get($validated, 'event_end_at', $this->getProject()->event_end_at?->format(self::DATETIME_FORMAT));

            if (filled($deadline) && filled($eventStart) && $deadline < $eventStart) {
                $validator->errors()->add('event_start_at', 'Event start datetime should be less or equal to deadline');
            }

            if (filled($eventStart) && filled($eventEnd) && $eventEnd <= $eventStart) {
                $validator->errors()->add('event_end_at', 'Event end datetime should be greater than event start datetime');
            }
        });
    }

    private function isCalendarProject(): bool
    {
        return $this->getProject()->is_calendar_project;
    }

    private function getProject(): Project
    {
        if (empty($this->project)) {
            $project = Project::withGlobalScope('policy', ProjectPolicy::scope())
                ->find($this->route('id'));

            if (empty($project)) {
                abort(Response::HTTP_NOT_FOUND, 'Project not found');
            }

            $this->project = $project;
        }


        return $this->project;
    }
}
