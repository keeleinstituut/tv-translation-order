<?php

namespace tests\Feature\Http;

use App\Enums\PrivilegeKey;
use App\Enums\TagType;
use App\Http\Controllers\TagController;
use App\Models\Institution;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\AuthHelpers;
use Tests\TestCase;

class TagControllerStoreTest extends TestCase
{
    public function test_storing_of_tags_returned_200(): void
    {
        $institution = Institution::factory()->create();
        $tagsAttributes = Tag::factory(10)->notVendorSkills()->make()
            ->map(fn(Tag $tag) => Arr::only($tag->getAttributes(), ['name', 'type']));

        $response = $this->sendStoreRequestWithCustomHeaders([
            'tags' => $tagsAttributes->map(fn(array $tagAttributes) => [
                'name' => $tagAttributes['name'],
                'type' => $tagAttributes['type']
            ])->toArray()
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]));

        $response->assertStatus(Response::HTTP_OK);

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
            'data' => $tags->map(fn(Tag $tag) => $this->createTagRepresentation($tag))
                ->toArray()
        ]);
    }

    public function test_storing_of_vendor_skill_tags_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => TagType::VendorSkill->value]]
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_storing_of_tags_with_empty_name_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => '', 'type' => TagType::TranslationMemory->value]]
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_storing_of_tags_with_empty_type_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => '']]
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_storing_of_tags_with_incorrect_type_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => 'some type']]
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_storing_of_tag_with_already_existing_name_returned_422(): void
    {
        $tag = Tag::factory()->for(
            $institution = Institution::factory()->create()
        )->create();

        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [
                ['name' => $tag->name, 'type' => $tag->type->value]
            ]
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_storing_of_empty_tags_data_returned_422(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => []
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::AddTag]))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function test_unauthorized_storing_of_tags_returned_401(): void
    {
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => TagType::Order->value]]
        ], ['Accept' => 'application/json'])
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_storing_of_tags_without_privilege_returned_403(): void
    {
        $institution = Institution::factory()->create();
        $this->sendStoreRequestWithCustomHeaders([
            'tags' => [['name' => 'Some name', 'type' => TagType::Order->value]]
        ], AuthHelpers::createJsonHeaderWithTokenParams($institution->id, [PrivilegeKey::EditTag]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }


    private function sendStoreRequestWithCustomHeaders(array $requestParams, array $headers): TestResponse
    {
        return $this->withHeaders($headers)->postJson(
            action([TagController::class, 'store']),
            $requestParams
        );
    }

    private function createTagRepresentation(Tag $tag): array
    {
        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'institution_id' => $tag->institution_id,
            'type' => $tag->type->value,
            'created_at' => $tag->created_at->toIsoString(),
            'updated_at' => $tag->updated_at->toIsoString(),
        ];
    }
}
