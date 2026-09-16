<?php

namespace App\Repositories\Calendar;

use App\Models\InstitutionMainLanguage;
use App\Models\Price;
use App\Services\Calendar\CalendarSettingsResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

readonly class PriceVendorLanguageCoverageRepository implements VendorLanguageCoverageRepositoryInterface
{
    public function __construct(
        private CalendarSettingsResolver $calendarSettings,
    ) {}

    public function getVendorIdsForLanguage(string $languageId, string $institutionId): Collection
    {
        return $this->baseQuery(
            institutionId: $institutionId,
            languageId: $languageId,
        )
            ->distinct()
            ->pluck('prices.vendor_id');
    }

    public function getCoverageForInstitution(string $institutionId): Collection
    {
        return $this->baseQuery(institutionId: $institutionId, isInternal: true)
            ->distinct()
            ->get([
                'prices.vendor_id',
                'prices.dst_lang_classifier_value_id as language_id',
                'v.institution_user_id',
            ]);
    }

    public function getCoverageForInstitutionMainLanguages(string $institutionId): Collection
    {
        $mainLanguageIds = InstitutionMainLanguage::query()
            ->where('institution_id', $institutionId)
            ->pluck('language_id');

        return $this->baseQuery(institutionId: $institutionId, isInternal: true)
            ->whereIn('prices.dst_lang_classifier_value_id', $mainLanguageIds)
            ->distinct()
            ->get([
                'prices.vendor_id',
                'prices.dst_lang_classifier_value_id as language_id',
                'v.institution_user_id',
            ]);
    }

    private function baseQuery(
        string $institutionId,
        ?string $languageId = null,
        ?bool $isInternal = null,
    ): Builder {
        $skillId = $this->calendarSettings->getDefaultCalendarSkillId();

        $query = Price::query()
            ->join('vendors as v', 'v.id', '=', 'prices.vendor_id')
            ->join('cached_institution_users as ciu', 'ciu.id', '=', 'v.institution_user_id')
            ->whereNull('prices.deleted_at')
            ->whereNull('v.deleted_at')
            ->whereNull('ciu.deleted_at')
            ->where('prices.skill_id', $skillId)
            ->whereRaw("(ciu.institution->>'id')::uuid = ?", [$institutionId]);

        if ($languageId) {
            $query->where('prices.dst_lang_classifier_value_id', $languageId);
        }

        if ($isInternal === true) {
            $query->whereRaw("(v.company_name IS NULL OR v.company_name = '')");
        } elseif ($isInternal === false) {
            $query->whereRaw("(v.company_name IS NOT NULL AND v.company_name != '')");
        }

        return $query;
    }
}
