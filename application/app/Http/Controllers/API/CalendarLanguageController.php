<?php

namespace App\Http\Controllers\API;

use App\Enums\CalendarRole;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CalendarLanguagesRequest;
use App\Http\Resources\API\CalendarLanguageResource;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionMainLanguage;
use App\Models\InstitutionUserPinnedLanguage;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Policies\AssignmentPolicy;
use App\Policies\InstitutionMainLanguagePolicy;
use App\Policies\InstitutionUserPinnedLanguagePolicy;
use App\Policies\SubProjectPolicy;
use App\Policies\VendorCalendarEntryPolicy;
use App\Services\Calendar\CalendarRoleResolver;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarLanguageController extends Controller
{
    public function __construct(
        private readonly CalendarRoleResolver $roleResolver,
        AuditLogPublisher                     $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    #[OA\Get(
        path: '/calendar/languages',
        description: 'Returns main, pinned, and project languages for the date range. For vendor callers, `main_languages` and `pinned_languages` are always empty arrays — only `project_languages` (destination languages from their booked assignments) are populated.',
        summary: 'List languages available for the calendar of the current institution',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'date_from', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'date_to', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: CalendarLanguageResource::class, description: 'Language lists for the requested date range. Vendor callers receive empty main_languages and pinned_languages arrays.')]
    public function languages(CalendarLanguagesRequest $request): CalendarLanguageResource
    {
        $this->authorize('viewAny', InstitutionMainLanguage::class);

        $dateFrom = Carbon::parse($request->validated('date_from'))->startOfDay()->utc();
        $dateTo = Carbon::parse($request->validated('date_to'))->endOfDay()->utc();

        return match ($this->roleResolver->resolve()) {
            CalendarRole::ProjectManager, CalendarRole::Client => $this->clientAndProjectManagerView(
                $this->roleResolver->getInstitutionId(),
                $this->roleResolver->getInstitutionUserId(),
                $dateFrom,
                $dateTo,
            ),
            CalendarRole::Vendor => $this->vendorView(
                $this->roleResolver->getVendor(),
                $dateFrom,
                $dateTo
            ),
            CalendarRole::Unknown => throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid role')
        };
    }

    private function vendorView(Vendor $vendor, Carbon $dateFrom, Carbon $dateTo): CalendarLanguageResource
    {
        $assignmentIds = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->where('vendor_id', $vendor->id)
            ->whereNotNull('assignment_id')
            ->where('start_at', '<', $dateTo)
            ->where('end_at', '>', $dateFrom)
            ->distinct()
            ->pluck('assignment_id');

        $projectLanguageIds = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
            ->whereIn('assignments.id', $assignmentIds)
            ->join('sub_projects as sp', 'sp.id', '=', 'assignments.sub_project_id')
            ->distinct()
            ->pluck('sp.destination_language_classifier_value_id');

        $projectLanguages = ClassifierValue::whereIn('id', $projectLanguageIds)->orderBy('name')->get();

        return CalendarLanguageResource::make([
            'project_languages' => $projectLanguages,
        ]);
    }

    private function clientAndProjectManagerView(
        string $institutionId,
        string $institutionUserId,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): CalendarLanguageResource
    {
        $mainLanguages = InstitutionMainLanguage::withGlobalScope('policy', InstitutionMainLanguagePolicy::scope())
            ->with('language')
            ->get();

        $pinnedLanguages = InstitutionUserPinnedLanguage::withGlobalScope('policy', InstitutionUserPinnedLanguagePolicy::scope())
            ->get();

        // Source 1: destination languages from calendar entries within the date range
        $assignmentIds = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->whereNotNull('assignment_id')
            ->where('start_at', '<', $dateTo)
            ->where('end_at', '>', $dateFrom)
            ->distinct()
            ->pluck('assignment_id');

        $calendarEntryLanguageIds = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
            ->whereIn('assignments.id', $assignmentIds)
            ->whereHas('subProject.project', fn($q) => $q->where('institution_id', $institutionId))
            ->join('sub_projects as sp', 'sp.id', '=', 'assignments.sub_project_id')
            ->distinct()
            ->pluck('sp.destination_language_classifier_value_id');

        // Source 2: client projects that have no vendor assigned yet
        $clientProjectLanguageIds = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->whereHas('project', fn($q) => $q
                ->where('client_institution_user_id', $institutionUserId)
                ->where('is_calendar_project', true)
                ->where('event_start_at', '<', $dateTo)
                ->where('event_end_at', '>', $dateFrom)
                ->whereNot('status', ProjectStatus::Cancelled)
            )
            ->whereDoesntHave('assignments.calendarEntry')
            ->distinct()
            ->pluck('destination_language_classifier_value_id');

        $projectLanguageIds = $calendarEntryLanguageIds
            ->merge($clientProjectLanguageIds)
            ->unique();

        $projectLanguages = $projectLanguageIds->isNotEmpty()
            ? ClassifierValue::whereIn('id', $projectLanguageIds)->orderBy('name')->get()
            : collect();

        return CalendarLanguageResource::make([
            'main_languages' => $mainLanguages,
            'pinned_languages' => $pinnedLanguages,
            'project_languages' => $projectLanguages,
        ]);
    }
}
