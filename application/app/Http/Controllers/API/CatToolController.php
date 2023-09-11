<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Http\Requests\API\CatToolMergeRequest;
use App\Http\Requests\API\CatToolSetupRequest;
use App\Http\Requests\API\CatToolSplitRequest;
use App\Http\Resources\API\CatToolJobResource;
use App\Http\Resources\API\CatToolVolumeAnalysisResource;
use App\Models\SubProject;
use App\Services\CatTools\Exceptions\CatToolSetupFailedException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CatToolController extends Controller
{
    #[OA\Post(
        path: '/cat-tool/setup',
        summary: 'Setup CAT tool',
        requestBody: new OAH\RequestBody(CatToolSetupRequest::class),
        tags: ['CAT tool'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(response: \Symfony\Component\HttpFoundation\Response::HTTP_CREATED, description: 'CAT tool was setup')]
    #[OA\Response(response: \Symfony\Component\HttpFoundation\Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Wrong input data passed')]
    public function setup(CatToolSetupRequest $request): Response
    {
        try {
            $this->getSubProject($request->validated('sub_project_id'))
                ->cat()->setupJobs($request->validated('source_files_ids'));
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
        return response()->noContent(201);
    }

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
        try {
            $jobs = $subProject->cat()->split($request->validated('chunks_count'));
        } catch (InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), previous: $e);
        }

        return CatToolJobResource::collection($jobs);
    }

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
        $jobs = $subProject->cat()->merge();
        return CatToolJobResource::collection($jobs);
    }

    #[OA\Get(
        path: '/cat-tool/jobs',
        summary: 'List CAT tool jobs of specified sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('subProjectId')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolJobResource::class, description: 'CAT tool jobs')]
    #[OA\Response(response: \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT, description: 'CAT tool project creation is in progress')]
    public function jobsIndex(Request $request): AnonymousResourceCollection|Response
    {
        $subProject = $this->getSubProject($request->route('subProjectId'));

        try {
            if (!$subProject->cat()->isCreated()) {
                return response()->noContent();
            }
        } catch (CatToolSetupFailedException $e) {
            throw new HttpException(500, "CAT tool setup failed. Reason: " . $e->getMessage(), $e);
        }

        return CatToolJobResource::collection($subProject->catToolJobs);
    }

    #[OA\Get(
        path: '/cat-tool/volume-analysis',
        summary: 'List CAT tool jobs volume analysis of specified sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('subProjectId')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: CatToolVolumeAnalysisResource::class, description: 'CAT tool jobs volume analysis')]
    #[OA\Response(response: \Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT, description: 'CAT tool analysis is in progress')]
    public function volumeAnalysis(Request $request): AnonymousResourceCollection|Response
    {
        $subProject = $this->getSubProject($request->route('subProjectId'));

        if (!$subProject->cat()->isAnalyzed()) {
            return response()->noContent();
        }

        return CatToolVolumeAnalysisResource::collection($subProject);
    }

    #[OA\Get(
        path: '/cat-tool/download-xliff',
        summary: 'Download xliff files of sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('subProjectId')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: \Symfony\Component\HttpFoundation\Response::HTTP_OK,
        description: 'File archive of CAT tool project XLIFF(s)',
        content: new OA\MediaType(
            mediaType: 'application/zip',
            schema: new OA\Schema(type: 'string'),
        )
    )]
    public function downloadXLIFFs(Request $request): StreamedResponse
    {
        $file = $this->getSubProject($request->route('subProjectId'))
            ->cat()->getDownloadableXLIFFsFile();

        return response()->streamDownload(
            fn() => $file->getContent(),
            $file->getName()
        );
    }

    #[OA\Get(
        path: '/cat-tool/download-translated',
        summary: 'Download translated files of sub-project',
        tags: ['CAT tool'],
        parameters: [new OAH\UuidPath('subProjectId')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: \Symfony\Component\HttpFoundation\Response::HTTP_OK,
        description: 'File archive of CAT tool translated files',
        content: new OA\MediaType(
            mediaType: 'application/zip',
            schema: new OA\Schema(type: 'string'),
        )
    )]
    public function downloadTranslations(Request $request): StreamedResponse
    {
        $file = $this->getSubProject($request->route('subProjectId'))
            ->cat()->getDownloadableTranslationsFile();

        return response()->streamDownload(
            fn() => $file->getContent(),
            $file->getName()
        );
    }


    private function getSubProject(string $subProjectId): SubProject
    {
        return SubProject::where('id', $subProjectId)->first();
    }
}
