<?php

declare(strict_types=1);

namespace Tests;

use App\Bootstrap\App;
use App\Http\Request;

/**
 * Comportamiento con APP_ENV=production: solo HTTPS (con HSTS) salvo los
 * health checks, y confianza en X-Forwarded-Proto solo desde proxies declarados.
 */
final class ProductionModeTest extends IntegrationTestCase
{
    private const PROXY = '10.0.0.5';

    protected function setUp(): void
    {
        parent::setUp();
        self::setEnv('APP_ENV', 'production');
        self::setEnv('TRUSTED_PROXIES', self::PROXY);
        self::setEnv('ALLOWED_HOSTS', 'otro.example.com, api.example.com');
        $this->app = new App(dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        self::setEnv('APP_ENV', 'testing');
        self::setEnv('TRUSTED_PROXIES', '');
        self::setEnv('ALLOWED_HOSTS', '');
        parent::tearDown();
    }

    public function testHttpSeRedirigeAHttpsConservandoRutaYQuery(): void
    {
        $response = $this->app->handle(Request::create(
            'GET',
            '/api/notes',
            ['pageSize' => '5'],
            ['Host' => 'api.example.com:8081', 'X-Api-Key' => TestDatabase::KEY_A],
        ));

        self::assertSame(308, $response->status);
        self::assertSame('https://api.example.com/api/notes?pageSize=5', $response->headers['Location']);
    }

    public function testHostFueraDeLaListaRecibe400SinRedirigir(): void
    {
        $response = $this->app->handle(Request::create('GET', '/api/notes', [], ['Host' => 'evil.example']));

        self::assertSame(400, $response->status);
        self::assertArrayNotHasKey('Location', $response->headers);
    }

    public function testElHostDeLaRedireccionSaleDeLaConfiguracion(): void
    {
        $response = $this->app->handle(Request::create('GET', '/docs', [], ['Host' => 'API.Example.COM']));

        self::assertSame(308, $response->status);
        self::assertSame('https://api.example.com/docs', $response->headers['Location']);
    }

    public function testHealthChecksNoSeRedirigen(): void
    {
        self::assertSame(200, $this->app->handle(Request::create('GET', '/health/live'))->status);
    }

    public function testHttpsDesdeProxyDeConfianzaSeAtiendeConHsts(): void
    {
        $response = $this->app->handle(Request::create(
            'GET',
            '/api/notes',
            [],
            ['X-Forwarded-Proto' => 'https', 'X-Api-Key' => TestDatabase::KEY_A],
            '',
            self::PROXY,
        ));

        self::assertSame(200, $response->status);
        self::assertStringStartsWith('max-age=', $response->headers['Strict-Transport-Security']);
    }

    public function testXForwardedProtoDeUnClienteCualquieraSeIgnora(): void
    {
        $response = $this->app->handle(Request::create(
            'GET',
            '/api/notes',
            [],
            ['Host' => 'api.example.com', 'X-Forwarded-Proto' => 'https', 'X-Api-Key' => TestDatabase::KEY_A],
            '',
            '203.0.113.7',
        ));

        self::assertSame(308, $response->status);
    }

    private static function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
}
