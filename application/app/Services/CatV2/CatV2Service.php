<?php

namespace App\Services\CatV2;

use Illuminate\Support\Facades\Http;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;


class CatV2Service {

    private $baseUrl;
    private $timeout;
    private $connectionTimeout;

    public function __construct(private readonly ServiceAccountJwtRetrieverInterface $jwtRetriever) {
        $this->baseUrl = config('catv2.base_url');
        $this->timeout = config('catv2.timeout', 30);
        $this->connectionTimeout = config('catv2.connection_timeout', 30);
    }

    public function getTranslationMemories($params = []) {
        return static::client()
            ->get('/api/translation-memories?' . http_build_query($params))
            ->throw()
            ->json();
    }    

    public function createTranslationMemory($data) {
        return static::client()
            ->post("/api/translation-memories", $data)
            ->json();
    }

    public function getTranslationMemory(string $translationMemoryId) {
        return static::client()
            ->get("/api/translation-memories/$translationMemoryId")
            ->json();
    }    

    public function updateTranslationMemory(string $translationMemoryId, $data) {
        return static::client()
            ->put("/api/translation-memories/$translationMemoryId", $data)
            ->json();
    }    

    public function deleteTranslationMemory(string $translationMemoryId) {
        return static::client()
            ->delete("/api/translation-memories/$translationMemoryId")
            ->json();
    }

    public function importTranslationMemory($data) {
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

    public function exportTranslationMemory($data) {
        return static::client()
            ->post("/api/translation-memories/export", $data);
    }

    public function getTranslationMemoryContentChecks() {
        return [];
    }

    private function client() {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->connectTimeout($this->connectionTimeout)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->jwtRetriever->getJwt());
    }
}