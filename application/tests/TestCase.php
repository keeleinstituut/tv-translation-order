<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function beforeRefreshingDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            Artisan::call('db-schema:setup');
            Artisan::call('db:wipe', ['--database' => config('pgsql-connection.sync.name')]);
        }
    }
}
