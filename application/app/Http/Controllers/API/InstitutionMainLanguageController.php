<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\SyncMainLanguagesRequest;
use App\Http\Resources\API\InstitutionMainLanguageResource;
use App\Models\CachedEntities\Institution;
use App\Models\InstitutionMainLanguage;
use App\Policies\InstitutionMainLanguagePolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Throwable;

class InstitutionMainLanguageController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/institutions/main-languages',
        summary: 'List institution main languages',
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionMainLanguageResource::class, description: 'Institution main languages')]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', InstitutionMainLanguage::class);

        return InstitutionMainLanguageResource::collection(
            InstitutionMainLanguage::withGlobalScope('policy', InstitutionMainLanguagePolicy::scope())
                ->with('language')
                ->get()
                ->sortBy(fn(InstitutionMainLanguage $row) => $row->language?->name)
                ->values()
        );
    }

    /**
     * @throws AuthorizationException|Throwable
     */
    #[OA\Post(
        path: '/institutions/main-languages',
        summary: 'Sync institution main languages',
        requestBody: new OAH\RequestBody(SyncMainLanguagesRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: InstitutionMainLanguageResource::class, description: 'Updated institution main languages')]
    public function sync(SyncMainLanguagesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('sync', InstitutionMainLanguage::class);

        $institution = Institution::findOrFail(Auth::user()->institutionId);

        return DB::transaction(function () use ($request, $institution): AnonymousResourceCollection {
            $survivingLanguageIds = collect($request->validated('languages', []))
                ->map(function (string $languageId) use ($institution): string {
                    InstitutionMainLanguage::firstOrNew([
                        'institution_id' => $institution->id,
                        'language_id' => $languageId,
                    ])->saveOrFail();

                    return $languageId;
                });

            InstitutionMainLanguage::withGlobalScope('policy', InstitutionMainLanguagePolicy::scope())
                ->whereNotIn('language_id', $survivingLanguageIds)
                ->delete();

            return InstitutionMainLanguageResource::collection(
                InstitutionMainLanguage::withGlobalScope('policy', InstitutionMainLanguagePolicy::scope())
                    ->with('language')
                    ->get()
                    ->sortBy(fn(InstitutionMainLanguage $row) => $row->language?->name)
                    ->values()
            );
        });
    }
}
