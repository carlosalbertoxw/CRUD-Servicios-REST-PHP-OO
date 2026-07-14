<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;

/**
 * Documentación de la API: sirve la especificación OpenAPI y una UI interactiva
 * (Scalar) para explorarla. Ambos endpoints son anónimos.
 */
final class DocsController
{
    public function __construct(private readonly string $basePath)
    {
    }

    /** GET /openapi.json — especificación OpenAPI 3.1. */
    public function spec(Request $request, array $params, ?string $ownerKeyId): Response
    {
        $json = file_get_contents($this->basePath . '/resources/openapi.json');

        return Response::raw(
            $json === false ? '{}' : $json,
            200,
            ['Content-Type' => 'application/json;charset=utf-8'],
        );
    }

    /** GET /docs — UI interactiva basada en Scalar (carga la spec de /openapi.json). */
    public function ui(Request $request, array $params, ?string $ownerKeyId): Response
    {
        $html = <<<'HTML'
            <!doctype html>
            <html>
              <head>
                <title>API de Notas — Documentación</title>
                <meta charset="utf-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1" />
              </head>
              <body>
                <script id="api-reference" data-url="/openapi.json"></script>
                <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
              </body>
            </html>
            HTML;

        return Response::raw($html, 200, ['Content-Type' => 'text/html;charset=utf-8']);
    }
}
