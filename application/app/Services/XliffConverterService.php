<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Http;


class XliffConverterService
{
    private static $base = "http://host.docker.internal:8732";

    public static function convertOriginalToXliff($sourceLocale, $targetLocale, File $sourceFile) {
        $response = static::client()
            ->attach('documentContent', $sourceFile->getContent(), $sourceFile->getName())
            ->post("/AutomationService/original2xliff", [
                    'sourceLocale' => $sourceLocale,
                    'targetLocale' => $targetLocale,
            ]);
        return $response->throw()->json();
    }

    public static function convertXliffToOriginal(File $xliffFile) {
        $response = static::client()
            ->attach('xliffContent', $xliffFile->getContent(), $xliffFile->getName())
            ->post("/AutomationService/xliff2original");
        return $response->throw()->json();
    }

    private static function client() {
        return Http::baseUrl(static::$base);
    }
}