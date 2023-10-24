<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Rules\ModelBelongsToInstitutionRule;
use App\Rules\TranslationSourceFileValidator;
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
                'type_classifier_value_id',
                'deadline_at',
                'source_language_classifier_value_id',
                'destination_language_classifier_value_ids',
            ],
            properties: [
                new OA\Property(property: 'type_classifier_value_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'manager_institution_user_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'client_institution_user_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'reference_number', type: 'string', nullable: true),
                new OA\Property(property: 'comments', type: 'string', nullable: true),
                new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time', example: '2020-12-31T12:00:00Z'),
                new OA\Property(property: 'translation_domain_classifier_value_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'source_language_classifier_value_id', type: 'string', format: 'uuid'),
                new OA\Property(
                    property: 'event_start_at',
                    description: 'Only allowed if project type supports start date.',
                    type: 'string',
                    format: 'date-time',
                    example: '2020-12-31T12:00:00Z',
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
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'binary'),
                    minItems: 1
                ),
                new OA\Property(
                    property: 'destination_language_classifier_value_ids',
                    type: 'array',
                    items: new OA\Items(type: 'string', format: 'uuid'),
                    minItems: 1
                ),
            ],
            type: 'object'
        ),
        encoding: [
            'type_classifier_value_id' => ['contentType' => 'application/json'],
            'reference_number' => ['contentType' => 'application/json'],
            'manager_institution_user_id' => ['contentType' => 'application/json'],
            'comments' => ['contentType' => 'application/json'],
            'deadline_at' => ['contentType' => 'application/json'],
            'event_start_at' => ['contentType' => 'application/json'],
            'translation_domain_classifier_value_id' => ['contentType' => 'application/json'],
            'source_language_classifier_value_id' => ['contentType' => 'application/json'],
            'destination_language_classifier_value_ids' => ['contentType' => 'application/json'],
            'help_file_types' => ['contentType' => 'application/json'],
            'source_files' => ['contentType' => 'application/octet-stream'],
            'help_files' => ['contentType' => 'application/octet-stream'],
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
            'type_classifier_value_id' => [
                'required',
                'uuid',
                'bail',
                Rule::exists(ProjectTypeConfig::class, 'type_classifier_value_id'),
            ],
            'event_start_at' => [
                'nullable',
                'date_format:Y-m-d\\TH:i:s\\Z', // only UTC (zero offset)
                'bail',
                Rule::prohibitedIf(fn () => ! $this->isProjectTypeSupportingEventStartDate()),
                Rule::requiredIf(fn () => $this->isProjectTypeSupportingEventStartDate()),
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
            'comments' => ['nullable', 'string'],
            'deadline_at' => ['required', 'date_format:Y-m-d\\TH:i:s\\Z'], // only UTC (zero offset)
            'translation_domain_classifier_value_id' => [
                'required',
                'uuid',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::TranslationDomain),
            ],
            'source_files' => ['array', 'min:1'],
            'source_files.*' => [TranslationSourceFileValidator::createRule()],
            'help_files' => ['required_with:help_file_types', 'array'],
            'help_files.*' => ['file'],
            'help_file_types' => ['required_with:help_files', 'array'],
            'help_file_types.*' => [Rule::in(Project::HELP_FILE_TYPES)],
            'source_language_classifier_value_id' => [
                'required',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
            'destination_language_classifier_value_ids' => ['required', 'array'],
            'destination_language_classifier_value_ids.*' => [
                'required',
                'string',
                'bail',
                Rule::exists(ClassifierValue::class, 'id')->where('type', ClassifierValueType::Language),
            ],
        ];
    }

    private function isProjectTypeSupportingEventStartDate(): bool
    {
        return ClassifierValue::find($this->get('type_classifier_value_id'))
            ?->projectTypeConfig()
            ?->where('is_start_date_supported', true)
            ?->exists() ?? false;

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
                if ($institutionUser->hasPrivileges(PrivilegeKey::ReceiveAndManageProject)
                    || $institutionUser->id === Auth::user()?->institutionUserId
                    && Auth::hasPrivilege(PrivilegeKey::ManageProject->value)) {
                    return [];
                }

                return ['The user referenced by :attribute must either (a) have the RECEIVE_AND_MANAGE_PROJECT privilege or (b) be current acting user with privilege MANAGE_PROJECT.'];
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
            },
        ];

    }
}
