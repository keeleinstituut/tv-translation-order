<?php

namespace App\Http\Controllers;

use App\Http\OpenApiHelpers as OAH;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Attributes as OA;

#[OA\OpenApi(security: [['WebClientBearerJwt' => []]])]
#[OAH\ApiServer]
#[OA\Info(
    version: '0.0.1',
    title: 'Tõlkevärav Translation Order Service API',
    contact: new OA\Contact(url: 'https://github.com/keeleinstituut/tv-translation-order')
)]
#[OA\ExternalDocumentation(url: 'https://github.com/keeleinstituut/tv-tolkevarav/wiki')]
#[OA\SecurityScheme(
    securityScheme: 'WebClientBearerJwt',
    type: 'http',
    description: 'Bearer JWT signed by Tõlkevärav’s SSO for the web client',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
}
