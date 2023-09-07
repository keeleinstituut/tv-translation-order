<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\SubProject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatToolController extends Controller
{
    public function downloadXLIFFs(string $subProjectId): StreamedResponse
    {
        $subProject = SubProject::where('id', $subProjectId)->first();
        $file = $subProject->cat()->getDownloadableXLIFFsFile();
        return response()->streamDownload(
            fn() => $file->getContent(),
            $file->getName()
        );
    }

    public function downloadTranslations(string $subProjectId): StreamedResponse
    {
        $subProject = SubProject::where('id', $subProjectId)->first();
        $file = $subProject->cat()->getDownloadableTranslationsFile();
        return response()->streamDownload(
            fn() => $file->getContent(),
            $file->getName()
        );
    }
}
