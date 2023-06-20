<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use SyncTools\Traits\RefreshDatabaseWithCachedEntitySchema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabaseWithCachedEntitySchema;
}
