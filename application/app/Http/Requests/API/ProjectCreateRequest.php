<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ServiceType;
use App\Enums\TagType;
use App\Http\Requests\Helpers\MaxLengthValue;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\Tag;
use App\Models\Vendor;
use App\Rules\ModelBelongsToInstitutionRule;
use App\Rules\ProjectFileValidator;
use App\Rules\ScannedRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: [
                'destination_language_classifier_value_ids',
            ],
            properties: [
                new OA\Property(
                    property: 'is_calendar_project',
                    description: 'When true, the project is a calendar booking. Calendar projects have different required fields.',
                    type: 'boolean',
                    nullable: true
                ),
                new OA\Property(
                    property: 'type_classifier_value_id',
                    description: 'Required when is_calendar_project is false or omitted.',
                    type: 'string',
                    format: 'uuid',
                    nullable: true
                ),
                new OA\Property(property: 'manager_institution_user_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'client_institution_user_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'reference_number', type: 'string', nullable: true),
                new OA\Property(property: 'comments', type: 'string', nullable: true, deprecated: true),
                new OA\Property(property: 'comment', type: 'string', nullable: true),
                new OA\Property(
                    property: 'deadline_at',
                    description: 'Required when is_calendar_project is false or omitted.',
                    type: 'string',
                    format: 'date-time',
                    example: '2020-12-31T12:00:00Z',
                    nullable: true
                ),
                new OA\Property(
                    property: 'translation_domain_classifier_value_id',
                    description: 'Required when is_calendar_project is false or omitted.',
                    type: 'string',
                    format: 'uuid',
                    nullable: true
                ),
                new OA\Property(
                    property: 'source_language_classifier_value_id',
                    description: 'Required when is_calendar_project is false or omitted.',
                    type: 'string',
                    format: 'uuid',
                    nullable: true
                ),
                new OA\Property(
                    property: 'event_start_at',
                    description: 'Required for calendar projects and for project types that support start date.',
                    type: 'string',
                    format: 'date-time',
                    example: '2020-12-31T12:00:00Z',
                    nullable: true
                ),
                new OA\Property(
                    property: 'event_end_at',
                    description: 'Required for calendar projects. Must be after event_start_at.',
                    type: 'string',
                    format: 'date-time',
                    example: '2020-12-31T14:00:00Z',
                    nullable: true
                ),
                new OA\Property(
                    property: 'help_file_types',
                    description: 'Requires one element for EACH file uploaded in the \'help_files\' field',
                    type: 'array',
                    items: new OA\Items(type: 'string', enum: Project::HELP_FILE_TYPES),
                    minItems: 1
                ),
                new OA\Property(
                    property: 'help_files',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'binary'),
                    minItems: 1
                ),
                new OA\Property(
                    property: 'source_files',
                    description: 'Not allowed for calendar projects.',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'binary'),
                    minItems: 1
                ),
                new OA\Property(
                    property: 'destination_language_classifier_value_ids',
                    description: 'Exactly one language required for calendar projects.',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uuid'),
                    minItems: 1
                ),
                new OA\Property(
                    property: 'candidate_vendor_id',
                    description: 'Calendar projects only. Requires ManageProject privilege.',
                    type: 'string',
                    format: 'uuid',
                    nullable: true
                ),
                new OA\Property(
                    property: 'service_type',
                    description: 'Required for calendar projects.',
                    type: 'string',
                    enum: ['ON_SITE', 'REMOTE'],
                    nullable: true
                ),
                new OA\Property(property: 'location', type: 'string', nullable: true),
                new OA\Property(property: 'meeting_link', type: 'string', nullable: true),
                new OA\Property(property: 'use_external_vendor', type: 'boolean', nullable: true),
                new OA\Property(
                    property: 'tags',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uuid'),
                    nullable: true
                ),
            ],
            type: 'object'
        ),
        encoding: [
            new OA\Encoding(property: 'is_calendar_project', contentType: 'application/json'),
            new OA\Encoding(property: 'type_classifier_value_id', contentType: 'application/json'),
            new OA\Encoding(property: 'reference_number', contentType: 'application/json'),
            new OA\Encoding(property: 'manager_institution_user_id', contentType: 'application/json'),
            new OA\Encoding(property: 'comments', contentType: 'application/json'),
            new OA\Encoding(property: 'comment', contentType: 'application/json'),
            new OA\Encoding(property: 'deadline_at', contentType: 'application/json'),
            new OA\Encoding(property: 'event_start_at', contentType: 'application/json'),
            new OA\Encoding(property: 'event_end_at', contentType: 'application/json'),
            new OA\Encoding(property: 'translation_domain_classifier_value_id', contentType: 'application/json'),
            new OA\Encoding(property: 'source_language_classifier_value_id', contentType: 'application/json'),
            new OA\Encoding(property: 'destination_language_classifier_value_ids', contentType: 'application/json'),
            new OA\Encoding(property: 'help_file_types', contentType: 'application/json'),
            new OA\Encoding(property: 'source_files', contentType: 'application/octet-stream'),
            new OA\Encoding(property: 'help_files', contentType: 'application/octet-stream'),
            new OA\Encoding(property: 'candidate_vendor_id', contentType: 'application/json'),
            new OA\Encoding(property: 'service_type', contentType: 'application/json'),
            new OA\Encoding(property: 'location', contentType: 'application/json'),
            new OA\Encoding(property: 'meeting_link', contentType: 'application/json'),
            new OA\Encoding(property: 'use_external_vendor', contentType: 'application/json'),
            new OA\Encoding(property: 'tags', contentType: 'application/json'),
        ]
    ),
)]
class ProjectCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'is_calendar_project' => ['nullable', 'boolean'],
            'type_classifier_value_id' => [
                Rule::requiredIf(fn () => !$this->isCalendarProject()),
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'event_start_at' => [
                'nullable',
                'date_format:Y-m-d\\TH:i:s\\Z', // only UTC (zero offset)
                'bail',
                Rule::requiredIf(fn () => $this->isCalendarProject()),
            ],
            'event_end_at' => [
                'nullable',
                'date_format:Y-m-d\\TH:i:s\\Z',
                'bail',
                Rule::requiredIf(fn () => $this->isCalendarProject()),
            ],
            'manager_institution_user_id' => [
                'nullable',
                'uuid',
                'bail',
                $this->userCanBeSelectedAsManagerRule(),
            ],
            'client_institution_user_id' => [
                'nullable',
                'uuid',
                'bail',
                $this->userCanBeSelectedAsClientRule(),
            ],
            'reference_number' => ['nullable', 'string'],
            'comments' => ['nullable', 'string', 'max:'. MaxLengthValue::TEXT],
            'comment' => ['nullable', 'string', 'max:'. MaxLengthValue::TEXT],
            'deadline_at' => [
                'date_format:Y-m-d\\TH:i:s\\Z', // only UTC (zero offset)
                Rule::requiredIf(fn () => !ClassifierValue::isProjectTypeSupportingEventStartDate($this->input('type_classifier_value_id'))),
            ],
            'translation_domain_classifier_value_id' => [
                Rule::requiredIf(fn () => !$this->isCalendarProject()),
                'uuid',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::TranslationDomain),
            ],
            'source_files' => [
                'array', 'min:1', 'max:20',
                Rule::prohibitedIf(fn () => $this->isCalendarProject()),
            ],
            'source_files.*' => [ProjectFileValidator::createRule(), ScannedRule::createRule()],
            'help_files' => ['required_with:help_file_types', 'array', 'max:20'],
            'help_files.*' => [ProjectFileValidator::createRule(), ScannedRule::createRule()],
            'help_file_types' => ['required_with:help_files', 'array'],
            'help_file_types.*' => [Rule::in(Project::HELP_FILE_TYPES)],
            'source_language_classifier_value_id' => [
                'nullable',
                'string',
                'bail',
                Rule::requiredIf(fn () => !$this->isCalendarProject()),
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
            'destination_language_classifier_value_ids' => array_filter([
                'required',
                'array',
                $this->isCalendarProject() ? 'min:1' : null,
                $this->isCalendarProject() ? 'max:1' : null,
            ]),
            'destination_language_classifier_value_ids.*' => [
                'required',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],

            // Calendar-only fields
            'candidate_vendor_id' => [
                'nullable',
                'uuid',
                'bail',
                Rule::prohibitedIf(fn () => !$this->isCalendarProject() || !Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value)),
                Rule::exists(Vendor::class, 'id'),
            ],
            'service_type' => [
                'nullable',
                Rule::requiredIf(fn () => $this->isCalendarProject()),
                Rule::in(ServiceType::cases()),
            ],
            'location' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $this->input('service_type') === ServiceType::OnSite->value),
            ],
            'meeting_link' => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => $this->input('service_type') === ServiceType::Remote->value),
            ],
            'use_external_vendor' => [
                'nullable',
                'boolean'
            ],
            'tags' => ['sometimes', 'array'],
            'tags.*' => [
                'required',
                Rule::exists(Tag::class, 'id')->whereIn('type', [TagType::Order->value, TagType::TranslationDomain->value]),
            ],
        ];
    }

    /**
     * @param  Closure(InstitutionUser): array<string>  $extraErrorChecker
     */
    protected static function existsActiveUserInSameInstitution(Closure $extraErrorChecker): ModelBelongsToInstitutionRule
    {
        return ModelBelongsToInstitutionRule::create(
            InstitutionUser::class,
            fn () => Auth::user()?->institutionId,
            actualInstitutionIdRetriever: fn (InstitutionUser $institutionUser) => $institutionUser->institution['id']
        )
            ->addExtraValidation(fn (InstitutionUser $institutionUser) => collect()
                ->when(
                    $institutionUser->isArchived(),
                    fn (Collection $errors) => $errors->push('The user referenced by :attribute may not be archived.')
                )
                ->when(
                    $institutionUser->isDeactivated(),
                    fn (Collection $errors) => $errors->push('The user referenced by :attribute may not be deactivated.')
                )
                ->push(...$extraErrorChecker($institutionUser))
                ->all());
    }

    protected function userCanBeSelectedAsClientRule(): ModelBelongsToInstitutionRule
    {
        return static::existsActiveUserInSameInstitution(
            function (InstitutionUser $institutionUser) {
                if ($institutionUser->belongsToTranslationAgency()) {
                    return ['The user referenced by :attribute may not belong to a translation agency institution.'];
                }

                if ($institutionUser->hasPrivileges(PrivilegeKey::CreateProject)) {
                    return [];
                }

                return ['The user referenced by :attribute must have the CREATE_PROJECT privilege.'];
            }
        );
    }

    protected function userCanBeSelectedAsManagerRule(): ModelBelongsToInstitutionRule
    {
        return static::existsActiveUserInSameInstitution(
            function (InstitutionUser $institutionUser) {
                if ($institutionUser->hasPrivileges(PrivilegeKey::ReceiveProject)
                    || $institutionUser->id === Auth::user()?->institutionUserId
                    && Auth::hasPrivilege(PrivilegeKey::ManageProject->value)) {
                    return [];
                }

                return ['The user referenced by :attribute must either (a) have the RECEIVE_PROJECT privilege or (b) be current acting user with privilege MANAGE_PROJECT.'];
            }
        );
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $validated = $validator->validated();
                if (count(Arr::get($validated, 'help_files', [])) !== count(Arr::get($validated, 'help_file_types', []))) {
                    $validator->errors()->add('help_file_types', 'The amount of \'help_file_types\' must be equal to the amount of \'help_files\'');
                }

                if (filled($deadline = data_get($validated, 'deadline_at')) && filled($eventStart = data_get($validated, 'event_start_at'))) {
                    if ($deadline < $eventStart) {
                        $validator->errors()->add('event_start_at', 'Event start datetime should be less or equal to deadline');
                    }
                }

                if ($this->isCalendarProject()) {
                    if (filled($eventStart = data_get($validated, 'event_start_at')) && filled($eventEnd = data_get($validated, 'event_end_at'))) {
                        if ($eventEnd <= $eventStart) {
                            $validator->errors()->add('event_end_at', 'Event end datetime must be after event start datetime.');
                        }
                    }
                }

                if ($this->input('is_calendar_project') === true
                    && filled($this->input('type_classifier_value_id'))
                    && !ClassifierValue::isCalendarProjectType($this->input('type_classifier_value_id'))) {
                    $validator->errors()->add('is_calendar_project', 'The is_calendar_project flag contradicts the selected project type.');
                }
            },
        ];
    }

    private ?bool $isCalendarProjectCached = null;

    private function isCalendarProject(): bool
    {
        return $this->isCalendarProjectCached ??= ClassifierValue::isCalendarProjectType($this->input('type_classifier_value_id'))
            || $this->input('is_calendar_project', false);
    }
}
