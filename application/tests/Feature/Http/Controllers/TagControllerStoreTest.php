<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\PrivilegeKey;
use App\Enums\TagType;
use App\Http\Controllers\TagController;
use App\Models\CachedEntities\Institution;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Str;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class TagControllerStoreTest extends TestCase
{
    public function test_storing_of_tags_returned_200(): void
    {
        $institution = Institution::factory()->create();
        $tagsAttributes = Tag::factory(10)->make()
            ->map(fn (Tag $tag) => Arr::only($tag->getAttributes(), ['name', 'type']));

        $response = $this->sendStoreRequestWithCustomHeaders([
            'tags' => $tagsAttributes->map(fn (array $tagAttributes) => [
                'name' => $tagAttributes['name'],
                'type' => $tagAttributes['type'],
            ])->toArray(),
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]));

        $response->assertOk();

        $tags = collect();
        foreach ($tagsAttributes as $tagAttributes) {
            $tag = Tag::query()->where('name', $tagAttributes['name'])
                ->where('type', $tagAttributes['type'])
                ->where('institution_id', $institution->id)
                ->first();

            $this->assertModelExists($tag);
            $tags->add($tag);
        }

        $response->assertJson([
            'data' => $tags->map(fn (Tag $tag) => RepresentationHelpers::createTagFlatRepresentation($tag))
                ->toArray(),
        ]);
    }

    public function test_storing_of_vendor_skill_tags_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => 'Some name', 'type' => TagType::VendorSkill->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_storing_of_tags_with_empty_name_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => '', 'type' => TagType::TranslationMemory->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_storing_of_tags_with_empty_type_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => 'Some name', 'type' => ''],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_storing_of_tags_with_incorrect_type_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => 'Some name', 'type' => 'some type'],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_space_at_the_beginning_of_name_automatically_trimmed(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => $name = ' Some name', 'type' => $type = TagType::Order->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertOk();

        $tag = Tag::query()->where('name', trim($name))->where('type', $type)
            ->where('institution_id', $institution->id)->first();

        $this->assertModelExists($tag);
    }

    public function test_storing_of_tags_with_hyphen_at_the_beginning_of_name_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => '-Some name', 'type' => TagType::Order->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_storing_of_tag_with_already_existing_name_returned_422(): void
    {
        $tag = Tag::factory()->for(
            $institution = Institution::factory()->create()
        )->create();

        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => $tag->name, 'type' => $tag->type->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_storing_of_tag_with_already_existing_name_that_was_trashed_returned_200(): void
    {
        $tag = Tag::factory()->trashed()->for(
            $institution = Institution::factory()->create()
        )->create();

        $response = $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => $tag->name, 'type' => $tag->type->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]));

        $newTag = Tag::query()->where('type', $tag->type)
            ->where('name', $tag->name)
            ->first();
        $this->assertModelExists($newTag);

        $response->assertOk()->assertJson([
            'data' => [
                RepresentationHelpers::createTagFlatRepresentation($newTag),
            ],
        ]);
    }

    public function test_storing_of_tag_with_already_existing_name_from_another_institution_returned_200(): void
    {
        $tag = Tag::factory()->for(
            Institution::factory()->create()
        )->create();

        $institution = Institution::factory()->create();
        $response = $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => $tag->name, 'type' => $tag->type->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]));

        $newTag = Tag::query()->where('type', $tag->type)
            ->where('name', $tag->name)
            ->where('institution_id', $institution->id)
            ->first();

        $response->assertOk()->assertJson([
            'data' => [
                RepresentationHelpers::createTagFlatRepresentation($newTag),
            ],
        ]);
    }

    public function test_storing_of_tag_with_already_existing_name_in_another_case_returned_422(): void
    {
        $tag = Tag::factory()->for(
            $institution = Institution::factory()->create()
        )->create();

        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => Str::upper($tag->name), 'type' => $tag->type->value],
            ],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_storing_of_empty_tags_data_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertUnprocessable();
    }

    public function test_unauthenticated_storing_of_tags_returned_401(): void
    {
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => TagType::Order->value]],
        ], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    public function test_storing_of_tags_without_privilege_returned_403(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => TagType::Order->value]],
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::EditTag]))
            ->assertForbidden();
    }

    private function sendStoreRequestWithCustomHeaders(array $requestParams, array $headers): TestResponse
    {
        return $this->withHeaders($headers)->postJson(
            action([TagController::class, 'store']),
            $requestParams
        );
    }
}
