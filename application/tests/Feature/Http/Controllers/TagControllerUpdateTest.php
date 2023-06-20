<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PrivilegeKey;
use App\Enums\TagType;
use App\Http\Controllers\TagController;
use App\Models\Institution;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Testing\TestResponse;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class TagControllerUpdateTest extends TestCase
{
    public function test_that_new_tags_inserted_existing_tags_updated_missing_tags_removed(): void
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::Order;
        $newTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->make()->map(fn (Tag $tag) => [
                'id' => null,
                'name' => $tag->name,
            ]);
        $updatedTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create()->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => "new $tag->name",
            ]);
        $tagsAttributes = $newTagsAttributes->merge($updatedTagsAttributes);

        $missingTags = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create();

        $response = $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => $tagsAttributes->map(fn (array $tagAttributes) => [
                'id' => $tagAttributes['id'],
                'name' => $tagAttributes['name'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::EditTag,
            PrivilegeKey::DeleteTag,
        ]));
        $response->assertOk();

        $tags = collect();
        foreach ($tagsAttributes as $tagAttributes) {
            $tag = Tag::query()->where('name', $tagAttributes['name'])
                ->where('type', $tagsType)
                ->where('institution_id', $institution->id)
                ->when(
                    filled($tagAttributes['id']),
                    fn (Builder $query) => $query->where('id', $tagAttributes['id']))
                ->first();

            $this->assertModelExists($tag);
            $tags->add($tag);
        }

        foreach ($missingTags as $missingTag) {
            $this->assertSoftDeleted($missingTag);
        }

        $response->assertJson([
            'data' => $tags->map(fn (Tag $tag) => RepresentationHelpers::createTagFlatRepresentation($tag))
                ->toArray(),
        ]);
    }

    public function test_creating_tags_with_already_existing_name_returned_422()
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::Order;
        $alreadyExistingTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create()->map(fn (Tag $tag) => [
                'id' => null,
                'name' => $tag->name,
            ]);

        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => $alreadyExistingTagsAttributes->map(fn (array $tagAttributes) => [
                'id' => $tagAttributes['id'],
                'name' => $tagAttributes['name'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::EditTag,
            PrivilegeKey::DeleteTag,
        ]))->assertUnprocessable();
    }

    public function test_creating_of_vendor_skill_tags_returned_422()
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::VendorSkill;
        $updatedTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create()->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => "new $tag->name",
            ]);

        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => $updatedTagsAttributes->map(fn (array $tagAttributes) => [
                'id' => $tagAttributes['id'],
                'name' => $tagAttributes['name'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::EditTag,
            PrivilegeKey::DeleteTag,
        ]))->assertUnprocessable();
    }

    public function test_deleting_of_vendor_skill_tags_returned_422()
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::VendorSkill;
        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => [],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::EditTag,
            PrivilegeKey::DeleteTag,
        ]))->assertUnprocessable();
    }

    public function test_editing_of_vendor_skill_tags_returned_422()
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::VendorSkill;
        $newTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->make()->map(fn (Tag $tag) => [
                'id' => null,
                'name' => $tag->name,
            ]);

        Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create();

        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => $newTagsAttributes->map(fn (array $tagAttributes) => [
                'id' => $tagAttributes['id'],
                'name' => $tagAttributes['name'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::EditTag,
            PrivilegeKey::DeleteTag,
        ]))->assertUnprocessable();
    }

    public function test_delete_without_privilege_returned_403(): void
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::Order;

        Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create();

        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => [],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::EditTag,
        ]))->assertForbidden();
    }

    public function test_insert_without_privilege_returned_403(): void
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::Order;
        $newTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->make()->map(fn (Tag $tag) => [
                'id' => null,
                'name' => $tag->name,
            ]);

        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => $newTagsAttributes->map(fn (array $tagAttributes) => [
                'id' => $tagAttributes['id'],
                'name' => $tagAttributes['name'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::EditTag,
            PrivilegeKey::DeleteTag,
        ]))->assertForbidden();
    }

    public function test_edit_without_privilege_returned_403(): void
    {
        $institution = Institution::factory()->create();
        $tagsType = TagType::Order;
        $newTagsAttributes = Tag::factory(10)->for($institution)
            ->withType($tagsType)
            ->create()->map(fn (Tag $tag) => [
                'id' => $tag->id,
                'name' => "new $tag->name",
            ]);

        $this->sendUpdateRequestWithCustomHeaders([
            'type' => $tagsType->value,
            'tags' => $newTagsAttributes->map(fn (array $tagAttributes) => [
                'id' => $tagAttributes['id'],
                'name' => $tagAttributes['name'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [
            PrivilegeKey::AddTag,
            PrivilegeKey::DeleteTag,
        ]))->assertForbidden();
    }

    public function test_unauthorized_edit_returned_401(): void
    {
        $this->sendUpdateRequestWithCustomHeaders([
            'type' => TagType::Order->value,
            'tags' => [
                ['name' => 'Some name'],
            ],
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    private function sendUpdateRequestWithCustomHeaders(array $requestParams, array $headers): TestResponse
    {
        return $this->withHeaders($headers)->postJson(
            action([TagController::class, 'update']),
            $requestParams
        );
    }
}
