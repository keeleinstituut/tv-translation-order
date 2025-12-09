<?php

namespace Tests\Unit\Rules;

use App\Rules\ProjectFileValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProjectFileValidatorTest extends TestCase
{
    /**
     * Create a temporary file with the given content and return an UploadedFile instance.
     */
    private function createUploadedFile(string $filename, string $content): UploadedFile
    {
        $tempPath = sys_get_temp_dir().'/'.uniqid('test_', true).'_'.$filename;
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            $tempPath,
            $filename,
            mime_content_type($tempPath) ?: 'application/octet-stream',
            null,
            true
        );
    }

    public function test_valid_pdf_file_with_matching_content_passes_validation(): void
    {
        // Create a valid PDF file (PDF magic bytes: %PDF)
        $pdfContent = "%PDF-1.4\n1 0 obj\n<<\n/Type /Catalog\n>>\nendobj\nxref\n0 0\ntrailer\n<<\n/Root 1 0 R\n>>\n%%EOF";
        $file = $this->createUploadedFile('document.pdf', $pdfContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_valid_docx_file_with_matching_content_passes_validation(): void
    {
        // Create a valid DOCX file (ZIP magic bytes: PK\x03\x04)
        // DOCX files are ZIP archives, so they start with ZIP magic bytes
        // Create a minimal valid ZIP file structure
        $docxContent = "PK\x03\x04\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00[Content_Types].xmlPK\x01\x02\x14\x00\x14\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00[Content_Types].xmlPK\x05\x06\x00\x00\x00\x00\x01\x00\x01\x00\x1F\x00\x00\x00\x1A\x00\x00\x00\x00\x00";
        $file = $this->createUploadedFile('document.docx', $docxContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_file_with_pdf_extension_but_wrong_content_fails_validation(): void
    {
        // Create a file with .pdf extension but actual content is plain text
        $textContent = 'This is plain text, not a PDF';
        $file = $this->createUploadedFile('document.pdf', $textContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('sisu ei vasta', $validator->errors()->first('file'));

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_file_with_jpg_extension_but_wrong_content_fails_validation(): void
    {
        // Create a file with .jpg extension but actual content is plain text
        $textContent = 'This is not an image';
        $file = $this->createUploadedFile('image.jpg', $textContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('sisu ei vasta', $validator->errors()->first('file'));

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_file_with_invalid_extension_fails_validation(): void
    {
        // Create a file with invalid extension
        $content = 'Some content';
        $file = $this->createUploadedFile('document.exe', $content);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertFalse($validator->passes());
        $this->assertStringContainsString('ei ole lubatud', $validator->errors()->first('file'));

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_valid_txt_file_with_matching_content_passes_validation(): void
    {
        // Create a valid text file
        $textContent = 'This is plain text content';
        $file = $this->createUploadedFile('document.txt', $textContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_valid_xml_file_with_matching_content_passes_validation(): void
    {
        // Create a valid XML file
        $xmlContent = '<?xml version="1.0"?><root><element>content</element></root>';
        $file = $this->createUploadedFile('document.xml', $xmlContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_valid_jpeg_file_with_matching_content_passes_validation(): void
    {
        // Create a valid JPEG file (JPEG magic bytes: FF D8 FF)
        $jpegContent = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00H\x00H\x00\x00\xFF\xDB";
        $file = $this->createUploadedFile('image.jpeg', $jpegContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_valid_htm_file_with_matching_content_passes_validation(): void
    {
        // Create a valid HTML file
        $htmlContent = '<!DOCTYPE html><html><head><title>Test</title></head><body><h1>Hello</h1></body></html>';
        $file = $this->createUploadedFile('document.htm', $htmlContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }

    public function test_valid_tmx_file_with_matching_content_passes_validation(): void
    {
        // Create a valid TMX file (XML-based Translation Memory format)
        $tmxContent = '<?xml version="1.0"?><tmx version="1.4"><header><tu><tuv xml:lang="en"><seg>Hello</seg></tuv></tu></header></tmx>';
        $file = $this->createUploadedFile('document.tmx', $tmxContent);

        $validator = Validator::make(
            ['file' => $file],
            ['file' => ProjectFileValidator::createRule()]
        );

        $this->assertTrue($validator->passes());

        // Cleanup
        @unlink($file->getRealPath());
    }
}
