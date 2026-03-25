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
    public function translationMemoryIndex(Request $request)
    {

        $params = collect($request->validate([
            'type' => 'array',
            'type.*' => 'in:private,shared,public',
            'tv_tags' => 'array',
            'tv_tags.*' => 'uuid',
            'lang_pair' => 'array',
            'lang_pair.*' => 'string',
            'tv_domain' => 'array',
            'tv_domain.*' => 'uuid',
            'name' => 'string',
        ]));

        $typeParam = collect($params->get('type', ['private', 'shared', 'public']));

        $typeFilters = collect();

        if ($typeParam->contains('private')) {
            $typeFilters->push([
                'and' => [
                    0 => [
                        'meta.institution_id' => Auth::user()->institutionId,
                    ],
                    1 => [
                        'meta.visibility' => 'private',
                    ],
                ],
            ]);
        }

        if ($typeParam->contains('shared')) {
            $typeFilters->push([
                'meta.visibility' => 'shared',
            ]);
        }

        if ($typeParam->contains('public')) {
            $typeFilters->push([
                'meta.visibility' => 'public',
            ]);
        }

        $filter = [
            'and' => [
                0 => [
                    'or' => [
                        ...$typeFilters->reduce(function ($acc, $v, $i) {
                            $acc[$i] = $v;
                            return $acc;
                        }, [])
                    ]
                ],
                1 => [
                    'or' => collect($params->get('tv_tags'))
                                ->reduce(function ($acc, $tagId, $i) {
                                    $acc[$i] = [
                                        'meta.tv_tags' => $tagId,
                                    ];
                                }, [])
                ],
                2 => [
                    'or' => collect($params->get('lang_pair'))
                                ->reduce(function ($acc, $pair, $i) {
                                    $parts = Str::of($pair)->explode('_');
                                    $source = $parts[0];
                                    $target = $parts[1];

                                    $acc[$i] = [
                                        'and' => [
                                            0 => [
                                                'source_locale' => $source,
                                            ],
                                            1 => [
                                                'target_locale' => $target,
                                            ],
                                        ]
                                    ];
                                    return $acc;
                                }, []),
                ],
                3 => [
                    'or' => collect($params->get('tv_domain'))
                                ->reduce(function ($acc, $domain, $i) {
                                    $acc[$i] = [
                                        'meta.tv_domain' => $domain,
                                    ];
                                    return $acc;
                                }, []),
                ],
                4 => tap(collect(), function ($acc) use ($params) {
                    if ($name = $params->get('name')) {
                        $acc['name'] = $name;
                    }
                    return $acc;
                })->toArray()
            ]
        ];

        $response = CatV2Service::getTranslationMemories([
            'filter' => $filter,
        ]);

        return CatV2TranslationMemoryResource::collection($response['data'])
            ->additional([
                'segment_counts' => data_get($response, 'segment_counts'),
            ]);
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

    public function translationMemoryExport(Request $request)
    {
        $params = collect($request->all());
        $tags = $params->get('tag');
        $combined = collect($tags)->count() > 1;

        $response = CatV2Service::exportTranslationMemory([
            'translation_memory_ids' => $tags,
            'combined' => $combined,
        ]);

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type'),
            'Content-Disposition' => $response->header('Content-Disposition'),
            'Content-Length' => $response->header('Content-Length'),
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
