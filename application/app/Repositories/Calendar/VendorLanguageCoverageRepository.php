<?php

namespace App\Repositories\Calendar;

use App\Models\InstitutionMainLanguage;
use App\Models\VendorLanguageCoverage;
use App\Services\Calendar\CalendarSkillResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

readonly class VendorLanguageCoverageRepository
{
    public function __construct(
        private CalendarSkillResolver $calendarSkillResolver,
    ) {}


    /**
     * Get all vendor IDs serving a language at an institution.
     */
    public function getVendorIdsForLanguage(string $languageId, string $institutionId): Collection
    {
        return $this->baseQuery(
            institutionId: $institutionId,
            languageId: $languageId,
        )->pluck('vendor_id');
    }

    /**
     * Get full coverage data (vendor_id, language_id, is_internal, institution_user_id).
     */
    public function getCoverageForInstitution(string $institutionId): Collection
    {
        return $this->baseQuery(institutionId: $institutionId, isInternal: true)
            ->get(['vendor_id', 'language_id', 'institution_user_id']);
    }

    public function getCoverageForInstitutionMainLanguages(string $institutionId,): Collection
    {
        $mainLanguageIds = InstitutionMainLanguage::query()
            ->where('institution_id', $institutionId)
            ->pluck('language_id');

        return $this->baseQuery(institutionId: $institutionId, isInternal: true)
            ->whereIn('language_id', $mainLanguageIds)
            ->get(['vendor_id', 'language_id', 'institution_user_id']);
    }

    private function baseQuery(
        string $institutionId,
        ?string $languageId = null,
        ?bool $isInternal = null,
    ): Builder {
        $skillId = $this->calendarSkillResolver->getDefaultCalendarSkillId($institutionId);

        $query = VendorLanguageCoverage::query()
            ->where('institution_id', $institutionId)
            ->where('skill_id', $skillId);

        if ($languageId) {
            $query->where('language_id', $languageId);
        }

        if (! is_null($isInternal)) {
            $query->where('is_internal', $isInternal);
        }

        return $query;
    }
}
