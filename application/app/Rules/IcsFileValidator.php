<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class IcsFileValidator
{
    const array ALLOWED_FILE_EXTENSIONS = ['ics'];

    /**
     * @var array<string, array<string>>
     */
    private const array EXTENSION_TO_MIME_TYPES = [
        'ics' => ['text/calendar', 'text/plain'],
    ];

    private static function validateFileNameSuffix(string $attribute, mixed $value, Closure $fail): void
    {
        if (!($value instanceof UploadedFile)) {
            $fail("The value of $attribute is not an instance of UploadedFile.");

            return;
        }
        $fileExtension = Str::lower($value->getClientOriginalExtension());
        if (collect(static::ALLOWED_FILE_EXTENSIONS)->doesntContain($fileExtension)) {
            $fail("Faililaiend .$fileExtension ei ole lubatud.");
        }
    }

    private static function validateFileContent(string $attribute, mixed $value, Closure $fail): void
    {
        if (!($value instanceof UploadedFile)) {
            return;
        }

        $fileExtension = Str::lower($value->getClientOriginalExtension());
        if (collect(static::ALLOWED_FILE_EXTENSIONS)->doesntContain($fileExtension)) {
            return;
        }

        $expectedMimeTypes = static::EXTENSION_TO_MIME_TYPES[$fileExtension] ?? [];
        if (empty($expectedMimeTypes)) {
            $fail("Faililaiend .$fileExtension ei ole lubatud.");

            return;
        }

        $filePath = $value->getRealPath();
        if ($filePath === false) {
            $fail('Faililaiend sisu kontrollimine ebaõnnestus.');

            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            $fail('Faililaiend sisu kontrollimine ebaõnnestus.');

            return;
        }

        $detectedMimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if ($detectedMimeType === false) {
            $fail('Faililaiend sisu kontrollimine ebaõnnestus.');

            return;
        }

        $detectedMimeType = strtolower(explode(';', $detectedMimeType)[0]);
        $expectedMimeTypes = array_map('strtolower', $expectedMimeTypes);

        if (collect($expectedMimeTypes)->doesntContain($detectedMimeType)) {
            $fail("Faililaiend sisu ei vasta faililaiendi .$fileExtension oodatud tüübile.");
        }
    }

    public static function createRule(): File
    {
        return File::types(static::ALLOWED_FILE_EXTENSIONS)
            ->rules([
                static::validateFileNameSuffix(...),
                static::validateFileContent(...),
            ]);
    }
}
