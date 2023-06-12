<?php

namespace App\Http\Controllers\API;

use App\Enums\Feature;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RedirectController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $params = $request->all();
        return $this->processByFeature($params);
    }

    public function processByFeature(array $params) {
        $feature = $params['feature'];

        return match ($feature) {
            Feature::JOB_TRANSLATION->value => $this->processTranslationFeature($params),
            Feature::JOB_REVISION->value => $this->processRevisionFeature($params),
            default => throw new \Exception("Unprocessable redirect", 1),
        };
    }

    public function processTranslationFeature($params)
    {
        return [
            'href' => 'translation'
        ];
    }

    public function processRevisionFeature($params)
    {
        return [
            'href' => 'revision'
        ];
    }
}
