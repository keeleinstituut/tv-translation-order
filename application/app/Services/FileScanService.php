<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class FileScanService
{

    public static function scanFiles(array $files) {
        $client = static::client();

        collect($files)->each(function (UploadedFile $file) use ($client) {
            $contents = fopen($file, 'r');
            $client->attach('FILES', $contents);
        });

        $json = $client->post("/v1/scan", [])->throw()->json();
        return $json['data']['result'];
    }

    private static function client(): PendingRequest
    {
        $baseUrl = config('filescan.base_url');
        $timeout = config('filescan.timeout', 30);
        $connectionTimeout = config('filescan.connection_timeout', 30);

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->connectTimeout($connectionTimeout);
    }
}
