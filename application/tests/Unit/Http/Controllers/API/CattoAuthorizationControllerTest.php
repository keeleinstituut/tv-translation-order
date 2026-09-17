<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Http\Controllers\API\CattoAuthorizationController;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Project;
use App\Models\SubProject;
use App\Sync\ApiClients\TvAuthorizationApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class CattoAuthorizationControllerTest extends TestCase
{
    private string $ownerInstitutionId;
    private string $partnerInstitutionId;
    private string $noPrivInstitutionId;
    private string $cattoProjectId;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerInstitutionId = Institution::factory()->create()->id;
        $this->partnerInstitutionId = Institution::factory()->create()->id;
        $this->noPrivInstitutionId = Institution::factory()->create()->id;
        $this->cattoProjectId = 'catto-proj-'.Str::random(8);

        $this->project = Project::factory()->create(['institution_id' => $this->ownerInstitutionId]);

        $subProject = SubProject::factory()->create([
            'project_id' => $this->project->id,
            'cat_metadata' => ['catto_project_id' => $this->cattoProjectId],
            'source_language_classifier_value_id' => ClassifierValue::factory()->language()->create(['value' => 'en']),
            'destination_language_classifier_value_id' => ClassifierValue::factory()->language(),
        ]);

        $assignment = Assignment::factory()->create(['sub_project_id' => $subProject->id]);
        $outsourceRequest = OutsourceRequest::factory()->create(['assignment_id' => $assignment->id]);
        OutsourceOffer::factory()->accepted()->create([
            'outsource_request_id' => $outsourceRequest->id,
            'institution_id' => $this->partnerInstitutionId,
        ]);
    }

    private function fakeClient(): TvAuthorizationApiClient
    {
        $owner = $this->ownerInstitutionId;
        $partner = $this->partnerInstitutionId;
        $noPriv = $this->noPrivInstitutionId;

        return new class($owner, $partner, $noPriv) extends TvAuthorizationApiClient {
            public function __construct(
                private string $owner,
                private string $partner,
                private string $noPriv,
            ) {
            }

            public function getUserPrivileges(string $pic): array
            {
                return match ($pic) {
                    'PIC-VIEWER' => ['institutionPrivileges' => [$this->owner => ['VIEW_INSTITUTION_PROJECT_DETAIL']]],
                    'PIC-MANAGER' => ['institutionPrivileges' => [$this->owner => ['MANAGE_PROJECT']]],
                    'PIC-UNRELATED' => ['institutionPrivileges' => [$this->noPriv => ['VIEW_INSTITUTION_PROJECT_DETAIL']]],
                    'PIC-PARTNER' => ['institutionPrivileges' => [$this->partner => ['VIEW_OUTSOURCE_REQUEST']]],
                    'PIC-EMPTY' => ['institutionPrivileges' => []],
                    default => ['institutionPrivileges' => []],
                };
            }

            public function getJwtClaims(string $pic, string $institutionId): array
            {
                $privileges = data_get($this->getUserPrivileges($pic), "institutionPrivileges.$institutionId", []);

                return [
                    'personalIdentificationCode' => $pic,
                    'userId' => (string) Str::uuid(),
                    'institutionUserId' => (string) Str::uuid(),
                    'forename' => 'Test',
                    'surname' => 'User',
                    'selectedInstitution' => ['id' => $institutionId, 'name' => 'Test Institution', 'type' => 'institution'],
                    'privileges' => $privileges,
                ];
            }
        };
    }

    private function checkAllows(string $pic, string $ability, ?string $cattoProjectId = null): bool
    {
        $request = Request::create('/api/catto-authorization', 'GET', [
            'catto_project_id' => $cattoProjectId ?? $this->cattoProjectId,
            'personal_identification_code' => $pic,
            'ability' => $ability,
        ]);

        $controller = app(CattoAuthorizationController::class);
        $response = $controller->check($request, $this->fakeClient());

        return data_get(json_decode($response->getContent(), true), 'allowed');
    }

    public function test_broad_institution_privilege_grants_view(): void
    {
        $this->assertTrue($this->checkAllows('PIC-VIEWER', 'view'));
    }

    public function test_broad_view_privilege_does_not_grant_manage(): void
    {
        $this->assertFalse($this->checkAllows('PIC-VIEWER', 'update'));
    }

    public function test_manage_project_privilege_grants_update(): void
    {
        $this->assertTrue($this->checkAllows('PIC-MANAGER', 'update'));
    }

    public function test_manage_only_privilege_does_not_grant_view(): void
    {
        // ProjectPolicy::view() doesn't check MANAGE_PROJECT at all.
        $this->assertFalse($this->checkAllows('PIC-MANAGER', 'view'));
    }

    public function test_privilege_in_unrelated_institution_is_denied(): void
    {
        $this->assertFalse($this->checkAllows('PIC-UNRELATED', 'view'));
    }

    public function test_accepted_partner_institution_grants_view(): void
    {
        $this->assertTrue($this->checkAllows('PIC-PARTNER', 'view'));
    }

    public function test_accepted_partner_institution_does_not_grant_update(): void
    {
        // tv-translation-order's own ProjectPolicy::update() deliberately excludes partners.
        $this->assertFalse($this->checkAllows('PIC-PARTNER', 'update'));
    }

    public function test_person_with_no_institutions_is_denied(): void
    {
        $this->assertFalse($this->checkAllows('PIC-EMPTY', 'view'));
    }

    public function test_unknown_catto_project_id_fails_closed(): void
    {
        $this->assertFalse($this->checkAllows('PIC-VIEWER', 'view', 'does-not-exist'));
    }
}
