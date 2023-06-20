<?php

namespace tests\Feature\Models;

use App\Models\Cached\InstitutionUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstitutionUserTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_institution_users_have_readonly_access(): void
    {
        $id = InstitutionUser::factory()->create()->id;

        $this->expectException(QueryException::class);
        $institution = InstitutionUser::query()->find($id)->first();
        $institution->synced_at = Carbon::now();
        $institution->save();
    }
}
