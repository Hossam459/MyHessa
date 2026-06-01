<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Root OpenAPI metadata for L5 Swagger.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'MyHessa API',
    description: 'MyHessa - Education Management System API'
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: 'Local Development Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'JWT Token',
    bearerFormat: 'JWT',
    scheme: 'bearer'
)]
class OpenApi
{
}
