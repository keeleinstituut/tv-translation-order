<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use SyncTools\Traits\RefreshDatabaseWithCachedEntitySchema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabaseWithCachedEntitySchema;


    protected function prepareAuthorizedRequest($accessToken): TestCase
    {
        return $this->withHeader('Authorization', 'Bearer ' . $accessToken);
    }
}
