<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\CatV2TranslationMemoryResource;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Services\CatV2\CatV2Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CatV2Controller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function translationMemoryIndex()
    {
        $response = CatV2Service::getTranslationMemories([
            'meta' => [
                'institution_id' => Auth::user()->institutionId,
            ],
        ]);

        return CatV2TranslationMemoryResource::collection($response['data']);
    }

    public function translationMemoryStore(Request $request)
    {
        $params = collect($request->all());

        $locales = Str::of($params->get('lang_pair'))->explode('_');

        $response = CatV2Service::createTranslationMemory([
            'name' => $params->get('name'),
            'source_locale' => $locales[0],
            'target_locale' => $locales[1],
            'meta' => [
                'visibility' => $params->get('type'),
                'institution_id' => Auth::user()->institutionId,
                'tv_domain' => $params->get('tv_domain'),
                'tv_tags' => $params->get('tv_tags'),
            ],
        ]);

        return CatV2TranslationMemoryResource::make($response['data']);
    }

    public function translationMemoryShow($id)
    {
        $response = CatV2Service::getTranslationMemory($id);

        return CatV2TranslationMemoryResource::make($response['data'])
            ->additional([
                'segment_count' => $response['segment_count'],
            ]);
    }

    public function translationMemoryUpdate(Request $request, $id)
    {
        $params = collect($request->all());

        $payload = collect([
            'name' => $params->get('name'),
            'source_locale' => $params->get('source_locale'),
            'target_locale' => $params->get('target_locale'),
            'meta' => collect([
                'visibility' => $params->get('type'),
                'tv_comment' => $params->get('comment'),
                // 'institution_id' => Auth::user()->institutionId,
                'tv_domain' => $params->get('tv_domain'),
                'tv_tags' => $params->get('tv_tags'),
            ])->filter()->toArray(),
        ])->filter()->toArray();

        $response = CatV2Service::updateTranslationMemory($id, $payload);

        return CatV2TranslationMemoryResource::make($response['data']);
    }

    public function translationMemoryImport(Request $request)
    {
        $params = collect($request->all());

        return CatV2Service::importTranslationMemory([
            'translation_memory_id' => $params->get('tag'),
            'files' => [
                $params->get('file'),
            ]
        ]);
    }

    public function translationMemoryContentCheckIndex()
    {
        return [
            'data' => [],
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
