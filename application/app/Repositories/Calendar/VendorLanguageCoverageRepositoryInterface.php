<?php

namespace App\Repositories\Calendar;

use Illuminate\Support\Collection;

interface VendorLanguageCoverageRepositoryInterface
{
    /**
     * Get all vendor IDs serving a language at an institution.
     */
    public function getVendorIdsForLanguage(string $languageId, string $institutionId): Collection;

    /**
     * Get coverage rows (vendor_id, language_id, institution_user_id) for an institution's internal vendors.
     */
    public function getCoverageForInstitution(string $institutionId): Collection;

    /**
     * Get coverage rows (vendor_id, language_id, institution_user_id) for an institution's internal vendors,
     * limited to the institution's main languages.
     */
    public function getCoverageForInstitutionMainLanguages(string $institutionId): Collection;
}
