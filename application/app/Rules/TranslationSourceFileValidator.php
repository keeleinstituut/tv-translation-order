<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TranslationSourceFileValidator
{
    const ALLOWED_FILE_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'odt',
        'xls',
        'xlsx',
        'png',
        'rtf',
        'odt',
        'ods',
        'txt',
        'html',
        'xml',
        'csv',
        'zip',
        'asice',
        'cdoc',
    ];

    private static function validateFileNameSuffix(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ($value instanceof UploadedFile)) {
            $fail("The value of $attribute is not an instance of UploadedFile.");

            return;
        }
        $fileExtension = Str::lower($value->getClientOriginalExtension());
        if (collect(static::ALLOWED_FILE_EXTENSIONS)->doesntContain($fileExtension)) {
            $fail("The file extension of $attribute ($fileExtension) is not allowed.");
        }
    }

    public static function createRule(): File
    {
        return File::types(static::ALLOWED_FILE_EXTENSIONS)->rules([static::validateFileNameSuffix(...)]);
    }
}
