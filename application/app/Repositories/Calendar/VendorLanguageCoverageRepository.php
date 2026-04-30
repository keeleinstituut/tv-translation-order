<?php

namespace App\Repositories\Calendar;

use App\Models\InstitutionMainLanguage;
use App\Models\VendorSkillLanguage;
use App\Services\Calendar\CalendarSettingsResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

readonly class VendorLanguageCoverageRepository
{
    public function __construct(
        private CalendarSettingsResolver $calendarSettings,
    ) {}

    /**
     * Get all vendor IDs serving a language at an institution.
     */
    public function getVendorIdsForLanguage(string $languageId, string $institutionId): Collection
    {
        return $this->baseQuery(
            institutionId: $institutionId,
            languageId: $languageId,
        )->pluck('vendor_skill_languages.vendor_id');
    }

    /**
     * Get coverage rows (vendor_id, language_id, institution_user_id) for an institution's internal vendors.
     */
    public function getCoverageForInstitution(string $institutionId): Collection
    {
        return $this->baseQuery(institutionId: $institutionId, isInternal: true)
            ->get([
                'vendor_skill_languages.vendor_id',
                'vendor_skill_languages.dst_lang_classifier_value_id as language_id',
                'v.institution_user_id',
            ]);
    }

    public function getCoverageForInstitutionMainLanguages(string $institutionId): Collection
    {
        $mainLanguageIds = InstitutionMainLanguage::query()
            ->where('institution_id', $institutionId)
            ->pluck('language_id');

        return $this->baseQuery(institutionId: $institutionId, isInternal: true)
            ->whereIn('vendor_skill_languages.dst_lang_classifier_value_id', $mainLanguageIds)
            ->get([
                'vendor_skill_languages.vendor_id',
                'vendor_skill_languages.dst_lang_classifier_value_id as language_id',
                'v.institution_user_id',
            ]);
    }

    private function baseQuery(
        string $institutionId,
        ?string $languageId = null,
        ?bool $isInternal = null,
    ): Builder {
        $skillId = $this->calendarSettings->getDefaultCalendarSkillId($institutionId);

        $query = VendorSkillLanguage::query()
            ->join('vendors as v', 'v.id', '=', 'vendor_skill_languages.vendor_id')
            ->join('cached_institution_users as ciu', 'ciu.id', '=', 'v.institution_user_id')
            ->whereNull('v.deleted_at')
            ->whereNull('ciu.deleted_at')
            ->where('vendor_skill_languages.skill_id', $skillId)
            ->whereRaw("(ciu.institution->>'id')::uuid = ?", [$institutionId]);

        if ($languageId) {
            $query->where('vendor_skill_languages.dst_lang_classifier_value_id', $languageId);
        }

        if ($isInternal === true) {
            $query->whereRaw("(v.company_name IS NULL OR v.company_name = '')");
        } elseif ($isInternal === false) {
            $query->whereRaw("(v.company_name IS NOT NULL AND v.company_name != '')");
        }

        return $query;
    }
}
