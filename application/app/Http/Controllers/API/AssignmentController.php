<?php

namespace App\Http\Controllers\API;

use App\Enums\Feature;
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
use App\Models\Assignment;
use App\Models\AssignmentCatToolJob;
use App\Models\Candidate;
use App\Models\SubProject;
use App\Policies\AssignmentPolicy;
use App\Policies\SubProjectPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
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
            new OA\QueryParameter(name: 'feature', schema: new OA\Schema(type: 'string', enum: Feature::class)),
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
            $request->validated('feature'),
            fn (Builder $query, string $feature) => $query->where('feature', $feature)
        )->with(
            'candidates.vendor.institutionUser',
            'assignee.institutionUser',
            'volumes',
            'catToolJobs'
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
        $this->authorize('update', self::getSubProjectOrFail($request->validated('sub_project_id')));

        return DB::transaction(function () use ($request) {
            if (empty($request->validated('linking'))) {
                $affectedAssignments = collect();
                // Delete all relations between assignments and CAT tool jobs in case if empty linking passed.
                AssignmentCatToolJob::query()->whereHas('assignment', function (Builder $assignmentQuery) use ($request) {
                    $assignmentQuery->where('sub_project_id', $request->validated('sub_project_id'))
                        ->where('feature', $request->validated('feature'));
                })->with([
                    'assignment.candidates.vendor.institutionUser',
                    'assignment.assignee.institutionUser',
                    'assignment.volumes',
                ])->each(function (AssignmentCatToolJob $assignmentCatToolJob) use ($affectedAssignments) {
                    $assignment = $assignmentCatToolJob->assignment;
                    $affectedAssignments->add($assignment);
                    $assignmentCatToolJob->delete();
                    $assignment->load('catToolJobs');
                });

                return AssignmentResource::collection($affectedAssignments);
            }

            $affectedAssignments = $request->getAssignments();
            collect($request->validated('linking'))->mapToGroups(function (array $item) {
                return [$item['assignment_id'] => $item['cat_tool_job_id']];
            })->each(function ($catToolJobsIds, string $assignmentId) use ($affectedAssignments) {
                $assignment = $affectedAssignments->get($assignmentId);
                $assignment->catToolJobs()->sync($catToolJobsIds);
                $assignment->load('catToolJobs');
            });

            return AssignmentResource::collection($affectedAssignments->values());
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
            $assignment = new Assignment();
            $assignment->fill($request->validated());
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
            $assignment = self::getAssignmentOrFail($request->route('id'));
            $assignment->fill($request->validated());
            $this->authorize('update', $assignment);
            $assignment->saveOrFail();
            $assignment->refresh();

            return AssignmentResource::make($assignment);
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
            $assignment = self::getAssignmentOrFail($request->route('id'));
            $assignment->fill($request->validated());
            $this->authorize('updateAssigneeComment', $assignment);
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
            $assignment = self::getAssignmentOrFail($assignmentId);
            $this->authorize('update', $assignment);

            $candidates = collect($params->get('data'))->map(Candidate::make(...));

            $assignment->candidates()->saveMany($candidates);

            $assignment->load('candidates.vendor.institutionUser');

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
            $assignment = self::getAssignmentOrFail($assignmentId);
            $this->authorize('update', $assignment);

            $vendorIds = collect($params->get('data'))->pluck('vendor_id');

            $assignment->candidates()
                ->whereIn('vendor_id', $vendorIds)
                ->each(fn (Candidate $candidate) => $candidate->delete());

            $assignment->load('candidates.vendor.institutionUser');

            return AssignmentResource::make($assignment);
        });
    }

    /**
     * @throws Throwable
     * TODO: Implement logic of removing assignment in case if it's an additional assignment, not the main one.
     * TODO: Implement interaction with Camunda
     */
    //    #[OA\Delete(
    //        path: '/assignment/{id}',
    //        summary: 'Delete assignment',
    //        tags: ['Assignment management'],
    //        parameters: [new OAH\UuidPath('id')],
    //        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    //    )]
    //    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Assignment deleted')]
    public function destroy(string $id): \Illuminate\Http\Response
    {
        DB::transaction(function () use ($id) {
            $assignment = self::getAssignmentOrFail($id);
            $this->authorize('delete', $assignment);
            $assignment->delete();
        });

        return response()->noContent();
    }

    private static function getAssignmentOrFail(string $id): Assignment
    {
        /** @var Assignment $assignment */
        $assignment = self::getBaseQuery()->with([
            'assignee',
            'candidates',
            'volumes',
            'catToolJobs',
        ])->findOrFail($id);

        return $assignment;
    }

    private static function getSubProjectOrFail(string $id): SubProject
    {
        /** @var SubProject $subProject */
        $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->findOrFail($id);

        return $subProject;
    }

    private static function getBaseQuery(): Assignment|Builder
    {
        return Assignment::getModel()->withGlobalScope('policy', AssignmentPolicy::scope());
    }
}
