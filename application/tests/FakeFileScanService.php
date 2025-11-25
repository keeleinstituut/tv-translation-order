<?php

namespace Tests;

use App\Services\FileScanService;
use Mockery;

/**
 * Fake FileScanService for testing.
 * Mocks the FileScanService static methods to always return safe (non-infected) results without calling external API.
 */
class FakeFileScanService
{
    public static function bind(): void
    {
        $mock = Mockery::mock('alias:' . FileScanService::class);

        $mock->shouldReceive('scanFiles')
            ->andReturnUsing(function (array $files): array {
                return collect($files)->map(function () {
                    return ['is_infected' => false];
                })->toArray();
            });
    }
}

