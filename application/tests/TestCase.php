<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use SyncTools\Traits\RefreshDatabaseWithCachedEntitySchema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabaseWithCachedEntitySchema;

    protected function setUp(): void
    {
        parent::setUp();
        AuthHelpers::fakeServiceValidationResponse();
    }

    protected function prepareAuthorizedRequest($accessToken): TestCase
    {
        return $this->withHeader('Authorization', 'Bearer '.$accessToken);
    }

    public function assertArrayHasSubsetIgnoringOrder(?array $expectedSubset, ?array $actual): void
    {
        Assertions::assertArrayHasSubsetIgnoringOrder($expectedSubset, $actual);
    }

    public function assertArraysEqualIgnoringOrder(?array $expected, ?array $actual): void
    {
        Assertions::assertArraysEqualIgnoringOrder($expected, $actual);
    }
}
