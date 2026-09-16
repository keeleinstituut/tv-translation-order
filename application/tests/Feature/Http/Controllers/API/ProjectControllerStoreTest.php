<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Http\Controllers\API\ProjectController;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Media;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use App\Models\Tag;
use Closure;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\AuthHelpers;
use Tests\FakesRealFiles;
use Tests\TestCase;
use Throwable;

class ProjectControllerStoreTest extends TestCase
{
    use FakesRealFiles;


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
            'Assigning a project manager from same institution' => [
                fn (InstitutionUser $actingUser) => [
                    ...static::createExampleValidPayload(),
                    'manager_institution_user_id' => InstitutionUser::factory()
                        ->state(['institution' => $actingUser->institution])
                        ->createWithPrivileges(PrivilegeKey::ReceiveProject)
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
            'Creating a project with a comment' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'comment' => 'Initial project comment',
                ],
                function (TestCase $testCase, TestResponse $testResponse) {
                    $project = Project::findOrFail($testResponse->json('data.id'));
                    $testCase->assertDatabaseHas(ProjectComment::class, [
                        'project_id' => $project->id,
                        'comment' => 'Initial project comment',
                    ]);
                    $testCase->assertCount(1, $testResponse->json('data.project_comments'));
                    $testCase->assertEquals('Initial project comment', $testResponse->json('data.project_comments.0.comment'));
                    $testCase->assertNotNull(
                        $testResponse->json('data.project_comments.0.institution_user'),
                        'project_comments should include institution_user'
                    );
                    $testCase->assertEquals(
                        $testResponse->json('data.project_comments.0.institution_user_id'),
                        $testResponse->json('data.project_comments.0.institution_user.id'),
                    );
                },
            ],
            'Creating a project with source files' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'source_files' => [
                        static::fakePdf('source1.pdf'),
                        static::fakeDocx('source2.docx'),
                    ],
                ],
                function (TestCase $testCase, TestResponse $testResponse) {
                    $project = Project::findOrFail($testResponse->json('data.id'));
                    $testCase->assertCount(2, $project->sourceFiles);
                    $project->sourceFiles->each(function (Media $media) use ($testCase) {
                        $testCase->assertEquals(Project::SOURCE_FILES_COLLECTION, $media->collection_name);
                        Storage::disk($media->disk)->assertExists($media->getPathRelativeToRoot());
                    });
                },
            ],
            'Creating a project with help files' => [
                fn () => [
                    ...static::createExampleValidPayload(),
                    'help_files' => [
                        static::fakePdf('help1.pdf'),
                        static::fakeDocx('help2.docx'),
                    ],
                    'help_file_types' => ['REFERENCE_FILE', 'STYLE_GUIDE'],
                ],
                function (TestCase $testCase, TestResponse $testResponse, array $sentPayload) {
                    $project = Project::findOrFail($testResponse->json('data.id'));
                    $testCase->assertCount(2, $project->helpFiles);
                    collect($sentPayload['help_files'])
                        ->zip($sentPayload['help_file_types'])
                        ->eachSpread(function (File $helpFile, string $helpFileType) use ($testCase, $project) {
                            /** @var Media $media */
                            $media = $project->helpFiles->firstWhere('file_name', $helpFile->getClientOriginalName());
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
     * @param  Closure(InstitutionUser): array  $createValidPayload
     * @param  Closure(TestCase, TestResponse, array): void  $performExtraAssertions
     *
     * @throws Throwable
     */
    #[DataProvider('provideValidPayloadCreatorsAndExtraAssertions')]
    public function test_project_is_created_when_payload_valid(Closure $createValidPayload, Closure $performExtraAssertions): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class); // declaring seeder on class level ($seeder=...) causes it to not run when running all tests
        $actingUser = InstitutionUser::factory()->createWithPrivileges(
            PrivilegeKey::CreateProject,
            PrivilegeKey::ChangeClient,
            PrivilegeKey::ChangeProjectManager
        );

        $payload = collect($createValidPayload($actingUser));
        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload->all()
            );

        $response->assertCreated();

        $project = Project::find($response->json('data.id'));
        $this->assertModelExists($project);

        $this->assertArrayHasSubsetIgnoringOrder(
            [
                'reference_number' => $payload->get('reference_number'),
                'institution_id' => $actingUser->institution['id'],
                'type_classifier_value_id' => $payload->get('type_classifier_value_id'),
                'workflow_template_id' => Config::get('app.workflows.process_definitions.project'),
                'translation_domain_classifier_value_id' => $payload->get('translation_domain_classifier_value_id'),
                'comments' => $payload->get('comments'),
                'deadline_at' => Carbon::parse($payload->get('deadline_at'))->toIso8601ZuluString('microsecond'),
                'event_start_at' => $payload->has('event_start_at')
                    ? Carbon::parse($payload->get('event_start_at'))->toIso8601ZuluString('microsecond')
                    : null,
                'manager_institution_user_id' => $payload->get('manager_institution_user_id'),
                'client_institution_user_id' => $payload->get('client_institution_user_id', $actingUser->id),
                'status' => $payload->has('manager_institution_user_id')
                    ? ProjectStatus::Registered->value
                    : ProjectStatus::New->value,
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
                'status' => $project->status->value,
                //                'cost' => $project->computeCost(),
            ],
            $response->json('data')
        );

        $this->assertCount(
            count($payload['destination_language_classifier_value_ids']),
            $project->subProjects
        );

        collect($project->subProjects->sortBy('destination_language_classifier_value_id')->values())
            ->zip(collect($payload['destination_language_classifier_value_ids'])->sort()->values())
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

        $performExtraAssertions($this, $response, $payload->all());
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
                    ->createWithPrivileges(PrivilegeKey::ReceiveProject)
                    ->id,
            ]],
            'manager_institution_user_id without RECEIVE_PROEJCT privilege' => [fn (InstitutionUser $actingUser) => [
                ...static::createExampleValidPayload(),
                'manager_institution_user_id' => InstitutionUser::factory()
                    ->state(['institution' => $actingUser->institution])
                    ->createWithAllPrivilegesExcept(PrivilegeKey::ReceiveProject)
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
            'source_files contains a uploaded file with wrong extension' => [fn () => [
                ...static::createExampleValidPayload(),
                'source_files' => [UploadedFile::fake()->create('source.exe', 1024, 'application/x-msdownload')],
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
            'Project type "Suuline tõlge" without event_start_at' => [fn () => collect(static::createExampleValidPayload())
                ->except('event_start_at')
                ->merge([
                    'type_classifier_value_id' => ProjectTypeConfig::where('type_classifier_value_id', function ($query) {
                        $query->select('id')
                            ->from('cached_classifier_values')
                            ->where('type', ClassifierValueType::ProjectType->value)
                            ->where('value', 'ORAL_TRANSLATION')
                            ->limit(1);
                    })->firstOrFail()->type_classifier_value_id,
                    'reference_number' => '4321',
                    'comments' => "Project\n\n4321",
                ])->toArray()],
        ];
    }

    /**
     * @param  Closure(InstitutionUser): array  $createInvalidPayload
     *
     * @throws Throwable
     */
    #[DataProvider('provideInvalidPayloadCreators')]
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
     * @param  Closure(): InstitutionUser  $createActingUser
     * @param  Closure(InstitutionUser): array  $createPayload
     *
     * @throws Throwable
     */
    #[DataProvider('provideActingUserModifiersAndForbiddenPayloadCreators')]
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
    public function test_no_project_comment_created_when_comment_not_provided(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                static::createExampleValidPayload()
            );

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertDatabaseMissing(ProjectComment::class, [
            'project_id' => $project->id,
        ]);
    }

    /** @throws Throwable */
    public function test_project_is_created_with_translation_domain_tags(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $orderTags = Tag::factory()->count(2)->typeOrder()->create([
            'institution_id' => $actingUser->institution['id'],
        ]);
        $translationDomainTags = Tag::factory()->count(2)->translationDomain()->create();
        $allTags = $orderTags->merge($translationDomainTags);

        $payload = [
            ...static::createExampleValidPayload(),
            'tags' => $allTags->pluck('id')->toArray(),
        ];

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload
            );

        $response->assertCreated();

        $project = Project::find($response->json('data.id'));
        $this->assertModelExists($project);
        $this->assertEqualsCanonicalizing(
            $allTags->pluck('id')->toArray(),
            $project->tags->pluck('id')->toArray()
        );
    }

    /** @throws Throwable */
    public function test_project_creation_with_vendor_skill_tags_returns_422(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $vendorSkillTags = Tag::factory()->count(2)->vendorSkills()->create();

        $payload = [
            ...static::createExampleValidPayload(),
            'tags' => $vendorSkillTags->pluck('id')->toArray(),
        ];

        $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload
            )
            ->assertUnprocessable();
    }

    /** @throws Throwable */
    public function test_project_creation_with_vendor_type_tags_returns_422(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $vendorTags = Tag::factory()->count(2)->typeVendor()->create([
            'institution_id' => $actingUser->institution['id'],
        ]);

        $payload = [
            ...static::createExampleValidPayload(),
            'tags' => $vendorTags->pluck('id')->toArray(),
        ];

        $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                $payload
            )
            ->assertUnprocessable();
    }

    /** @throws Throwable */
    public static function createExampleValidPayload(): array
    {
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->get();

        throw_unless($languages->count() > 1);

        [$sourceLanguage, $destinationLanguage] = $languages;

        $projectTypeConfig = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) {
            $query->where('value', 'TRANSLATION');
        })->firstOrFail();

        $payload = [
            'type_classifier_value_id' => $projectTypeConfig->type_classifier_value_id,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()
                ->id,
            'deadline_at' => Date::now()->addWeek()->toIso8601ZuluString(),
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_ids' => [
                $destinationLanguage->id,
            ],
        ];

        if (ClassifierValue::isProjectTypeSupportingEventStartDate($projectTypeConfig->type_classifier_value_id)) {
            $payload['event_start_at'] = Date::now()->addDays(5)->toIso8601ZuluString();
        }

        return $payload;
    }

    /** POST_TRANSLATION supports event dates but is not a calendar project type. */
    private static function createPostTranslationValidPayload(array $overrides = []): array
    {
        $languages = ClassifierValue::where('type', ClassifierValueType::Language)->get();

        throw_unless($languages->count() > 1);

        [$sourceLanguage, $destinationLanguage] = $languages;

        $projectTypeConfig = ProjectTypeConfig::whereHas('typeClassifierValue', function ($query) {
            $query->where('value', 'POST_TRANSLATION');
        })->firstOrFail();

        return array_merge([
            'type_classifier_value_id' => $projectTypeConfig->type_classifier_value_id,
            'translation_domain_classifier_value_id' => ClassifierValue::where('type', ClassifierValueType::TranslationDomain)
                ->firstOrFail()
                ->id,
            'source_language_classifier_value_id' => $sourceLanguage->id,
            'destination_language_classifier_value_ids' => [
                $destinationLanguage->id,
            ],
            'event_start_at' => Date::now()->addDays(5)->toIso8601ZuluString(),
            'event_end_at' => Date::now()->addDays(5)->addHour()->toIso8601ZuluString(),
            'service_type' => 'REMOTE',
            'meeting_link' => 'https://meet.example.com/post-translation',
        ], $overrides);
    }

    /** @throws Throwable */
    public function test_event_type_project_creation_succeeds_without_deadline_at(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(
                action([ProjectController::class, 'store']),
                static::createPostTranslationValidPayload()
            );

        $response->assertCreated();

        $project = Project::findOrFail($response->json('data.id'));
        $this->assertNull($project->deadline_at);
        $this->assertNotNull($project->event_start_at);
        $this->assertNotNull($project->event_end_at);
        $this->assertFalse($project->is_calendar_project);
    }

    /** @throws Throwable */
    public function test_event_type_project_creation_requires_event_start_at(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $payload = collect(static::createPostTranslationValidPayload())->except('event_start_at')->toArray();

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'store']), $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['event_start_at']);
    }

    /** @throws Throwable */
    public function test_event_type_project_creation_requires_event_end_at(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $payload = collect(static::createPostTranslationValidPayload())->except('event_end_at')->toArray();

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'store']), $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['event_end_at']);
    }

    /** @throws Throwable */
    public function test_event_type_project_creation_requires_service_type(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $payload = collect(static::createPostTranslationValidPayload())->except('service_type')->toArray();

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'store']), $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['service_type']);
    }

    /** @throws Throwable */
    public function test_event_type_project_creation_does_not_require_deadline_at(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        // No deadline_at in payload at all (not just null) — must not 422.
        $payload = static::createPostTranslationValidPayload();
        $this->assertArrayNotHasKey('deadline_at', $payload);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'store']), $payload);

        $response->assertCreated();
    }

    /** @throws Throwable */
    public function test_event_type_project_creation_rejects_event_end_before_event_start(): void
    {
        $this->seed(ClassifiersAndProjectTypesSeeder::class);
        $actingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::CreateProject);

        $payload = static::createPostTranslationValidPayload([
            'event_start_at' => Date::now()->addDays(5)->setHour(11)->toIso8601ZuluString(),
            'event_end_at' => Date::now()->addDays(5)->setHour(10)->toIso8601ZuluString(),
        ]);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($actingUser))
            ->postJson(action([ProjectController::class, 'store']), $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['event_end_at']);
    }
}
