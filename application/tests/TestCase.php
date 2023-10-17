<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Arr;
use SyncTools\Traits\RefreshDatabaseWithCachedEntitySchema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabaseWithCachedEntitySchema;

    protected function prepareAuthorizedRequest($accessToken): TestCase
    {
        return $this->withHeader('Authorization', 'Bearer '.$accessToken);
    }

    public function assertArrayHasSubsetIgnoringOrder(?array $expectedSubset, ?array $actual): void
    {
        $this->assertNotNull($expectedSubset);
        $this->assertNotNull($actual);

        $sortedDottedExpectedSubset = Arr::dot(Arr::sortRecursive($expectedSubset));
        $sortedDottedActualWholeArray = Arr::dot(Arr::sortRecursive($actual));
        $sortedDottedActualSubset = Arr::only($sortedDottedActualWholeArray, array_keys($sortedDottedExpectedSubset));

        $this->assertArraysEqualIgnoringOrder($sortedDottedExpectedSubset, $sortedDottedActualSubset);
    }

    public function assertArraysEqualIgnoringOrder(?array $expected, ?array $actual): void
    {
        $this->assertNotNull($expected);
        $this->assertNotNull($actual);

        $this->assertEquals(
            Arr::sortRecursive($expected),
            Arr::sortRecursive($actual)
        );
    }
}
