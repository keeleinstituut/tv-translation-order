<?php

namespace tests\Feature\Models;

use App\Models\CachedEntities\InstitutionUser;
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
        $this->markTestSkipped('readonly access is not needed anymore');

        $id = InstitutionUser::factory()->create()->id;

        $this->expectException(QueryException::class);
        $institutionUser = InstitutionUser::query()->find($id)->first();
        $institutionUser->setConnection(config('database.default'));
        $institutionUser->synced_at = Carbon::now();
        $institutionUser->save();
    }
}
