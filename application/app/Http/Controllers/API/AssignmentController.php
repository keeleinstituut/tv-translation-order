<?php

namespace App\Http\Controllers\API;

use App\Enums\AssignmentStatus;
use App\Enums\JobKey;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\AssignmentAddCandidatesRequest;
use App\Http\Requests\API\AssignmentCatToolJobBulkLinkRequest;
use App\Http\Requests\API\AssignmentCreateRequest;
use App\Http\Requests\API\AssignmentDeleteCandidateRequest;
use App\Http\Requests\API\AssignmentListRequest;
use App\Http\Requests\API\AssignmentUpdateAssigneeCommentRequest;
use App\Http\Requests\API\AssignmentUpdateRequest;
use App\Http\Resources\API\AssignmentResource;
use App\Jobs\NotifyAssignmentCandidates;
use App\Jobs\Workflows\AddCandidatesToWorkflow;
use App\Jobs\Workflows\DeleteCandidatesFromWorkflow;
use App\Jobs\Workflows\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\AssignmentCatToolJob;
use App\Models\Candidate;
use App\Models\Media;
use App\Models\SubProject;
use App\Policies\AssignmentPolicy;
use App\Policies\SubProjectPolicy;
use App\Services\Workflows\Tasks\WorkflowTasksDataProvider;
use App\Services\Workflows\WorkflowService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AssignmentController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/assignments/{sub_project_id}',
        description: 'Endpoint that returns list of assignments of the sub-project with filtering by `feature`',
        summary: 'list of assignments of the sub-project with filtering by `feature`',
        tags: ['Assignment management'],
        parameters: [
            new OAH\UuidPath('sub_project_id'),
            new OA\QueryParameter(name: 'job_key', schema: new OA\Schema(type: 'string', enum: JobKey::class)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: AssignmentResource::class, description: 'Filtered assignments of current sub-project')]
    public function index(AssignmentListRequest $request): ResourceCollection
    {
        $this->authorize('viewAny', [
            Assignment::class,
            self::getSubProjectOrFail($request->route('sub_project_id')),
        ]);

        $data = static::getBaseQuery()->where(
            'sub_project_id',
            $request->route('sub_project_id')
        )->when(
            $request->validated('job_key'),
            fn(Builder $query, string $feature) => $query->whereRelation(
                'jobDefinition',
                'job_key',
                $request->validated('job_key')
            )
        )->with(
            'candidates.vendor.institutionUser',
            'assignee.institutionUser',
            'volumes',
            'catToolJobs',
            'jobDefinition'
        )->get();

        return AssignmentResource::collection($data);
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/assignments/link-cat-tool-jobs',
        summary: 'Create/delete relations between CAT tool jobs and assignments (XLIFF assignment tab). Please note that not passed relations will be removed.',
        requestBody: new OAH\RequestBody(AssignmentCatToolJobBulkLinkRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: AssignmentResource::class, description: 'List of affected assignments', response: Response::HTTP_OK)]
    public function linkToCatToolJobs(AssignmentCatToolJobBulkLinkRequest $request)
    {
        $this->authorize('update', $request->getSubProject());

        return DB::transaction(function () use ($request) {
            $affectedAssignmentIds = collect();
            $assignmentsIndexedById = $request->getAssignments();
            if (filled($request->validated('linking'))) {
                collect($request->validated('linking'))->mapToGroups(function (array $item) {
                    return [$item['assignment_id'] => $item['cat_tool_job_id']];
                })->each(function ($catToolJobsIds, string $assignmentId) use ($assignmentsIndexedById, $affectedAssignmentIds) {
                    $assignment = $assignmentsIndexedById->get($assignmentId);
                    $assignment->catToolJobs()->sync($catToolJobsIds);
                    $affectedAssignmentIds->add($assignment->id);
                });
            }

            AssignmentCatToolJob::query()->whereHas('assignment', function (Builder $assignmentQuery) use ($request) {
                $assignmentQuery->where('sub_project_id', $request->validated('sub_project_id'))
                    ->where('job_definition_id', $request->getJobDefinition()->id)
                    ->when(
                        filled($request->getAssignments()->keys()),
                        fn(Builder $assignmentSubQuery) => $assignmentSubQuery->whereNotIn(
                            'id',
                            $request->getAssignments()->keys()
                        )
                    );
            })->each(function (AssignmentCatToolJob $assignmentCatToolJob) use ($affectedAssignmentIds) {
                $affectedAssignmentIds->add($assignmentCatToolJob->assignment_id);
                $assignmentCatToolJob->delete();
            });

            return AssignmentResource::collection(self::getAssignmentsByIds($affectedAssignmentIds));
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/assignments',
        summary: 'Create a new assignment',
        requestBody: new OAH\RequestBody(AssignmentCreateRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: AssignmentResource::class, description: 'Created assignment', response: Response::HTTP_CREATED)]
    public function store(AssignmentCreateRequest $request): AssignmentResource
    {
        return DB::transaction(function () use ($request) {
            if ($request->getSubProject()->workflow()->isStarted()) {
                abort(Response::HTTP_BAD_REQUEST, 'Adding of assignments not allowed for sub-projects with already started workflow');
            }

            $assignment = new Assignment();

            $attributes = $request->validated();
            $attributes['job_definition_id'] = $request->getJobDefinition()->id;
            unset($attributes['job_key']);

            $assignment->fill($attributes);
            $this->authorize('create', $assignment);
            $assignment->saveOrFail();
            $assignment->refresh();

            return AssignmentResource::make($assignment);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/assignments/{id}',
        summary: 'Update the assignment',
        requestBody: new OAH\RequestBody(AssignmentUpdateRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: AssignmentResource::class, description: 'Updated assignment', response: Response::HTTP_OK)]
    public function update(AssignmentUpdateRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $assignment = self::getBaseQuery()->findOrFail($request->route('id'));
            $this->authorize('update', $assignment);

            $assignment->fill($request->validated());
            $assignment->saveOrFail();

            return AssignmentResource::make($assignment->refresh());
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/assignments/{id}/assignee-comment',
        summary: 'Update assignee comment for an assignment',
        requestBody: new OAH\RequestBody(AssignmentUpdateAssigneeCommentRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: AssignmentResource::class, description: 'Updated assignment', response: Response::HTTP_OK)]
    public function updateAssigneeComment(AssignmentUpdateAssigneeCommentRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $assignment = self::getBaseQuery()->findOrFail($request->route('id'));
            $this->authorize('updateAssigneeComment', $assignment);

            $assignment->fill($request->validated());
            $assignment->saveOrFail();

            return AssignmentResource::make($assignment);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/assignments/{id}/candidates/bulk',
        summary: 'Add candidates to assignment',
        requestBody: new OAH\RequestBody(AssignmentAddCandidatesRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: AssignmentResource::class, description: 'Updated assignment', response: Response::HTTP_OK)]
    public function addCandidates(AssignmentAddCandidatesRequest $request)
    {
        $assignmentId = $request->route('id');
        $params = collect($request->validated());
        return DB::transaction(function () use ($assignmentId, $params) {
            /** @var Assignment $assignment */
            $assignment = self::getBaseQuery()->findOrFail($assignmentId);
            $this->authorize('update', $assignment);

            if ($assignment->status === AssignmentStatus::Done) {
                abort(Response::HTTP_BAD_REQUEST, 'Not possible to add candidates for the assignment that is done');
            }

            if ($assignment->jobDefinition->job_key === JobKey::JOB_OVERVIEW) {
                abort(Response::HTTP_BAD_REQUEST, 'Review task can be done only by the assigned project manager');
            }

            /** @var Collection $newCandidates */
            $newCandidates = collect($params->get('data'))
                ->unique(fn($data) => $data['vendor_id'])
                ->map(Candidate::make(...));

            $assignment->candidates()->saveMany($newCandidates);

            $assignment->load('candidates.vendor.institutionUser');

            $newCandidatesVendorIds = $newCandidates->pluck('vendor_id');
            $newCandidatesInstitutionUserIds = $assignment->candidates->filter(function (Candidate $candidate) use ($newCandidatesVendorIds) {
                return $newCandidatesVendorIds->contains($candidate->vendor_id);
            })->map(function (Candidate $candidate) {
                return $candidate->vendor?->institution_user_id;
            })->filter()->values();

            if ($newCandidatesInstitutionUserIds->isNotEmpty()) {
                AddCandidatesToWorkflow::dispatch($assignment, $newCandidatesInstitutionUserIds->toArray());
            }

            return AssignmentResource::make($assignment);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/assignments/{id}/candidates/bulk',
        summary: 'Delete assignment candidate',
        requestBody: new OAH\RequestBody(AssignmentDeleteCandidateRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: AssignmentResource::class, description: 'Updated assignment', response: Response::HTTP_OK)]
    public function deleteCandidate(AssignmentDeleteCandidateRequest $request)
    {
        $assignmentId = $request->route('id');
        $params = collect($request->validated());

        return DB::transaction(function () use ($assignmentId, $params) {
            /** @var Assignment $assignment */
            $assignment = self::getBaseQuery()->findOrFail($assignmentId);
            $this->authorize('update', $assignment);

            $vendorIds = collect($params->get('data'))->pluck('vendor_id');
            $deletedCandidatesInstitutionUserIds = collect();
            $assignment->candidates()
                ->whereIn('vendor_id', $vendorIds)
                ->each(function (Candidate $candidate) use ($deletedCandidatesInstitutionUserIds) {
                    if (filled($candidate->vendor?->institution_user_id)) {
                        $deletedCandidatesInstitutionUserIds->push($candidate->vendor?->institution_user_id);
                    }

                    $candidate->delete();
                });

            if ($deletedCandidatesInstitutionUserIds->isNotEmpty()) {
                DeleteCandidatesFromWorkflow::dispatch($assignment, $deletedCandidatesInstitutionUserIds->toArray());
            }

            $assignment->load('candidates.vendor.institutionUser');
            return AssignmentResource::make($assignment);
        });
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/assignment/{id}',
        summary: 'Delete assignment',
        tags: ['Assignment management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Assignment deleted')]
    public function destroy(string $id): \Illuminate\Http\Response
    {
        DB::transaction(function () use ($id) {
            /** @var Assignment $assignment */
            $assignment = self::getBaseQuery()->findOrFail($id);
            $this->authorize('delete', $assignment);

            if ($assignment->subProject->workflow()->isStarted()) {
                abort(Response::HTTP_BAD_REQUEST, 'Deleting of assignments not allowed for sub-projects with already started workflow');
            }

            $subProjectHasAssignmentWithTheSameJobDefinition = $assignment
                ->getSameJobDefinitionAssignmentsQuery()->exists();

            if (!$subProjectHasAssignmentWithTheSameJobDefinition) {
                abort(Response::HTTP_BAD_REQUEST, 'Not possible to delete the last assignment');
            }

            if ($assignment->status === AssignmentStatus::InProgress) {
                abort(Response::HTTP_BAD_REQUEST, 'Not possible to delete the assignment as the sub-project workflow is in progress.');
            } elseif ($assignment->status === AssignmentStatus::Done) {
                abort(Response::HTTP_BAD_REQUEST, 'Not possible to delete the assignment as the task related to it is done.');
            }

            $assignment->delete();
        });

        return response()->noContent();
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/assignments/{id}/mark-as-completed',
        description: 'Note: available only for assignments with status `IN_PROGRESS`. For the assignments with job key  `job_overview` the request body is required.',
        summary: 'Mark assignment as completed',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                required: [],
                properties: [
                    new OA\Property(property: 'accepted', type: 'boolean'),
                    new OA\Property(property: 'final_file_id', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        tags: ['Assignment management'],
        parameters: [new OAH\UuidPath('id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: AssignmentResource::class, description: 'Updated assignment', response: Response::HTTP_OK)]
    public function markAsCompleted(Request $request): AssignmentResource
    {
        return DB::transaction(function () use ($request) {
            /** @var Assignment $assignment */
            $assignment = self::getBaseQuery()->findOrFail($request->route('id'));
            $this->authorize('markAsCompleted', $assignment);

            $taskData = $this->retrieveTaskBasedOnAssignmentOrFail($assignment);

            if (empty($taskId = data_get($taskData, 'task.id'))) {
                abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'The task data has incorrect format');
            }

            if ($assignment->jobDefinition->job_key === JobKey::JOB_OVERVIEW) {
                if (data_get($taskData, 'variables.task_type', TaskType::Default->value) !== TaskType::Review->value) {
                    abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'The task has the wrong type that not match with the assignment');
                }

                $validated = $request->validate([
                    'accepted' => ['required', 'boolean'],
                    'final_file_id' => ['required_if:accepted,1', 'array'],
                    'final_file_id.*' => [
                        'required',
                        'integer',
                        Rule::exists(Media::class, 'id'),
                    ],
                ]);

                WorkflowService::completeReviewTask($taskId, $validated['accepted']);
                try {
                    $validated['accepted'] && $assignment->subProject
                        ->moveFinalFilesToProjectFinalFiles(
                            $validated['final_file_id']
                        );
                } catch (InvalidArgumentException $e) {
                    abort(Response::HTTP_BAD_REQUEST, $e->getMessage());
                }
            } else {
                if (data_get($taskData, 'variables.task_type', TaskType::Default->value) !== TaskType::Default->value) {
                    abort(Response::HTTP_INTERNAL_SERVER_ERROR, 'The task has the wrong type that not match with the assignment');
                }

                WorkflowService::completeTask($taskId);
            }

            DB::transaction(function () use ($assignment) {
                $assignment->status = AssignmentStatus::Done;
                $assignment->saveOrFail();
            });

            TrackSubProjectStatus::dispatch($assignment->subProject);

            return AssignmentResource::make($assignment);
        });
    }

    private function retrieveTaskBasedOnAssignmentOrFail(Assignment $assignment): array
    {
        $searchResults = (new WorkflowTasksDataProvider())->search([
            'processVariables' => [
                [
                    'name' => 'assignment_id',
                    'value' => $assignment->id,
                    'operator' => 'eq',
                ]
            ]
        ]);

        if ($searchResults->getCount() === 0) {
            abort(Response::HTTP_NOT_FOUND, 'Assignment has no task to complete');
        }

        return $searchResults->getTasks()->get(0);
    }

    private static function getSubProjectOrFail(string $id): SubProject
    {
        /** @var SubProject $subProject */
        $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->findOrFail($id);

        return $subProject;
    }

    private static function getBaseQuery(): Builder
    {
        return Assignment::getModel()->withGlobalScope('policy', AssignmentPolicy::scope())
            ->with([
                'candidates.vendor.institutionUser',
                'assignee.institutionUser',
                'volumes.institutionDiscount',
                'catToolJobs',
                'jobDefinition'
            ]);
    }

    /**
     * @param Collection $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, Assignment>
     */
    private static function getAssignmentsByIds(Collection $ids): \Illuminate\Database\Eloquent\Collection
    {
        return self::getBaseQuery()->whereIn('id', $ids)->get();
    }
}
