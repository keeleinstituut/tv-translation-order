<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Skill;
use Tests\AuthHelpers;
use Tests\Feature\RepresentationHelpers;
use Tests\TestCase;

class SkillControllerTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_list(): void
    {
        // GIVEN
        $existingSkills = Skill::all();

        $accessToken = AuthHelpers::generateAccessToken();

        // WHEN
        $response = $this->prepareAuthorizedRequest($accessToken)->getJson('/api/skills');

        // THEN
        $response
            ->assertStatus(200)
            ->assertJson([
                'data' => collect($existingSkills)->map(fn ($obj) => $this->constructRepresentation($obj))->toArray(),
            ])
            ->assertJsonCount($existingSkills->count(), 'data');
    }

    public static function constructRepresentation($obj): array
    {
        return RepresentationHelpers::createSkillRepresentation($obj);
    }
}
