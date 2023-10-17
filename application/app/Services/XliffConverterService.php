<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

class XliffConverterService
{
    public static function convertOriginalToXliff($sourceLocale, $targetLocale, File $sourceFile)
    {
        $response = static::client()
            ->attach('documentContent', $sourceFile->getContent(), $sourceFile->getName())
            ->post('/AutomationService/original2xliff', [
                'sourceLocale' => $sourceLocale,
                'targetLocale' => $targetLocale,
            ]);

        return $response->throw()->json();
    }

    public static function convertXliffToOriginal(File $xliffFile)
    {
        $response = static::client()
            ->attach('xliffContent', $xliffFile->getContent(), $xliffFile->getName())
            ->post('/AutomationService/xliff2original');

        return $response->throw()->json();
    }

    /** @throws Throwable */
    private static function client()
    {
        $xliffConverterURL = env('XLIFF_CONVERTER_URL');
        throw_if(empty($xliffConverterURL), UnexpectedValueException::class);

        return Http::baseUrl($xliffConverterURL);
    }
}
