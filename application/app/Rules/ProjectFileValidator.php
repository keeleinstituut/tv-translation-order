<?php

namespace App\Rules;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProjectFileValidator
{
    const ALLOWED_FILE_EXTENSIONS = [
        '7z',
        'aac',
        'akt',
        'asice',
        'avi',
        'bdoc',
        'cdoc',
        'csv',
        'doc',
        'docx',
        'eml',
        'hevc',
        'htm',
        'html',
        'jpg',
        'jpeg',
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
        'tmx',
        'txt',
        'wav',
        'wma',
        'wmv',
        'xls',
        'xlsx',
        'xml',
        'xst',
        'zip',
    ];

    /**
     * Mapping of file extensions to their valid MIME types based on content.
     *
     * @var array<string, array<string>>
     */
    private const EXTENSION_TO_MIME_TYPES = [
        '7z' => ['application/x-7z-compressed'],
        'aac' => ['audio/aac', 'audio/x-aac'],
        'akt' => ['application/xml', 'text/xml'],
        'asice' => ['application/vnd.etsi.asic-e+zip'],
        'avi' => ['video/x-msvideo', 'video/avi'],
        'bdoc' => ['application/bdoc', 'application/x-bdoc'],
        'cdoc' => ['application/cdoc', 'application/x-cdoc'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'eml' => ['message/rfc822'],
        'hevc' => ['video/hevc', 'video/quicktime'],
        'htm' => ['text/html'],
        'html' => ['text/html'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a'],
        'mov' => ['video/quicktime'],
        'mp3' => ['audio/mpeg', 'audio/mp3'],
        'mp4' => ['video/mp4'],
        'msg' => ['application/vnd.ms-outlook', 'application/x-msmsg'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'rtf' => ['application/rtf', 'text/rtf'],
        'tmx' => ['application/xml', 'text/xml'],
        'txt' => ['text/plain'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
        'wma' => ['audio/x-ms-wma'],
        'wmv' => ['video/x-ms-wmv'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'xml' => ['application/xml', 'text/xml'],
        'xst' => ['application/xml', 'text/xml'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
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

    /**
     * Validate that the file content matches the expected MIME type for the file extension.
     * This implements OWASP AVS 12.2.1: Verify that files obtained from untrusted sources
     * are validated to be of expected type based on the file's content.
     */
    private static function validateFileContent(string $attribute, mixed $value, Closure $fail): void
    {
        if (! ($value instanceof UploadedFile)) {
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
