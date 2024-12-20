<?php

namespace App\Http\Controllers\API;

use App\Enums\SubProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CatToolMergeRequest;
use App\Http\Requests\API\CatToolSetupRequest;
use App\Http\Requests\API\CatToolSplitRequest;
use App\Http\Requests\API\CatToolToggleMTEngineRequest;
use App\Http\Resources\API\CatToolJobResource;
use App\Http\Resources\API\CatToolMTEngineStatusResource;
use App\Http\Resources\API\SubProjectCatToolJobsResource;
use App\Http\Resources\API\SubProjectVolumeAnalysisResource;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use App\Services\CatTools\CatToolAnalysisReport;
use App\Services\CatTools\Enums\CatToolAnalyzingStatus;
use AuditLogClient\Services\AuditLogMessageBuilder;
use BadMethodCallException;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class CatToolController extends Controller
{
    /**
     * @throws AuthorizationException|ValidationException|Throwable
     */
    #[OA\Post(
        path: '/cat-tool/setup',
        summary: 'Setup CAT tool',
        requestBody: new OAH\RequestBody(CatToolSetupRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\InvalidTmKeys]
    )]
    #[OA\Response(response: Response::HTTP_CREATED, description: 'CAT tool was setup')]
    #[OA\Response(
        response: Response::HTTP_UNPROCESSABLE_ENTITY,
        description: 'Sub project TM keys validation failed',
        content: new OA\JsonContent(required: ['message'],
            properties: [
                new OA\Property(property: 'message', type: 'string'),
            ],
            type: 'object',
            example: ['message' => 'Project should have at least one TM key || Project should have not more than 10 TM keys || Not more than two translation memories can be writable || At least one TM should be writable']
        )
    )]
    public function setup(CatToolSetupRequest $request): \Illuminate\Http\Response
    {
        $subProject = $this->getSubProject($request->validated('sub_project_id'));
        $this->authorize('manageCatTool', $subProject);
        try {
            $this->auditLogPublisher->publishModifyObjectAfterAction(
                $subProject,
                fn () => $subProject->cat()->setupJobs($request->validated('source_files_ids'))
            );
        } catch (InvalidArgumentException|BadMethodCallException|RuntimeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), previous: $e);
        }

        return response()->noContent(201);
    }

    /**
     * @throws AuthorizationException|ValidationException|Throwable
     */
    #[OA\Post(
        path: '/cat-tool/split',
        summary: 'Split CAT tool jobs',
        requestBody: new OAH\RequestBody(CatToolSplitRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolJobResource::class, description: 'The list of CAT tool jobs (XLIFF files in the requirements)')]
    public function split(CatToolSplitRequest $request): AnonymousResourceCollection
    {
        $subProject = $this->getSubProject($request->validated('sub_project_id'));

        if (!in_array($subProject->status, [SubProjectStatus::New, SubProjectStatus::Registered])) {
            abort(Response::HTTP_BAD_REQUEST, 'Not possible to split XLIFF file(s) for subproject that is in progress');
        }

        $this->authorize('manageCatTool', $subProject);

        try {
            $jobs = $this->auditLogPublisher->publishModifyObjectAfterAction(
                $subProject,
                fn () => $subProject->cat()->split($request->validated('chunks_count'))
            );
        } catch (InvalidArgumentException|DomainException|RuntimeException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), previous: $e);
        }

        return CatToolJobResource::collection($jobs);
    }

    /**
     * @throws AuthorizationException|ValidationException|Throwable
     */
    #[OA\Post(
        path: '/cat-tool/merge',
        summary: 'Merge CAT tool jobs into one.',
        requestBody: new OAH\RequestBody(CatToolMergeRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolJobResource::class, description: 'The list of CAT tool jobs (XLIFF files in the requirements)')]
    public function merge(CatToolMergeRequest $request): AnonymousResourceCollection
    {
        $subProject = $this->getSubProject($request->validated('sub_project_id'));
        $this->authorize('manageCatTool', $subProject);

        if (!in_array($subProject->status, [SubProjectStatus::New, SubProjectStatus::Registered])) {
            abort(Response::HTTP_BAD_REQUEST, 'Not possible to merge XLIFF files for subproject that is in progress');
        }

        $jobs = $this->auditLogPublisher->publishModifyObjectAfterAction(
            $subProject,
            fn () => $subProject->cat()->merge()
        );

        return CatToolJobResource::collection($jobs);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/cat-tool/jobs/{sub_project_id}',
        summary: 'List CAT tool jobs of specified sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: SubProjectCatToolJobsResource::class, description: 'CAT tool jobs')]
    public function jobsIndex(Request $request): SubProjectCatToolJobsResource
    {
        $subProject = $this->getSubProject($request->route('sub_project_id'));
        $this->authorize('manageCatTool', $subProject);

        return SubProjectCatToolJobsResource::make($subProject);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/cat-tool/volume-analysis/{sub_project_id}',
        summary: 'List CAT tool jobs volume analysis of specified sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: SubProjectVolumeAnalysisResource::class, description: 'CAT tool jobs volume analysis')]
    public function volumeAnalysis(Request $request): SubProjectVolumeAnalysisResource
    {
        $subProject = $this->getSubProject($request->route('sub_project_id'));
        $this->authorize('manageCatTool', $subProject);

        return new SubProjectVolumeAnalysisResource($subProject);
    }

    /**
     * @throws AuthorizationException|ValidationException|Throwable
     */
    #[OA\Put(
        path: '/cat-tool/toggle-mt-engine/{sub_project_id}',
        summary: 'Enable/Disable MT engine for CAT tool',
        requestBody: new OAH\RequestBody(CatToolToggleMTEngineRequest::class),
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: CatToolMTEngineStatusResource::class, description: 'MT engine enabled flag', response: Response::HTTP_OK)]
    public function toggleMTEngine(CatToolToggleMTEngineRequest $request): CatToolMTEngineStatusResource
    {
        $subProject = $this->getSubProject($request->route('sub_project_id'));
        $this->authorize('manageCatTool', $subProject);

        try {
            $this->auditLogPublisher->publishModifyObjectAfterAction(
                $subProject,
                fn () => $subProject->cat()->toggleMtEngine($request->validated('mt_enabled'))
            );
        } catch (RequestException $e) {
            throw new HttpException(500, 'Disabling/Enabling MT failed. Reason: '.$e->getMessage(), $e);
        }

        return CatToolMTEngineStatusResource::make($subProject);
    }

    /**
     * @throws AuthorizationException|ValidationException|Throwable
     */
    #[OA\Get(
        path: '/cat-tool/download-xliff/{sub_project_id}',
        summary: 'Download xliff files of sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'File archive of CAT tool project XLIFF(s)',
        content: new OA\MediaType(
            mediaType: 'application/zip',
            schema: new OA\Schema(type: 'string'),
        )
    )]
    public function downloadXLIFFs(Request $request): StreamedResponse
    {
        $subProject = $this->getSubProject($request->route('sub_project_id'));
        $this->authorize('downloadXliff', $subProject);

        $file = $subProject->cat()->getDownloadableXLIFFsFile();

        $this->auditLogPublisher->publish(
            AuditLogMessageBuilder::makeUsingJWT()->toDownloadSubprojectXliffsEvent(
                $subProject->id,
                $subProject->ext_id,
            )
        );

        return response()->streamDownload(function () use ($file) {
            echo $file->getContent();
        }, $file->getName());
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/cat-tool/download-translated/{sub_project_id}',
        summary: 'Download translated files of sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'File archive of CAT tool translated files',
        content: new OA\MediaType(
            mediaType: 'application/zip',
            schema: new OA\Schema(type: 'string'),
        )
    )]
    public function downloadTranslations(Request $request): StreamedResponse
    {
        $subProject = $this->getSubProject($request->route('sub_project_id'));
        $this->authorize('downloadTranslations', $subProject);

        $file = $subProject->cat()->getDownloadableTranslationsFile();

        $this->auditLogPublisher->publish(
            AuditLogMessageBuilder::makeUsingJWT()->toDownloadSubprojectTranslationsEvent(
                $subProject->id,
                $subProject->ext_id,
            )
        );

        return response()->streamDownload(function () use ($file) {
            echo $file->getContent();
        }, $file->getName());
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/cat-tool/download-volume-analysis/{sub_project_id}',
        summary: 'Download .txt file with CAT tool volume analysis for the sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('sub_project_id')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'File archive of CAT tool translated files',
        content: new OA\MediaType(
            mediaType: 'text/plain',
            schema: new OA\Schema(type: 'string'),
        )
    )]
    #[OA\Response(response: Response::HTTP_ACCEPTED, description: 'CAT tool volume analysis is in progress, retry request in a few seconds')]
    public function downloadVolumeAnalysisReport(Request $request): StreamedResponse|Response
    {
        $subProject = $this->getSubProject($request->route('sub_project_id'));
        $this->authorize('manageCatTool', $subProject);

        if ($subProject->cat()->getAnalyzingStatus() !== CatToolAnalyzingStatus::Done) {
            return response()->noContent(Response::HTTP_ACCEPTED);
        }

        $file = (new CatToolAnalysisReport($subProject))->getReport();

        return response()->streamDownload(function () use ($file) {
            echo $file->getContent();
        }, $file->getName());
    }

    private function getSubProject(string $subProjectId): SubProject
    {
        return SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->with('catToolJobs')
            ->findOrFail($subProjectId);
    }
}
