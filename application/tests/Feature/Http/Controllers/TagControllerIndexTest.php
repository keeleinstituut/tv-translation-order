<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PrivilegeKey;
use App\Enums\TagType;
use App\Http\Controllers\TagController;
use App\Models\Institution;
use App\Models\Tag;
use Illuminate\Testing\TestResponse;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class TagControllerIndexTest extends TestCase
{
    public function test_list_of_tags_returned(): void
    {
        $tags = Tag::factory(10)->for(
            $institution = Institution::factory()->create()
        )->create();

        $response = $this->sendListRequestWithCustomHeaders(
            AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag])
        )->assertOk();

        $tags->each(fn (Tag $tag) => $response->assertJsonFragment(
            RepresentationHelpers::createTagFlatRepresentation($tag)
        ));
    }

    public function test_list_of_tags_filtered_by_type_returned(): void
    {
        $orderTags = Tag::factory(10)->for(
            $institution = Institution::factory()->create()
        )->create(['type' => TagType::Order->value]);

        $vendorTags = Tag::factory(10)->for($institution)
            ->create(['type' => TagType::Vendor->value]);

        $response = $this->sendListRequestWithCustomHeaders(
            AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]),
            ['type' => TagType::Order->value],
        )->assertOk();

        $orderTags->each(fn (Tag $tag) => $response->assertJsonFragment(
            RepresentationHelpers::createTagFlatRepresentation($tag)
        ));

        $vendorTags->each(fn (Tag $tag) => $response->assertJsonMissingExact(
            RepresentationHelpers::createTagFlatRepresentation($tag)
        ));
    }

    public function test_list_of_vendor_skill_tags_returned(): void
    {
        $institution = Institution::factory()->create();
        $vendorSkillTags = Tag::factory(10)->vendorSkills()->create();
        $vendorSkillTags->map(fn (Tag $tag) => $this->assertEmpty($tag->institution_id));

        $response = $this->sendListRequestWithCustomHeaders(
            AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]),
            ['type' => TagType::VendorSkill->value],
        )->assertOk();

        $vendorSkillTags->each(fn (Tag $tag) => $response->assertJsonFragment(
            RepresentationHelpers::createTagFlatRepresentation($tag)
        ));
    }

    public function test_list_of_tags_doesnt_contains_tags_from_another_institution(): void
    {
        $institution = Institution::factory()->create();
        $tags = Tag::factory(10)->create();
        $this->sendListRequestWithCustomHeaders(
            AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag])
        )->assertOk()->assertJsonMissing(
            $tags->map(fn (Tag $tag) => RepresentationHelpers::createTagFlatRepresentation($tag))
                ->toArray()
        );
    }

    public function test_list_of_tags_doesnt_contains_deleted_tags(): void
    {
        $tags = Tag::factory(10)->trashed()->for(
            $institution = Institution::factory()->create()
        )->create();

        $this->sendListRequestWithCustomHeaders(
            AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag])
        )->assertOk()->assertJsonMissing(
            $tags->map(fn (Tag $tag) => RepresentationHelpers::createTagFlatRepresentation($tag))
                ->toArray()
        );
    }

    private function sendListRequestWithCustomHeaders(array $headers, array $queryParams = []): TestResponse
    {
        return $this->withHeaders($headers)->getJson(
            action([TagController::class, 'index'], $queryParams),
        );
    }
}
