<?php

declare(strict_types=1);

namespace Tests;

final class AuthTest extends IntegrationTestCase
{
    public function testSinApiKeyDevuelve401(): void
    {
        $response = $this->send('GET', '/api/notes');

        self::assertSame(401, $response->status);
        self::assertSame('ApiKey', $response->headers['WWW-Authenticate']);
    }

    public function testApiKeyInvalidaDevuelve401(): void
    {
        $response = $this->send('GET', '/api/notes', ['X-Api-Key' => 'local-dev.mal']);

        self::assertSame(401, $response->status);
    }

    public function testApiKeyMalFormadaDevuelve401(): void
    {
        $response = $this->send('GET', '/api/notes', ['X-Api-Key' => 'sinpunto']);

        self::assertSame(401, $response->status);
    }

    public function testApiKeyValidaDevuelve200(): void
    {
        $response = $this->sendAsA('GET', '/api/notes');

        self::assertSame(200, $response->status);
    }
}
