<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Http\Controllers\API\ProjectController;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Media;
use App\Models\Project;
use App\Models\SubProject;
use Closure;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\AuthHelpers;
use Tests\TestCase;
use Throwable;

class ProjectControllerStoreTest extends TestCase
{
    /** @return array<array{
     *     Closure(InstitutionUser): array,
     *     Closure(TestCase, TestResponse, array): void,
     * }>
     *
     * @throws Throwable
     */
    public static function provideValidPayloadCreatorsAndExtraAssertions(): array
    {
        return [
            'Required fields only' => [
                static::createExampleValidPayload(...),
                function () {
                },
            ],
            'Include optional fields for project type of value "T"' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'reference_number' => '1234',
                    'comments' => "Project\n\n1234",
                ],
                function () {
                },
            ],
            'Project type "Suuline tõlge"' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'type_classifier_value_id' => ClassifierValue::where([
                        'type' => ClassifierValueType::ProjectType,
                        'value' => 'S',
                    ])->firstOrFail()->id,
                    'reference_number' => '4321',
                    'comments' => "Project\n\n4321",
                    'event_start_at' => '2020-12-31T12:00:00Z',
                ],
                function () {
                },
            ],
            'Assigning a project manager from same institution' => [
                fn (InstitutionUser $actingUser) => [
                    ...static::createExampleValidPayload(),
                    'manager_institution_user_id' => InstitutionUser::factory()
                        ->state(['institution' => $actingUser->institution])
                        ->createWithPrivileges(PrivilegeKey::ReceiveAndManageProject)
                        ->id,
                ],
                function () {
                },
            ],
            'Creating a project for a different client' => [
                fn (InstitutionUser $actingUser) => [
                    ...static::createExampleValidPayload(),
                    'client_institution_user_id' => InstitutionUser::factory()
                        ->state(['institution' => $actingUser->institution])
                        ->createWithPrivileges(PrivilegeKey::CreateProject)
                        ->id,
                ],
                function () {
                },
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
                function () {
                },
            ],
            'Creating a project with source files' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'source_files' => [
                        UploadedFile::fake()->create('source1.pdf', 1024, 'application/pdf'),
                        UploadedFile::fake()->create('source2.docx', 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                    ],
                ],
                function (TestCase $testCase, TestResponse $testResponse) {
                    $project = Project::find($testResponse->json('data.id'));
                    $testCase->assertModelExists($project);
                    $testCase->assertCount(2, $project->getSourceFiles());
                    $project->getSourceFiles()->each(function (Media $media) use ($testCase) {
                        $testCase->assertEquals(Project::SOURCE_FILES_COLLECTION, $media->collection_name);
                        Storage::disk($media->disk)->assertExists($media->getPathRelativeToRoot());
                    });
                },
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
                function (TestCase $testCase, TestResponse $testResponse, array $sentPayload) {
                    $project = Project::find($testResponse->json('data.id'));
                    $testCase->assertModelExists($project);
                    $testCase->assertCount(2, $project->getHelpFiles());
                    collect($sentPayload['help_files'])
                        ->zip($sentPayload['help_file_types'])
                        ->eachSpread(function (File $helpFile, string $helpFileType) use ($testCase, $project) {
                            /** @var Media $media */
                            $media = $project->getHelpFiles()->firstWhere('file_name', $helpFile->getClientOriginalName());
                            $testCase->assertModelExists($media);
                            $testCase->assertEquals(Project::HELP_FILES_COLLECTION, $media->collection_name);
                            $testCase->assertEquals(['type' => $helpFileType], $media->custom_properties);
                            Storage::disk($media->disk)->assertExists($media->getPathRelativeToRoot());
                        });
                },
            ],
        ];
    }

    /**
     * @dataProvider provideValidPayloadCreatorsAndExtraAssertions
     *
     * @param  Closure(InstitutionUser): array  $createValidPayload
     * @param  Closure(TestCase, TestResponse, array): void  $performExtraAssertions
     *
     * @throws Throwable
     */
    public function test_project_is_created_when_payload_valid(Closure $createValidPayload, Closure $performExtraAssertions): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class); // declaring seeder on class level ($seeder=...) causes it to not run when running all tests

        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject, PrivilegeKey::ChangeClient);

        $payload = $createValidPayload($actingUser);
        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload
            );

        $response->assertCreated();

        $project = Project::find($response->json('data.id'));
        $this->assertModelExists($project);

        $this->assertArrayHasSubsetIgnoringOrder(
            [
                'reference_number' => Arr::get($payload, 'reference_number'),
                'institution_id' => $actingUser->institution['id'],
                'type_classifier_value_id' => Arr::get($payload, 'type_classifier_value_id'),
                'workflow_template_id' => ClassifierValue::findOrFail(Arr::get($payload, 'type_classifier_value_id'))->projectTypeConfig->workflow_process_definition_id,
                'translation_domain_classifier_value_id' => Arr::get($payload, 'translation_domain_classifier_value_id'),
                'comments' => Arr::get($payload, 'comments'),
                'deadline_at' => Carbon::parse(Arr::get($payload, 'deadline_at'))->toIso8601ZuluString('microsecond'),
                'event_start_at' => Arr::has($payload, 'event_start_at')
                    ? Carbon::parse(Arr::get($payload, 'event_start_at'))->toIso8601ZuluString('microsecond')
                    : null,
                'manager_institution_user_id' => Arr::get($payload, 'manager_institution_user_id'),
                'client_institution_user_id' => Arr::get($payload, 'client_institution_user_id', $actingUser->id),
            ],
            $project->jsonSerialize()
        );

        $this->assertArrayHasSubsetIgnoringOrder(
            [
                'id' => $project->id,
                'ext_id' => $project->ext_id,
                'reference_number' => $project->reference_number,
                'institution_id' => $project->institution_id,
                'type_classifier_value' => ['id' => $project->type_classifier_value_id],
                'workflow_template_id' => $project->workflow_template_id,
                'translation_domain_classifier_value' => ['id' => $project->translation_domain_classifier_value_id],
                'comments' => $project->comments,
                'deadline_at' => $project->deadline_at->toIso8601ZuluString('microsecond'),
                'event_start_at' => $project->event_start_at?->toIso8601ZuluString('microsecond'),
                'manager_institution_user' => isset($project->manager_institution_user_id)
                    ? ['id' => $project->manager_institution_user_id]
                    : null,
                'client_institution_user' => ['id' => $project->client_institution_user_id],
            ],
            $response->json('data')
        );

        $this->assertCount(
            count($payload['destination_language_classifier_value_ids']),
            $project->subProjects
        );

        collect($project->subProjects)
            ->zip($payload['destination_language_classifier_value_ids'])
            ->eachSpread(function (SubProject $subProject, string $destination_language_classifier_value_id) use ($payload) {
                $this->assertEquals(
                    $payload['source_language_classifier_value_id'],
                    $subProject->source_language_classifier_value_id
                );
                $this->assertEquals(
                    $destination_language_classifier_value_id,
                    $subProject->destination_language_classifier_value_id
                );
            });

        $performExtraAssertions($this, $response, $payload);
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
                    'value' => 'S',
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

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $createInvalidPayload($actingUser)
            );

        $response->assertUnprocessable();
        $this->assertDatabaseMissing(
            Project::class,
            ['institution_id' => $actingUser->institution['id']]
        );
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

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $createPayload($actingUser)
            );

        $response->assertForbidden();
        $this->assertDatabaseMissing(
            Project::class,
            ['institution_id' => $actingUser->institution['id']]
        );
    }

    /** @throws Throwable */
    public static function createExampleValidPayload(): array
    {
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->get();

        throw_unless($languages->count() > 1);

        [$sourceLanguage, $destinationLanguage] = $languages;

        return [
            'type_classifier_value_id' => ClassifierValue::where(['type' => ClassifierValueType::ProjectType, 'value' => 'T'])
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
}
