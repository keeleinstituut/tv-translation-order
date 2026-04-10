<?php

namespace Tests\Unit;

use App\Services\Calendar\SlotDiscretizationService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class SlotDiscretizationServiceTest extends TestCase
{
    private SlotDiscretizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlotDiscretizationService();
    }

    public function test_pm_view_emits_partial_slot_at_start_of_interval(): void
    {
        // Vendor free 9:30–11:00 → partial 9:30–10:00 + full 10:00–11:00
        $nine_thirty = $this->ts('09:30');
        $eleven = $this->ts('11:00');

        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$nine_thirty, $eleven]],
        ]);

        $this->assertCount(2, $result);
        $this->assertSlot($result[0], '09:30', '10:00', ['v1']);
        $this->assertSlot($result[1], '10:00', '11:00', ['v1']);
    }

    public function test_pm_view_emits_partial_slot_at_end_of_interval(): void
    {
        // Vendor free 9:00–10:30 → full 9:00–10:00 + partial 10:00–10:30
        $nine = $this->ts('09:00');
        $ten_thirty = $this->ts('10:30');

        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$nine, $ten_thirty]],
        ]);

        $this->assertCount(2, $result);
        $this->assertSlot($result[0], '09:00', '10:00', ['v1']);
        $this->assertSlot($result[1], '10:00', '10:30', ['v1']);
    }

    public function test_pm_view_emits_partial_slots_at_both_boundaries(): void
    {
        // Vendor free 9:30–11:30 → partial 9:30–10:00, full 10:00–11:00, partial 11:00–11:30
        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$this->ts('09:30'), $this->ts('11:30')]],
        ]);

        $this->assertCount(3, $result);
        $this->assertSlot($result[0], '09:30', '10:00', ['v1']);
        $this->assertSlot($result[1], '10:00', '11:00', ['v1']);
        $this->assertSlot($result[2], '11:00', '11:30', ['v1']);
    }

    public function test_pm_view_exact_hour_boundaries_produce_no_partial_slots(): void
    {
        // Vendor free 9:00–11:00 → 2 full hour slots, no partials
        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$this->ts('09:00'), $this->ts('11:00')]],
        ]);

        $this->assertCount(2, $result);
        $this->assertSlot($result[0], '09:00', '10:00', ['v1']);
        $this->assertSlot($result[1], '10:00', '11:00', ['v1']);
    }

    public function test_pm_view_overlapping_slots_when_vendors_have_different_partial_ranges(): void
    {
        // v1 free full hour 9:00–10:00, v2 free only 9:30–10:00
        // → two overlapping slots: {9:00–10:00, [v1]} and {9:30–10:00, [v2]}
        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$this->ts('09:00'), $this->ts('10:00')]],
            'v2' => [[$this->ts('09:30'), $this->ts('10:00')]],
        ]);

        $this->assertCount(2, $result);
        $this->assertSlot($result[0], '09:00', '10:00', ['v1']);
        $this->assertSlot($result[1], '09:30', '10:00', ['v2']);
    }

    public function test_pm_view_vendors_with_same_interval_are_grouped(): void
    {
        // Both vendors free 9:00–10:00 → single slot with both vendor IDs
        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$this->ts('09:00'), $this->ts('10:00')]],
            'v2' => [[$this->ts('09:00'), $this->ts('10:00')]],
        ]);

        $this->assertCount(1, $result);
        $this->assertSlot($result[0], '09:00', '10:00', ['v1', 'v2']);
    }

    public function test_pm_view_vendors_with_same_partial_interval_are_grouped(): void
    {
        // Both vendors free 9:30–10:00 → single partial slot with both vendor IDs
        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$this->ts('09:30'), $this->ts('10:00')]],
            'v2' => [[$this->ts('09:30'), $this->ts('10:00')]],
        ]);

        $this->assertCount(1, $result);
        $this->assertSlot($result[0], '09:30', '10:00', ['v1', 'v2']);
    }

    public function test_pm_view_single_sub_hour_interval_emits_single_partial_slot(): void
    {
        // Vendor free only 9:15–9:45 → single partial slot
        $result = $this->service->discretizeWithVendorIds([
            'v1' => [[$this->ts('09:15'), $this->ts('09:45')]],
        ]);

        $this->assertCount(1, $result);
        $this->assertSlot($result[0], '09:15', '09:45', ['v1']);
    }

    public function test_pm_view_empty_input_returns_empty(): void
    {
        $result = $this->service->discretizeWithVendorIds([]);
        $this->assertCount(0, $result);
    }

    public function test_client_view_emits_partial_slots_at_boundaries(): void
    {
        // Single language interval 9:30–11:30 → 3 slots
        $result = $this->service->discretizeLanguageSlots([
            'lang-en' => [[$this->ts('09:30'), $this->ts('11:30')]],
        ]);

        $this->assertCount(3, $result);
        $this->assertLanguageSlot($result[0], '09:30', '10:00', ['lang-en']);
        $this->assertLanguageSlot($result[1], '10:00', '11:00', ['lang-en']);
        $this->assertLanguageSlot($result[2], '11:00', '11:30', ['lang-en']);
    }

    public function test_client_view_full_hour_covers_partial_for_same_language(): void
    {
        // Simulates: vendor A covers lang-en 9:00–10:00, vendor B covers lang-en 9:30–10:00
        // fanOutByLanguage merges overlapping intervals per language, so the merged
        // lang-en interval is 9:00–10:00 (full hour) → only the 1h slot is returned.
        // The 30-minute overlap from vendor B is absorbed into the merged interval.
        $result = $this->service->discretizeLanguageSlots([
            'lang-en' => [[$this->ts('09:00'), $this->ts('10:00')], [$this->ts('09:30'), $this->ts('10:00')]],
        ]);

        $this->assertCount(1, $result);
        $this->assertLanguageSlot($result[0], '09:00', '10:00', ['lang-en']);
    }

    public function test_client_view_only_partial_available_returns_partial_slot(): void
    {
        // Only 30 minutes of availability for a language (no vendor covers the full hour)
        // After merging, the interval is still 9:30–10:00 → partial slot returned
        $result = $this->service->discretizeLanguageSlots([
            'lang-en' => [[$this->ts('09:30'), $this->ts('10:00')]],
        ]);

        $this->assertCount(1, $result);
        $this->assertLanguageSlot($result[0], '09:30', '10:00', ['lang-en']);
    }

    public function test_client_view_exact_hour_boundaries_produce_no_partial_slots(): void
    {
        $result = $this->service->discretizeLanguageSlots([
            'lang-en' => [[$this->ts('09:00'), $this->ts('11:00')]],
        ]);

        $this->assertCount(2, $result);
        $this->assertLanguageSlot($result[0], '09:00', '10:00', ['lang-en']);
        $this->assertLanguageSlot($result[1], '10:00', '11:00', ['lang-en']);
    }

    public function test_client_view_two_languages_one_full_one_partial_in_same_hour(): void
    {
        // lang-en covers full hour 9:00–10:00, lang-de covers only 9:30–10:00
        // Sweep produces: 9:00–9:30 [en], 9:30–10:00 [de, en]
        // Discretization: both sub-intervals are within the same hour and non-overlapping
        $result = $this->service->discretizeLanguageSlots([
            'lang-en' => [[$this->ts('09:00'), $this->ts('10:00')]],
            'lang-de' => [[$this->ts('09:30'), $this->ts('10:00')]],
        ]);

        $this->assertCount(2, $result);
        $this->assertLanguageSlot($result[0], '09:00', '09:30', ['lang-en']);
        $this->assertLanguageSlot($result[1], '09:30', '10:00', ['lang-de', 'lang-en']);
    }

    public function test_client_view_empty_input_returns_empty(): void
    {
        $result = $this->service->discretizeLanguageSlots([]);
        $this->assertCount(0, $result);
    }

    public function test_client_view_single_sub_hour_interval(): void
    {
        // Only 15 minutes available → single partial slot
        $result = $this->service->discretizeLanguageSlots([
            'lang-en' => [[$this->ts('09:15'), $this->ts('09:30')]],
        ]);

        $this->assertCount(1, $result);
        $this->assertLanguageSlot($result[0], '09:15', '09:30', ['lang-en']);
    }

    /**
     * Create a UTC timestamp for a given time on 2026-01-01.
     */
    private function ts(string $time): int
    {
        return Carbon::parse("2026-01-01 {$time}", 'UTC')->timestamp;
    }

    /**
     * Assert a PM-view slot matches expected time range and vendor IDs.
     *
     * @param array{start_at: string, end_at: string, vendor_ids: string[]} $slot
     * @param string[] $vendorIds
     */
    private function assertSlot(array $slot, string $expectedStart, string $expectedEnd, array $vendorIds): void
    {
        $this->assertEquals(
            Carbon::parse("2026-01-01 {$expectedStart}", 'UTC')->toIso8601String(),
            $slot['start_at'],
            "Slot start_at mismatch"
        );
        $this->assertEquals(
            Carbon::parse("2026-01-01 {$expectedEnd}", 'UTC')->toIso8601String(),
            $slot['end_at'],
            "Slot end_at mismatch"
        );
        $this->assertEqualsCanonicalizing($vendorIds, $slot['vendor_ids'], "Slot vendor_ids mismatch");
    }

    /**
     * Assert a client-view slot matches expected time range and languages.
     *
     * @param array{start_at: string, end_at: string, languages: string[]} $slot
     * @param string[] $languages
     */
    private function assertLanguageSlot(array $slot, string $expectedStart, string $expectedEnd, array $languages): void
    {
        $this->assertEquals(
            Carbon::parse("2026-01-01 {$expectedStart}", 'UTC')->toIso8601String(),
            $slot['start_at'],
            "Slot start_at mismatch"
        );
        $this->assertEquals(
            Carbon::parse("2026-01-01 {$expectedEnd}", 'UTC')->toIso8601String(),
            $slot['end_at'],
            "Slot end_at mismatch"
        );
        $this->assertEqualsCanonicalizing($languages, $slot['languages'], "Slot languages mismatch");
    }
}
