<?php

namespace Tests;

use Illuminate\Http\UploadedFile;

/**
 * Builds UploadedFile fakes whose bytes actually match their extension, for tests
 * exercising endpoints validated by App\Rules\ProjectFileValidator (content-sniffing).
 * Plain UploadedFile::fake()->create() writes random bytes and fails that validation.
 */
trait FakesRealFiles
{
    private const PDF_CONTENT = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\nxref\n0 0\ntrailer\n<<\n/Root 1 0 R\n>>\n%%EOF";

    private const DOCX_CONTENT = "PK\x03\x04\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00[Content_Types].xmlPK\x01\x02\x14\x00\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00[Content_Types].xmlPK\x05\x06\x00\x00\x00\x00\x01\x00\x01\x00\x1F\x00\x00\x00\x1A\x00\x00\x00\x00\x00";

    protected static function fakePdf(string $filename = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, self::PDF_CONTENT);
    }

    protected static function fakeDocx(string $filename = 'document.docx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($filename, self::DOCX_CONTENT);
    }
}
