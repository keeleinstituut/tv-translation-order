<?php

namespace App\Services\TranslationMemories;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use KeycloakAuthGuard\Services\ServiceAccountJwtRetrieverInterface;

class TvTranslationMemoryApiClient
{
    private string $baseUrl;

    public function __construct(private readonly ServiceAccountJwtRetrieverInterface $jwtRetriever)
    {
        $this->baseUrl = rtrim(config('services.nectm.base_url'), '/');
    }

    /**
     * @param array $params
     * @return array
     * @throws RequestException
     */
    public function createTag(array $params): array
    {
        return $this->getBaseRequest()->post('/tags', collect($params)->only([
            'institution_id',
            'name',
            'type',
            'lang_pair'
        ]))->throw()->json();
    }

    private function getBaseRequest(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->jwtRetriever->getJwt(),
        ])->baseUrl($this->baseUrl);
    }

    public static function getLanguagePair(ClassifierValue $source, ClassifierValue $destination): string
    {
        if ($source->type !== ClassifierValueType::Language || $destination->type !== ClassifierValueType::Language) {
            throw new InvalidArgumentException('Wrong classifier value passed for building of the language pair');
        }

        return implode('_', [
            self::convertLanguageCode($source->value),
            self::convertLanguageCode($destination->value)
        ]);
    }

    private static function convertLanguageCode(string $language): string
    {
        return explode('-', $language)[0];
    }
}
