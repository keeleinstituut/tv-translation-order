<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TranslationSourceFileValidator
{
    const ALLOWED_FILE_EXTENSIONS = [
        '7z',
        'aac',
        'asice',
        'avi',
        'bdoc',
        'cdoc',
        'csv',
        'doc',
        'docx',
        'eml',
        'hevc',
        'html',
        'jpg',
        'm4a',
        'mov',
        'mp3',
        'mp4',
        'msg',
        'ods',
        'odt',
        'pdf',
        'png',
        'ppt',
        'pptx',
        'rtf',
        'txt',
        'wav',
        'wma',
        'wmv',
        'xls',
        'xlsx',
        'xml',
        'zip',
    ];

    private static function validateFileNameSuffix(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ($value instanceof UploadedFile)) {
            $fail("The value of $attribute is not an instance of UploadedFile.");

            return;
        }
        $fileExtension = Str::lower($value->getClientOriginalExtension());
        if (collect(static::ALLOWED_FILE_EXTENSIONS)->doesntContain($fileExtension)) {
            // $fail("The file extension of $attribute ($fileExtension) is not allowed.");
            // TODO: temporary quick fix
            $fail("Faililaiend .$fileExtension ei ole lubatud.");
        }
    }

    public static function createRule(): File
    {
        return File::types(static::ALLOWED_FILE_EXTENSIONS)->rules([static::validateFileNameSuffix(...)]);
    }
}
