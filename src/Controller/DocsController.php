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
    public function __construct(private readonly string $basePath) {}

    /**
     * GET /openapi.json — especificación OpenAPI 3.1.
     *
     * @param array<string,string> $params parámetros de la ruta
     */
    public function spec(Request $request, array $params, ?string $ownerKeyId): Response
    {
        $json = file_get_contents($this->basePath . '/resources/openapi.json');

        return Response::raw(
            $json === false ? '{}' : $json,
            200,
            ['Content-Type' => 'application/json;charset=utf-8'],
        );
    }

    /**
     * GET /docs — UI interactiva basada en Scalar (carga la spec de /openapi.json).
     *
     * El script se sirve con versión fija e integridad (SRI): el navegador lo
     * rechaza si el CDN entrega otro contenido. Al actualizar la versión hay que
     * recalcular el hash (ver README).
     *
     * @param array<string,string> $params parámetros de la ruta
     */
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
                <script
                  src="https://cdn.jsdelivr.net/npm/@scalar/api-reference@1.77.0/dist/browser/standalone.js"
                  integrity="sha384-+7ESN8b9Ljg46TCYKedZUlhF/wfZbOt78aV/DtoU3anEElh8Q2eXsyhQ9W5lAZD4"
                  crossorigin="anonymous"></script>
              </body>
            </html>
            HTML;

        return Response::raw($html, 200, ['Content-Type' => 'text/html;charset=utf-8']);
    }
}
