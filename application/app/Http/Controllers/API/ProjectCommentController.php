<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectCommentCreateRequest;
use App\Http\Requests\API\ProjectCommentUpdateRequest;
use App\Http\Resources\API\ProjectCommentResource;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Policies\ProjectCommentPolicy;
use App\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProjectCommentController extends Controller
{
    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/projects/{project}/comments',
        summary: 'Add a comment to a project',
        requestBody: new OAH\RequestBody(ProjectCommentCreateRequest::class),
        tags: ['Project comments', 'Calendar'],
        parameters: [new OAH\UuidPath('project')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectCommentResource::class, description: 'Created project comment', response: Response::HTTP_CREATED)]
    public function store(ProjectCommentCreateRequest $request): ProjectCommentResource
    {
        $project = Project::withGlobalScope('policy', ProjectPolicy::scope())
            ->findOrFail($request->route('project'));

        $this->authorize('create', ProjectComment::class);

        $comment = (new ProjectComment)->fill([
            'project_id' => $project->id,
            'comment' => $request->validated('comment'),
            'institution_user_id' => Auth::user()->institutionUserId,
        ]);
        $comment->saveOrFail();

        $comment->load('institutionUser');

        return ProjectCommentResource::make($comment);
    }

    /**
     * @throws Throwable
     */
    #[OA\Put(
        path: '/projects/{project}/comments/{comment}',
        summary: 'Update a project comment (only by its author with ManageProject privilege)',
        requestBody: new OAH\RequestBody(ProjectCommentUpdateRequest::class),
        tags: ['Project comments', 'Calendar'],
        parameters: [new OAH\UuidPath('project'), new OAH\UuidPath('comment')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectCommentResource::class, description: 'Updated project comment', response: Response::HTTP_OK)]
    public function update(ProjectCommentUpdateRequest $request): ProjectCommentResource
    {
        $comment = self::getBaseQuery()
            ->where('project_id', $request->route('project'))
            ->findOrFail($request->route('comment'));

        $this->authorize('update', $comment);

        $comment->fill($request->validated());
        $comment->saveOrFail();

        $comment->load('institutionUser');

        return ProjectCommentResource::make($comment);
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/projects/{project}/comments/{comment}',
        summary: 'Delete a project comment',
        tags: ['Project comments', 'Calendar'],
        parameters: [new OAH\UuidPath('project'), new OAH\UuidPath('comment')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Project comment deleted')]
    public function destroy(Request $request): Response
    {
        $comment = self::getBaseQuery()
            ->where('project_id', $request->route('project'))
            ->findOrFail($request->route('comment'));

        $this->authorize('delete', $comment);

        $comment->deleteOrFail();

        return response()->noContent();
    }

    private static function getBaseQuery(): Builder|ProjectComment
    {
        return ProjectComment::withGlobalScope('policy', ProjectCommentPolicy::scope());
    }
}
