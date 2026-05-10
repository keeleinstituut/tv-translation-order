<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\OutsourceOfferStatus;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AuthHelpers;
use Tests\TestCase;

class SubProjectControllerShowTest extends TestCase
{
    public function test_partner_cannot_see_source_files_when_request_excludes_them(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));

        $ownerInstitution = Institution::factory()->create();
        $partnerUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ViewOutsourceRequest);

        $subProject = $this->createSubProjectForInstitution($ownerInstitution->id);
        $this->attachSourceFile($subProject);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'include_source_files' => false,
        ]);

        OutsourceOffer::factory()->notified()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerUser->institution['id'],
        ]);

        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/subprojects/{$subProject->id}");

        $response
            ->assertOk()
            ->assertJsonMissingPath('data.source_files');
    }

    public function test_partner_can_see_source_files_when_request_includes_them(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));

        $ownerInstitution = Institution::factory()->create();
        $partnerUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ViewOutsourceRequest);

        $subProject = $this->createSubProjectForInstitution($ownerInstitution->id);
        $this->attachSourceFile($subProject);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'include_source_files' => true,
        ]);

        OutsourceOffer::factory()->notified()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerUser->institution['id'],
        ]);

        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/subprojects/{$subProject->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.source_files.0.file_name', 'source-file.pdf');
    }

    public function test_selected_partner_can_see_source_files_even_when_request_excludes_them(): void
    {
        Storage::fake(config('media-library.disk_name', 'test-disk'));

        $ownerInstitution = Institution::factory()->create();
        $partnerUser = InstitutionUser::factory()->createWithPrivileges(PrivilegeKey::ViewOutsourceRequest);

        $subProject = $this->createSubProjectForInstitution($ownerInstitution->id);
        $this->attachSourceFile($subProject);
        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);

        $outsourceRequest = OutsourceRequest::factory()->create([
            'assignment_id' => $assignment->id,
            'include_source_files' => false,
        ]);

        OutsourceOffer::factory()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $partnerUser->institution['id'],
            'status' => OutsourceOfferStatus::OfferAccepted,
        ]);

        $response = $this->withHeaders(AuthHelpers::createHeadersForInstitutionUser($partnerUser))
            ->getJson("/api/subprojects/{$subProject->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.source_files.0.file_name', 'source-file.pdf');
    }

    private function createSubProjectForInstitution(string $institutionId): SubProject
    {
        $project = Project::factory()->create(['institution_id' => $institutionId]);

        return SubProject::factory()->create([
            'project_id' => $project->id,
            'source_language_classifier_value_id' => ClassifierValue::factory()->language(),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);
    }

    private function attachSourceFile(SubProject $subProject): void
    {
        $subProject->project
            ->addMedia(UploadedFile::fake()->create('source-file.pdf', 10, 'application/pdf'))
            ->toMediaCollection($subProject->file_collection);
    }
}
