<?php

declare(strict_types=1);

namespace Tests;

use App\Bootstrap\App;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Base para los tests de integración: reinicia la BD a un estado conocido antes
 * de cada test y ofrece un helper para ejercitar la API vía App::handle().
 */
abstract class IntegrationTestCase extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->app = new App(dirname(__DIR__));
    }

    /**
     * Envía una petición a la aplicación y devuelve la respuesta.
     *
     * @param array<string,string> $headers
     * @param array<string,string> $query
     */
    protected function send(
        string $method,
        string $path,
        array $headers = [],
        ?array $json = null,
        array $query = [],
    ): Response {
        $body = $json === null ? '' : (string) json_encode($json);

        return $this->app->handle(Request::create($method, $path, $query, $headers, $body));
    }

    /** Igual que send() pero autenticado como Cliente A. */
    protected function sendAsA(
        string $method,
        string $path,
        ?array $json = null,
        array $query = [],
    ): Response {
        return $this->send($method, $path, ['X-Api-Key' => TestDatabase::KEY_A], $json, $query);
    }

    /** @return array<string,mixed> */
    protected function decode(Response $response): array
    {
        return json_decode($response->body, true) ?? [];
    }
}
