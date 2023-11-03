<?php

namespace Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Http\Controllers\API\ProjectController;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Media;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use AuditLogClient\Enums\AuditLogEventFailureType;
use AuditLogClient\Enums\AuditLogEventObjectType;
use AuditLogClient\Enums\AuditLogEventType;
use Closure;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\AuditLogTestCase;
use Tests\AuthHelpers;
use Tests\TestCase;
use Throwable;

class ProjectControllerStoreAuditLogPublishedTest extends AuditLogTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Date::setTestNow(Date::now());
        Http::fake([
            rtrim(env('CAMUNDA_API_URL'), '/').'/*' => Http::response(['hi']),
        ]);

    }

    /** @return array<array{
     *     Closure(InstitutionUser): array,
     * }>
     *
     * @throws Throwable
     */
    public static function provideValidPayloadCreators(): array
    {
        return [
            'Required fields only' => [
                static::createExampleValidPayload(...),
            ],
            'Include optional fields for project type of value "T"' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'reference_number' => '1234',
                    'comments' => "Project\n\n1234",
                ],
            ],
            'Project type "Suuline tõlge"' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'type_classifier_value_id' => ClassifierValue::where([
                        'type' => ClassifierValueType::ProjectType,
                        'value' => 'ORAL_TRANSLATION',
                    ])->firstOrFail()->id,
                    'reference_number' => '4321',
                    'comments' => "Project\n\n4321",
                    'event_start_at' => '2020-12-31T12:00:00Z',
                ],
            ],
            'Assigning a project manager from same institution' => [
                fn (InstitutionUser $actingUser) => [
                    ...static::createExampleValidPayload(),
                    'manager_institution_user_id' => InstitutionUser::factory()
                        ->state(['institution' => $actingUser->institution])
                        ->createWithPrivileges(PrivilegeKey::ReceiveAndManageProject)
                        ->id,
                ],
            ],
            'Creating a project for a different client' => [
                fn (InstitutionUser $actingUser) => [
                    ...static::createExampleValidPayload(),
                    'client_institution_user_id' => InstitutionUser::factory()
                        ->state(['institution' => $actingUser->institution])
                        ->createWithPrivileges(PrivilegeKey::CreateProject)
                        ->id,
                ],
            ],
            'Creating a project with multiple destination languages' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'destination_language_classifier_value_ids' => ClassifierValue::where('type', ClassifierValueType::Language)
                        ->whereNot('id', static::createExampleValidPayload()['source_language_classifier_value_id'])
                        ->limit(3)
                        ->pluck('id')
                        ->all(),
                ],
            ],
            'Creating a project with source files' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'source_files' => [
                        UploadedFile::fake()->create('source1.pdf', 1024, 'application/pdf'),
                        UploadedFile::fake()->create('source2.docx', 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                    ],
                ],
            ],
            'Creating a project with help files' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'help_files' => [
                        UploadedFile::fake()->create('help1.pdf', 1024, 'application/pdf'),
                        UploadedFile::fake()->create('help2.docx', 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                    ],
                    'help_file_types' => ['REFERENCE_FILE', 'STYLE_GUIDE'],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideValidPayloadCreators
     *
     * @param  Closure(InstitutionUser): array  $createValidPayload
     *
     * @throws Throwable
     */
    public function test_project_is_created_when_payload_valid(Closure $createValidPayload): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class); // declaring seeder on class level ($seeder=...) causes it to not run when running all tests

        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject, PrivilegeKey::ChangeClient);

        $payload = collect($createValidPayload($actingUser));

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload->all()
            );

        $response->assertSuccessful();

        $this->assertCreateProjectMessagePublished(Project::findOrFail($response->json('data.id')));
    }

    /** @return array<array{Closure(InstitutionUser): array}>
     * @throws Throwable
     */
    public static function provideInvalidPayloadCreators(): array
    {
        return [
            'Missing type_classifier_value_id' => [fn () => [
                ...static::createExampleValidPayload(),
                'type_classifier_value_id' => '',
            ]],
            'type_classifier_value_id references classifier value of wrong type' => [fn () => [
                ...static::createExampleValidPayload(),
                'type_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::Language)
                    ->firstOrFail()
                    ->id,
            ]],
            'manager_institution_user_id from another institution' => [fn () => [
                ...static::createExampleValidPayload(),
                'manager_institution_user_id' => InstitutionUser::factory()
                    ->createWithPrivileges(PrivilegeKey::ReceiveAndManageProject)
                    ->id,
            ]],
            'manager_institution_user_id without RECEIVE_PROEJCT privilege' => [fn (InstitutionUser $actingUser) => [
                ...static::createExampleValidPayload(),
                'manager_institution_user_id' => InstitutionUser::factory()
                    ->state(['institution' => $actingUser->institution])
                    ->createWithAllPrivilegesExcept(PrivilegeKey::ReceiveAndManageProject)
                    ->id,
            ]],
            'client_institution_user_id from another institution' => [fn () => [
                ...static::createExampleValidPayload(),
                'client_institution_user_id' => InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject)->id,
            ]],
            'client_institution_user_id without CREATE_RPOJECT privilege' => [fn (InstitutionUser $actingUser) => [
                ...static::createExampleValidPayload(),
                'client_institution_user_id' => InstitutionUser::factory()
                    ->state(['institution' => $actingUser->institution])
                    ->createWithAllPrivilegesExcept(PrivilegeKey::CreateProject)
                    ->id,
            ]],
            'Empty deadline_at' => [fn () => [
                ...static::createExampleValidPayload(),
                'deadline_at' => null,
            ]],
            'deadline_at with timezone' => [fn () => [
                ...static::createExampleValidPayload(),
                'deadline_at' => '2020-12-12T02:00:00+03:00',
            ]],
            'event_start_at with timezone' => [fn () => [
                ...static::createExampleValidPayload(),
                'deadline_at' => '2020-12-12T02:00:00+03:00',
            ]],
            'source_files not uploaded files' => [fn () => [
                ...static::createExampleValidPayload(),
                'source_files' => ['file.pdf'],
            ]],
            'source_files contains a uploaded file with wrong extension' => [fn () => [
                ...static::createExampleValidPayload(),
                'source_files' => [UploadedFile::fake()->create('source.zip', 1024, 'application/zip')],
            ]],
            'help_files not uploaded files' => [fn () => [
                ...static::createExampleValidPayload(),
                'help_files' => ['file.pdf'],
                'help_file_types' => ['REFERENCE_FILE'],
            ]],
            'help_file_types not same length as uploaded help_files' => [fn () => [
                ...static::createExampleValidPayload(),
                'help_files' => ['source_files' => [UploadedFile::fake()->create('source.pdf', 1024, 'application/pdf')]],
                'help_file_types' => [],
            ]],
            'help_file_types contains unknown type' => [fn () => [
                ...static::createExampleValidPayload(),
                'help_files' => ['source_files' => [UploadedFile::fake()->create('source.pdf', 1024, 'application/pdf')]],
                'help_file_types' => ['SNAKE_JAZZ'],
            ]],
            'Missing source_language_classifier_value_id' => [fn () => [
                ...static::createExampleValidPayload(),
                'source_language_classifier_value_id' => '',
            ]],
            'source_language_classifier_value_id references classifier value of wrong type' => [fn () => [
                ...static::createExampleValidPayload(),
                'source_language_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)->firstOrFail()->id,
            ]],
            'Empty destination_language_classifier_value_ids' => [fn () => [
                ...static::createExampleValidPayload(),
                'destination_language_classifier_value_ids' => [],
            ]],
            'destination_language_classifier_value_ids references classifier value of wrong type' => [fn () => [
                ...static::createExampleValidPayload(),
                'destination_language_classifier_value_ids' => [
                    ClassifierValue::where('type', ClassifierValueType::Language)->firstOrFail()->id,
                    ClassifierValue::where('type', ClassifierValueType::FileType)->firstOrFail()->id,
                ],
            ]],
            'Project type "Suuline tõlge" without event_start_at' => [fn () => [
                ...static::createExampleValidPayload(),
                'type_classifier_value_id' => ClassifierValue::where([
                    'type' => ClassifierValueType::ProjectType,
                    'value' => 'ORAL_TRANSLATION',
                ])->firstOrFail()->id,
                'reference_number' => '4321',
                'comments' => "Project\n\n4321",
            ]],
        ];
    }

    /**
     * @dataProvider provideInvalidPayloadCreators
     *
     * @param  Closure(InstitutionUser): array  $createInvalidPayload
     *
     * @throws Throwable
     */
    public function test_invalid_payload_results_in_unprocessable_entity_response(Closure $createInvalidPayload): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class); // declaring seeder on class level ($seeder=...) causes it to not run when running all tests
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $payload = $createInvalidPayload($actingUser);
        $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload
            )
            ->assertUnprocessable();

        $this->assertCreateProjectFailureMessagePublished(AuditLogEventFailureType::UNPROCESSABLE_ENTITY, $payload);
    }

    /**
     * @return array<array{
     *     Closure(): InstitutionUser,
     *     Closure(InstitutionUser): array
     * }>
     */
    public static function provideActingUserModifiersAndForbiddenPayloadCreators(): array
    {
        return [
            'Usual request without acting user having CREATE_PROJECT privilege' => [
                fn () => InstitutionUser::factory()->createWithAllPrivilegesExcept(PrivilegeKey::CreateProject),
                fn () => static::createExampleValidPayload(),
            ],
            'Request which assigns a different client without acting user having CHANGE_CLIENT privilege' => [
                fn () => InstitutionUser::factory()->createWithAllPrivilegesExcept(PrivilegeKey::ChangeClient),
                fn (InstitutionUser $actingUser) => [
                    ...static::createExampleValidPayload(),
                    'client_institution_user_id' => InstitutionUser::factory()
                        ->state(['institution' => $actingUser->institution])
                        ->createWithPrivileges(PrivilegeKey::CreateProject)
                        ->id,
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideActingUserModifiersAndForbiddenPayloadCreators
     *
     * @param  Closure(): InstitutionUser  $createActingUser
     * @param  Closure(InstitutionUser): array  $createPayload
     *
     * @throws Throwable
     */
    public function test_unprivileged_acting_user_results_in_forbidden_response(Closure $createActingUser, Closure $createPayload): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class); // declaring seeder on class level ($seeder=...) causes it to not run when running all tests
        $actingUser = $createActingUser();

        $payload = $createPayload($actingUser);

        $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload
            )
            ->assertForbidden();

        $this->assertCreateProjectFailureMessagePublished(AuditLogEventFailureType::FORBIDDEN, $payload);
    }


    /** @throws Throwable */
    public static function createExampleValidPayload(): array
    {
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->get();

        throw_unless($languages->count() > 1);

        [$sourceLanguage, $destinationLanguage] = $languages;

        return [
            'type_classifier_value_id' => ClassifierValue::where(['type' => ClassifierValueType::ProjectType, 'value' => 'CAT_TRANSLATION'])
                ->firstOrFail()
                ->id,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()
                ->id,
            'deadline_at' => Date::now()->addWeek()->toIso8601ZuluString(),
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_ids' => [
                $destinationLanguage->id,
            ],
        ];
    }

    private function assertCreateProjectMessagePublished(Project $project): void {
        $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
        $this->assertMessageRepresentsProjectCreation($actualMessageBody, $project);
    }
    private function assertMessageRepresentsProjectCreation(array $actualMessageBody, Project $project): void {
        $expectedMessageBodySubset = [
            'event_type' => AuditLogEventType::CreateObject->value,
            'happened_at' => Date::getTestNow()->toISOString(),
            'failure_type' => null
        ];

        $this->assertArrayHasSubsetIgnoringOrder(
            collect($expectedMessageBodySubset)->except('event_parameters')->all(),
            collect($actualMessageBody)->except('event_parameters')->all(),
        );
        $this->assertArraysEqualIgnoringOrder(
            [
                'object_type' => AuditLogEventObjectType::Project->value,
                'object_data' => $project->getAuditLogRepresentation(),
            ],
            data_get($actualMessageBody, 'event_parameters'),
        );
    }

    private function assertCreateProjectFailureMessagePublished(AuditLogEventFailureType $failureType, array $expectedInput): void {
        $actualMessageBody = $this->retrieveLatestAuditLogMessageBody();
        $this->assertMessageRepresentsProjectCreationFailure($actualMessageBody, $failureType, $expectedInput);
    }

    private function assertMessageRepresentsProjectCreationFailure(array $actualMessageBody, AuditLogEventFailureType $failureType, array $expectedInput): void {
        $expectedMessageBodySubset = [
            'event_type' => AuditLogEventType::CreateObject->value,
            'happened_at' => Date::getTestNow()->toISOString(),
            'failure_type' => $failureType->value
        ];

        $this->assertArrayHasSubsetIgnoringOrder(
            collect($expectedMessageBodySubset)->except('event_parameters')->all(),
            collect($actualMessageBody)->except('event_parameters')->all(),
        );
        $this->assertArraysEqualIgnoringOrder(
            [
                'object_type' => AuditLogEventObjectType::Project->value,
                'input' => collect($expectedInput)->except(['input.source_files', 'input.help_files.source_files'])->all(),
            ],
            collect($actualMessageBody)->except(['event_parameters.input.source_files', 'event_parameters.input.help_files.source_files'])->get('event_parameters')
        );
    }
}
