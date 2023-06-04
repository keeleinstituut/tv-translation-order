<?php

namespace tests\Feature\Models;

use App\Models\Institution;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InstitutionTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_institutions_have_readonly_access()
    {
        $id = Institution::factory()->create()->id;

        $this->expectException(QueryException::class);
        $institution = Institution::query()->find($id)->first();
        $institution->synced_at = Carbon::now();
        $institution->save();
    }
}
