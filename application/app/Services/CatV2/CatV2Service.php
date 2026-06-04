<?php

namespace App\Services\CatV2;

use Illuminate\Support\Facades\Http;


class CatV2Service {
    public static function getTranslationMemories($params = []) {
        return static::client()
            ->get('/api/translation-memories?' . http_build_query($params))
            ->json();
    }    

    public static function createTranslationMemory($data) {
        return static::client()
            ->post("/api/translation-memories", $data)
            ->json();
    }

    public static function getTranslationMemory(string $translationMemoryId) {
        return static::client()
            ->get("/api/translation-memories/$translationMemoryId")
            ->json();
    }    

    public static function updateTranslationMemory(string $translationMemoryId, $data) {
        return static::client()
            ->put("/api/translation-memories/$translationMemoryId", $data)
            ->json();
    }    

    public static function deleteTranslationMemory(string $translationMemoryId) {
        return static::client()
            ->delete("/api/translation-memories/$translationMemoryId")
            ->json();
    }

    public static function importTranslationMemory($data) {
        $request = static::client()->asMultipart();

        collect($data['files'])
            ->each(function ($file) use ($request) {
                $request->attach('files[]', $file->get(), $file->getClientOriginalName(), [
                    'Content-Type' => $file->getClientMimeType(),
                ]);
            });

        return $request
            ->post("/api/translation-memories/import", [
                'translation_memory_id' => $data['translation_memory_id'],
            ])
            ->throw()
            ->json();
    }

    public static function exportTranslationMemory($data) {
        return static::client()
            ->post("/api/translation-memories/export", $data);
    }

    public static function getTranslationMemoryContentChecks() {
        return [];
    }

    private static function client() {
        $baseUrl = config('catv2.base_url');
        $timeout = config('catv2.timeout', 30);
        $connectionTimeout = config('catv2.connection_timeout', 30);

        return Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->connectTimeout($connectionTimeout)
            ->withHeader('Accept', 'application/json');
    }
}