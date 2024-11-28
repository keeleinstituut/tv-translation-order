<?php

namespace Tests;

use Illuminate\Support\Arr;
use Illuminate\Testing\Assert;

class Assertions
{
    public static function assertArrayHasSubsetIgnoringOrder(?array $expectedSubset, ?array $actual): void
    {
        Assert::assertNotNull($expectedSubset);
        Assert::assertNotNull($actual);

        $sortedDottedExpectedSubset = Arr::dot(Arr::sortRecursive($expectedSubset));
        $sortedDottedActualWholeArray = Arr::dot(Arr::sortRecursive($actual));
        $sortedDottedActualSubset = Arr::only($sortedDottedActualWholeArray, array_keys($sortedDottedExpectedSubset));

        static::assertArraysEqualIgnoringOrder($sortedDottedExpectedSubset, $sortedDottedActualSubset);
    }

    public static function assertArraysEqualIgnoringOrder(?array $expected, ?array $actual): void
    {
        Assert::assertNotNull($expected);
        Assert::assertNotNull($actual);

        Assert::assertEquals(
            Arr::sortRecursive($expected),
            Arr::sortRecursive($actual)
        );
    }
}
