<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\ClassifierValueType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Http\Controllers\API\ProjectController;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Project;
use App\Models\ProjectTypeConfig;
use App\Models\SubProject;
use App\Models\Tag;
use Closure;
use Database\Seeders\ClassifiersAndProjectTypesSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Assertions;
use Tests\AuthHelpers;
use Tests\TestCase;
use Throwable;

class ProjectControllerIndexTest extends TestCase
{

    protected static InstitutionUser $privilegedActingUser;

    protected static Collection $projects;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ClassifiersAndProjectTypesSeeder::class);

        static::$privilegedActingUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ViewInstitutionProjectList);
        $clientUser = InstitutionUser::factory()->create(['institution' => static::$privilegedActingUser->institution]);

        static::$projects = static::populateDatabaseWithData(static::$privilegedActingUser, $clientUser);
    }

    /** @return array<array{
     *     Closure(Collection, InstitutionUser): array,
     *     Closure(TestCase, TestResponse, array, Collection, InstitutionUser): void,
     * }>
     *
     * @throws Throwable
     */
    public static function provideValidPayloadCreatorsAndExtraAssertions(): array
    {
        return [
            'No filters' => [
                fn () => ['per_page' => 100, 'only_show_personal_projects' => false],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $response->assertJsonCount(
                        min($payload['per_page'], $projects->count()),
                        'data'
                    );
                },
            ],
            'results are sorted by creation date by default' => [
                fn () => ['per_page' => 15, 'only_show_personal_projects' => false],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $actualData = collect($response->json('data'));

                    // Verify sort order: each item should have created_at <= previous (descending order)
                    $actualData->each(function ($item, $index) use ($testCase, $actualData) {
                        if ($index > 0) {
                            $prevCreatedAt = $actualData[$index - 1]['created_at'];
                            $currCreatedAt = $item['created_at'];
                            $testCase->assertLessThanOrEqual(
                                $prevCreatedAt,
                                $currCreatedAt,
                                "Items should be sorted by created_at in descending order"
                            );
                        }
                    });

                    $testCase->assertLessThanOrEqual($payload['per_page'], $actualData->count());
                },
            ],
            'sorting by deadline_at DESC' => [
                fn () => ['per_page' => 15, 'only_show_personal_projects' => false, 'sort_by' => 'deadline_at', 'sort_order' => 'desc'],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $expectedIds = Project::whereIn('id', $projects->pluck('id'))
                        ->orderBy('deadline_at', 'desc')
                        ->take($payload['per_page'])
                        ->pluck('id')
                        ->values()
                        ->all();
                    $actualIds = collect($response->json('data'))
                        ->pluck('id')
                        ->values()
                        ->all();
                    $testCase->assertEquals($expectedIds, $actualIds);
                },
            ],
            'Selecting a specific ext_id' => [
                function (Collection $projects, InstitutionUser $actingUser) {
                    $actingUserInstitutionId = $actingUser->institution['id'];

                    $selectedProject = $projects
                        ->filter(fn(Project $project) => $project->institution_id === $actingUserInstitutionId)
                        ->groupBy('ext_id')
                        ->filter(fn(Collection $extIdProjects) => $extIdProjects->count() === 1)
                        ->map(fn(Collection $extIdProjects) => $extIdProjects->first())
                        ->firstOrFail();

                    return ['ext_id' => $selectedProject->ext_id, 'only_show_personal_projects' => false];
                },
                function (TestCase $testCase, TestResponse $response, array $payload) {
                    $response->assertJsonCount(1, 'data');
                    $response->assertJsonPath('data.0.ext_id', $payload['ext_id']);
                },
            ],
            'Hardcoded prefix of ext_id, with different casing' => [
                function (Collection $projects) {
                    throw_unless(
                        $projects->pluck('ext_id')->filter(fn ($extId) => str_contains($extId, 'HardcodedPrefix'))->count() > 1,
                        'Test data set is invalid'
                    );

                    return ['ext_id' => 'hardcodedprefiX', 'only_show_personal_projects' => false, 'per_page' => 50];
                },
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $matchingProjectIds = $projects
                        ->filter(fn (Project $project) => str_contains($project->ext_id, 'HardcodedPrefix'))
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($matchingProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $matchingProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $matchingProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Non-existent ext_id' => [
                function (Collection $projects) {
                    $nonExistentExtId = '????????????????????';
                    throw_if($projects->pluck('ext_id')->contains($nonExistentExtId), 'Test data set is invalid');

                    return ['ext_id' => $nonExistentExtId, 'only_show_personal_projects' => false];
                },
                function (TestCase $testCase, TestResponse $response) {
                    $response->assertJsonCount(0, 'data');
                },
            ],
            'Only show personal projects' => [
                function (Collection $projects, InstitutionUser $actingUser) {
                    throw_unless(
                        $projects->some(fn (Project $project) => $project->client_institution_user_id === $actingUser->id
                            || $project->manager_institution_user_id === $actingUser->id
                        ),
                        'Test data set is invalid'
                    );

                    return ['per_page' => 100, 'only_show_personal_projects' => true];
                },
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects, InstitutionUser $actingUser) {
                    $personalProjectIds = Project::where('institution_id', $actingUser->institution['id'])
                        ->where(function ($query) use ($actingUser) {
                            $query->where('manager_institution_user_id', $actingUser->id)
                                ->orWhere('client_institution_user_id', $actingUser->id);
                        })
                        ->join('entity_cache.cached_institution_users', 'projects.client_institution_user_id', '=', 'cached_institution_users.id')
                        ->pluck('projects.id')
                        ->all();

                    collect($response->json('data'))->each(function (array $responseProject) use ($personalProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $personalProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], count($personalProjectIds)),
                        'data'
                    );
                },
            ],
            'Filter by single type classifier value' => [
                fn (Collection $projects) => [
                    'type_classifier_value_ids' => [$projects->first()->type_classifier_value_id],
                    'per_page' => 15,
                    'only_show_personal_projects' => false,
                ],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedTypeProjectIds = $projects
                        ->filter(fn (Project $project) => $project->type_classifier_value_id === $payload['type_classifier_value_ids'][0])
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedTypeProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedTypeProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedTypeProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by multiple type classifier values' => [
                fn (Collection $projects) => [
                    'type_classifier_value_ids' => $projects->take(5)->pluck('type_classifier_value_id')->all(),
                    'per_page' => 50,
                    'only_show_personal_projects' => false,
                ],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedTypeProjectIds = $projects
                        ->filter(fn (Project $project) => in_array($project->type_classifier_value_id, $payload['type_classifier_value_ids']))
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedTypeProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedTypeProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedTypeProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by single tag' => [
                fn (Collection $projects) => [
                    'tag_ids' => [$projects->first()->tags->first()->id],
                    'per_page' => 15,
                    'only_show_personal_projects' => false,
                ],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedTagProjectIds = $projects
                        ->filter(fn (Project $project) => $project->tags->pluck('id')->contains($payload['tag_ids'][0]))
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedTagProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedTagProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedTagProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by multiple tags' => [
                fn (Collection $projects) => [
                    'tag_ids' => $projects->take(3)->flatMap->tags->pluck('id')->all(),
                    'per_page' => 50,
                    'only_show_personal_projects' => false,
                ],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedTagProjectIds = $projects
                        ->filter(fn (Project $project) => $project->tags->pluck('id')->intersect($payload['tag_ids'])->isNotEmpty())
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedTagProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedTagProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedTagProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by tag with no projects' => [
                fn (Collection $projects, InstitutionUser $actingUser) => [
                    'tag_ids' => [Tag::factory()->typeOrder()->create(['institution_id' => $actingUser->institution['id']])->id],
                    'only_show_personal_projects' => false,
                ],
                function (TestCase $testCase, TestResponse $response) {
                    $response->assertJsonCount(0, 'data');
                },
            ],
            'Filter by single language direction' => [
                function (Collection $projects) {
                    /** @var SubProject $someSubProject */
                    $someSubProject = $projects->flatMap(fn (Project $project) => $project->subProjects)->first();

                    return [
                        'language_directions' => ["$someSubProject->source_language_classifier_value_id:$someSubProject->destination_language_classifier_value_id"],
                        'per_page' => 15,
                        'only_show_personal_projects' => false,
                    ];
                },
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    [$sourceLanguage, $destinationLanguage] = explode(':', $payload['language_directions'][0]);

                    $specifiedLanguageDirectionProjectIds = $projects
                        ->filter(fn (Project $project) => $project->subProjects->some(
                            fn (SubProject $subProject) => $subProject->source_language_classifier_value_id === $sourceLanguage
                                && $subProject->destination_language_classifier_value_id === $destinationLanguage
                        ))
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedLanguageDirectionProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedLanguageDirectionProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedLanguageDirectionProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by multiple language directions' => [
                function (Collection $projects) {
                    $subProjects = $projects->take(3)->flatMap(fn (Project $project) => $project->subProjects);

                    return [
                        'language_directions' => $subProjects
                            ->map(fn (SubProject $subProject) => "$subProject->source_language_classifier_value_id:$subProject->destination_language_classifier_value_id")
                            ->all(),
                        'per_page' => 100,
                        'only_show_personal_projects' => false,
                    ];
                },
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedLanguageDirectionProjectIds = $projects
                        ->filter(fn (Project $project) => $project->subProjects
                            ->map(fn (SubProject $subProject) => "$subProject->source_language_classifier_value_id:$subProject->destination_language_classifier_value_id")
                            ->intersect($payload['language_directions'])
                            ->isNotEmpty()
                        )
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedLanguageDirectionProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedLanguageDirectionProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedLanguageDirectionProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by single status' => [
                fn (Collection $projects) => [
                    'per_page' => 50,
                    'only_show_personal_projects' => false,
                    'statuses' => [ProjectStatus::New->value],
                ],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedStatusProjectIds = $projects
                        ->filter(fn (Project $project) => collect($payload['statuses'])->contains($project->status->value))
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedStatusProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedStatusProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedStatusProjectIds->count()),
                        'data'
                    );
                },
            ],
            'Filter by multiple statuses' => [
                fn (Collection $projects) => [
                    'per_page' => 50,
                    'only_show_personal_projects' => false,
                    'statuses' => [ProjectStatus::New->value, ProjectStatus::Registered->value],
                ],
                function (TestCase $testCase, TestResponse $response, array $payload, Collection $projects) {
                    $specifiedStatusProjectIds = $projects
                        ->filter(fn (Project $project) => collect($payload['statuses'])->contains($project->status->value))
                        ->pluck('id');

                    collect($response->json('data'))->each(function (array $responseProject) use ($specifiedStatusProjectIds, $testCase) {
                        $testCase->assertContains($responseProject['id'], $specifiedStatusProjectIds);
                    });

                    $response->assertJsonCount(
                        min($payload['per_page'], $specifiedStatusProjectIds->count()),
                        'data'
                    );
                },
            ],
        ];
    }

    /**
     * @dataProvider provideValidPayloadCreatorsAndExtraAssertions
     *
     * @param  Closure(Collection, InstitutionUser): array  $createValidPayload
     * @param  Closure(TestCase, TestResponse, array, Collection, InstitutionUser): void  $performAssertions
     *
     * @throws Throwable
     */
    public function test_expected_subset_of_projects_returned_for_valid_payloads(Closure $createValidPayload, Closure $performAssertions): void
    {
        $payload = $createValidPayload(static::$projects, static::$privilegedActingUser);

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser(static::$privilegedActingUser))
            ->getJson(action(
                [ProjectController::class, 'index'],
                $payload
            ));

        $response->assertOk();
        $response->assertJsonIsArray('data');
        collect($response->json('data'))->each(function (mixed $item) {
            $this->assertIsArray($item);
            Assertions::assertArraysEqualIgnoringOrder(
                ['rejected_at', 'workflow_template_id', 'workflow_started', 'accepted_at', 'cancelled_at', 'client_institution_user', 'corrected_at', 'comments', 'created_at', 'deadline_at', 'event_start_at', 'ext_id', 'id', 'institution_id', 'price', 'reference_number', 'status', 'sub_projects', 'tags', 'type_classifier_value', 'updated_at', 'workflow_instance_ref'],
                array_keys($item)
            );

            $this->assertContains($item['id'], static::$projects->pluck('id'));
            $this->assertEquals($item['institution_id'], static::$privilegedActingUser->institution['id']);
        });

        $performAssertions($this, $response, $payload, static::$projects, static::$privilegedActingUser);
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
            'Requesting all institution projects without having VIEW_INSTITUTION_PROJECT_LIST privilege' => [
                fn () => InstitutionUser::factory()->createWithAllPrivilegesExcept(
                    PrivilegeKey::ViewInstitutionProjectList,
                    PrivilegeKey::ViewInstitutionProjectDetail,
                    PrivilegeKey::ViewInstitutionUnclaimedProjectDetail
                ),
                fn () => ['only_show_personal_projects' => false],
            ],
            'Requesting only personal projects without having VIEW_INSTITUTION_PROJECT_LIST or VIEW_PERSONAL_PROJECT privilege' => [
                fn () => InstitutionUser::factory()->createWithAllPrivilegesExcept(
                    PrivilegeKey::ViewPersonalProject,
                    PrivilegeKey::ViewInstitutionProjectList,
                    PrivilegeKey::ViewInstitutionProjectDetail,
                    PrivilegeKey::ViewInstitutionUnclaimedProjectDetail
                ),
                fn () => [],
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
        $unprivilegedActingUser = $createActingUser();

        $response = $this
            ->withHeaders(AuthHelpers::createHeadersForInstitutionUser($unprivilegedActingUser))
            ->getJson(action(
                [ProjectController::class, 'index'],
                $createPayload($unprivilegedActingUser)
            ));
        $response->assertForbidden();
    }

    private static function populateDatabaseWithData(InstitutionUser $actingUser, InstitutionUser $clientUser): Collection
    {
        $projects = Project::factory()
            ->state([
                'institution_id' => $actingUser->institution['id'],
                'client_institution_user_id' => $clientUser->id,
                'type_classifier_value_id' => ProjectTypeConfig::firstOrFail()->type_classifier_value_id,
                'translation_domain_classifier_value_id' => ClassifierValue::firstWhere('type', ClassifierValueType::TranslationDomain)->id,
            ])
            ->forEachSequence(
                ['ext_id' => 'HardcodedPrefix'.Str::random(8)],
                ['ext_id' => 'HardcodedPrefix'.Str::random(8)],
                ['status' => ProjectStatus::New],
                ['status' => ProjectStatus::Registered],
                ['manager_institution_user_id' => $actingUser->id],
                ['client_institution_user_id' => $actingUser->id],
                ...ProjectTypeConfig::all()
                ->map(fn (ProjectTypeConfig $projectTypeConfig) => [
                        'type_classifier_value_id' => $projectTypeConfig->type_classifier_value_id,
                    ]),
            )
            ->create();

        ClassifierValue::where('type', ClassifierValueType::Language)
            ->get()
            ->split($projects->count())
            ->zip($projects)
            ->eachSpread(function (Collection $languages, Project $project) {
                $sourceLanguage = $languages->first();
                $languages->skip(1)->each(
                    fn (ClassifierValue $destinationLanguage) => SubProject::create([
                        'project_id' => $project->id,
                        'source_language_classifier_value_id' => $sourceLanguage->id,
                        'destination_language_classifier_value_id' => $destinationLanguage->id,
                        'file_collection' => Project::INTERMEDIATE_FILES_COLLECTION_PREFIX."/$sourceLanguage->value/$destinationLanguage->value",
                        'file_collection_final' => Project::FINAL_FILES_COLLECTION."/$sourceLanguage->value/$destinationLanguage->value",
                    ])
                );
            });

        $tags = Tag::factory()
            ->typeOrder()
            ->state(['institution_id' => $actingUser->institution['id']])
            ->count($projects->count() * 2)
            ->state(function (array $attributes) {
                return [
                    'name' => $attributes['name'] . ' ' . Str::random(8),
                ];
            })
            ->create();

        $tags->chunk(2)->zip($projects)->eachSpread(function (Collection $projectTags, Project $project) {
            $project->tags()->sync($projectTags->pluck('id'));
        });

        return $projects->fresh(['subProjects', 'tags']);
    }
}
