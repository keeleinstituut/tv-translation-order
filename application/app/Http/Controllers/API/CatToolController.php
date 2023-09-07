<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\SubProject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatToolController extends Controller
{
    public function downloadOriginal(string $id, string $jobId): StreamedResponse
    {
        $subProject = SubProject::where('id', $id)->first();

        $response = $subProject->cat()->getXliffFileStreamedDownloadResponse();

        return response()->streamDownload(function () use ($response) {
            echo $response->toPsrResponse()->getBody()->getContents();
        }, '19.zip');
    }
}
