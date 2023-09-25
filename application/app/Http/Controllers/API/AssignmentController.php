<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\AssignmentCatToolJobBulkLinkRequest;
use App\Http\Resources\API\AssignmentResource;
use App\Models\Assignment;
use App\Models\AssignmentCatToolJob;
use App\Policies\AssignmentPolicy;
use DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;

class AssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', Assignment::class);

        $data = static::getBaseQuery()->where(
            'sub_project_id',
            $request->route('subProjectId')
        )->with(
            'candidates.vendor.institutionUser',
            'assignee.vendor.institutionUser',
            'volumes',
            'catToolJobs'
        );

        return AssignmentResource::collection($data);
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/link-cat-tool-jobs',
        summary: 'Create/delete relations between CAT tool jobs and assignments (XLIFF assignment tab)',
        requestBody: new OAH\RequestBody(AssignmentCatToolJobBulkLinkRequest::class),
        tags: ['Assignment management'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: AssignmentResource::class, description: 'List of affected assignments', response: Response::HTTP_OK)]
    public function linkToCatToolJobs(AssignmentCatToolJobBulkLinkRequest $request)
    {
        return DB::transaction(function () use ($request) {
            if (empty($request->validated('linking'))) {
                $affectedAssignments = collect();
                // Delete all relations between assignments and CAT tool jobs in case if empty linking passed.
                AssignmentCatToolJob::query()->whereHas('assignment', function (Builder $assignmentQuery) use ($request) {
                    $assignmentQuery->where('sub_project_id', $request->validated('sub_project_id'))
                        ->where('feature', $request->validated('feature'));
                })->each(function (AssignmentCatToolJob $assignmentCatToolJob) use ($affectedAssignments) {
                    $affectedAssignments->add($assignmentCatToolJob->assignment);
                    $assignmentCatToolJob->delete();
                });

                return AssignmentResource::collection($affectedAssignments);
            }

            $affectedAssignments = $request->getAssignments();
            collect($request->validated('linking'))->mapToGroups(function (array $item) {
                return [$item['assignment_id'] => $item['cat_tool_job_id']];
            })->each(function ($catToolJobsIds, string $assignmentId) use ($affectedAssignments) {
                $assignment = $affectedAssignments->get($assignmentId);
                $assignment->catToolJobs()->sync($catToolJobsIds);
            });

            return AssignmentResource::collection($affectedAssignments->values());
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // create assignment under subproject?
    }

    /**
     * Display the specified resource.
     *
     * @throws AuthorizationException
     */
    public function show(string $id): AssignmentResource
    {
        $assignment = static::getBaseQuery()->findOrFail($id);
        $this->authorize('view', $assignment);

        return AssignmentResource::make($assignment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private static function getBaseQuery(): Assignment|Builder
    {
        return Assignment::getModel()->withGlobalScope('policy', AssignmentPolicy::scope());
    }
}
