<?php

namespace Tests\Feature\Services\Prices;

use App\Enums\JobKey;
use App\Enums\VolumeUnits;
use App\Models\Assignment;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CatToolJob;
use App\Models\InstitutionPartner;
use App\Models\InstitutionPartnerPrice;
use App\Models\JobDefinition;
use App\Models\ProjectTypeConfig;
use App\Models\Skill;
use App\Models\SubProject;
use App\Models\Volume;
use App\Services\Prices\ExternalPartnerAssignmentPriceCalculator;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalPartnerAssignmentPriceCalculatorTest extends TestCase
{
    private ClassifierValue $srcLang;

    private ClassifierValue $dstLang;

    private Skill $skill;

    private SubProject $subProject;

    private JobDefinition $jobDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->srcLang = ClassifierValue::factory()->language()->create();
        $this->dstLang = ClassifierValue::factory()->language()->create();
        $this->skill = Skill::query()->first();

        $this->subProject = SubProject::factory()->create([
            'source_language_classifier_value_id' => $this->srcLang->id,
            'destination_language_classifier_value_id' => $this->dstLang->id,
        ]);

        $this->jobDefinition = JobDefinition::create([
            'project_type_config_id' => ProjectTypeConfig::factory()->create()->id,
            'job_key' => JobKey::JOB_TRANSLATION,
            'skill_id' => $this->skill->id,
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 1,
        ]);
    }

    public function test_overview_job_returns_zero(): void
    {
        $overviewDefinition = JobDefinition::create([
            'project_type_config_id' => ProjectTypeConfig::factory()->create()->id,
            'job_key' => JobKey::JOB_OVERVIEW,
            'skill_id' => $this->skill->id,
            'multi_assignments_enabled' => false,
            'linking_with_cat_tool_jobs_enabled' => false,
            'sequence' => 1,
        ]);

        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $overviewDefinition->id,
        ]);

        $partner = InstitutionPartner::factory()->create();

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertSame(0, $result);
    }

    public function test_no_volumes_returns_null(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        $partner = $this->makePartnerWithPrice(pageFee: 5.0);

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertNull($result);
    }

    public function test_missing_partner_price_row_returns_null(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 10,
            'unit_fee' => null,
        ]);

        // Partner has no price row for this language pair + skill.
        $partner = InstitutionPartner::factory()->create();

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertNull($result);
    }

    public function test_simple_page_volume_returns_fee_times_quantity(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 3,
            'unit_fee' => null,
        ]);

        $partner = $this->makePartnerWithPrice(pageFee: 10.0, minFee: 0.0);

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertEqualsWithDelta(30.0, $result, 0.001);
    }

    public function test_sum_below_minimal_fee_returns_minimal_fee(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 1,
            'unit_fee' => null,
        ]);

        $partner = $this->makePartnerWithPrice(pageFee: 5.0, minFee: 20.0);

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertEqualsWithDelta(20.0, $result, 0.001);
    }

    public function test_sum_above_minimal_fee_returns_sum(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 10,
            'unit_fee' => null,
        ]);

        $partner = $this->makePartnerWithPrice(pageFee: 5.0, minFee: 20.0);

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertEqualsWithDelta(50.0, $result, 0.001);
    }

    public function test_cat_discount_from_partner_is_applied(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        $catJob = CatToolJob::create([
            'sub_project_id' => $this->subProject->id,
            'ext_id' => Str::uuid(),
            'name' => 'test-job',
            'translate_url' => 'https://example.com',
            'volume_unit_type' => VolumeUnits::Words->value,
            'volume_analysis' => [
                'tm_0_49' => 100,
                'tm_50_74' => 0,
                'tm_75_84' => 0,
                'tm_85_94' => 0,
                'tm_95_99' => 0,
                'tm_100' => 0,
                'repetitions' => 0,
                'tm_101' => 0,
                'total' => 100,
            ],
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'cat_tool_job_id' => $catJob->id,
            'unit_type' => VolumeUnits::Words,
            'unit_quantity' => 100,
            'unit_fee' => null,
        ]);

        // 50% discount on 0-49% TM matches; word_fee = 2.0
        // Expected: 100 * (100 - 50) / 100 * 2.0 = 100.0
        $partner = $this->makePartnerWithPrice(wordFee: 2.0, minFee: 0.0, discounts: [
            'discount_percentage_0_49' => 50,
            'discount_percentage_50_74' => 0,
            'discount_percentage_75_84' => 0,
            'discount_percentage_85_94' => 0,
            'discount_percentage_95_99' => 0,
            'discount_percentage_100' => 0,
            'discount_percentage_repetitions' => 0,
            'discount_percentage_101' => 0,
        ]);

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertEqualsWithDelta(100.0, $result, 0.001);
    }

    public function test_any_null_volume_price_makes_total_null(): void
    {
        $assignment = Assignment::factory()->create([
            'sub_project_id' => $this->subProject->id,
            'job_definition_id' => $this->jobDefinition->id,
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Pages,
            'unit_quantity' => 5,
            'unit_fee' => null,
        ]);

        Volume::create([
            'assignment_id' => $assignment->id,
            'unit_type' => VolumeUnits::Words,
            'unit_quantity' => 10,
            'unit_fee' => null,
        ]);

        // Partner has price only for Pages, not for Words → second volume returns null.
        $partner = InstitutionPartner::factory()->create([
            'discount_percentage_101' => null,
            'discount_percentage_repetitions' => null,
            'discount_percentage_100' => null,
            'discount_percentage_95_99' => null,
            'discount_percentage_85_94' => null,
            'discount_percentage_75_84' => null,
            'discount_percentage_50_74' => null,
            'discount_percentage_0_49' => null,
        ]);

        InstitutionPartnerPrice::factory()->create([
            'institution_partner_id' => $partner->id,
            'src_lang_classifier_value_id' => $this->srcLang->id,
            'dst_lang_classifier_value_id' => $this->dstLang->id,
            'skill_id' => $this->skill->id,
            'page_fee' => 5.0,
            'word_fee' => 0,  // zero fee → VolumePriceCalculator returns null for the Words volume
        ]);

        $result = (new ExternalPartnerAssignmentPriceCalculator($assignment->load(['jobDefinition', 'subProject', 'volumes']), $partner))->getPrice();

        $this->assertNull($result);
    }

    private function makePartnerWithPrice(
        ?float $wordFee = null,
        ?float $pageFee = null,
        ?float $minFee = null,
        array  $discounts = [],
    ): InstitutionPartner
    {
        $partner = InstitutionPartner::factory()->create(array_merge([
            'discount_percentage_101' => null,
            'discount_percentage_repetitions' => null,
            'discount_percentage_100' => null,
            'discount_percentage_95_99' => null,
            'discount_percentage_85_94' => null,
            'discount_percentage_75_84' => null,
            'discount_percentage_50_74' => null,
            'discount_percentage_0_49' => null,
        ], $discounts));

        InstitutionPartnerPrice::factory()->create([
            'institution_partner_id' => $partner->id,
            'src_lang_classifier_value_id' => $this->srcLang->id,
            'dst_lang_classifier_value_id' => $this->dstLang->id,
            'skill_id' => $this->skill->id,
            'word_fee' => $wordFee ?? 0,
            'page_fee' => $pageFee ?? 0,
            'character_fee' => 0,
            'minute_fee' => 0,
            'hour_fee' => 0,
            'minimal_fee' => $minFee ?? 0,
        ]);

        return $partner;
    }
}
