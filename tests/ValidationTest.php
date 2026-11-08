<?php

declare(strict_types=1);

namespace Tests;

final class ValidationTest extends IntegrationTestCase
{
    public function testTituloVacioDevuelve400(): void
    {
        $response = $this->sendAsA('POST', '/api/notes', ['text' => 'sin titulo']);

        self::assertSame(400, $response->status);
        self::assertArrayHasKey('title', $this->decode($response)['errors']);
    }

    public function testTituloSoloConEspaciosDevuelve400(): void
    {
        $response = $this->sendAsA('POST', '/api/notes', ['title' => '   ']);

        self::assertSame(400, $response->status);
        self::assertArrayHasKey('title', $this->decode($response)['errors']);
    }

    public function testTituloQueNoEsCadenaDevuelve400(): void
    {
        $response = $this->sendAsA('POST', '/api/notes', ['title' => 123, 'text' => ['no', 'texto']]);

        self::assertSame(400, $response->status);
        $errors = $this->decode($response)['errors'];
        self::assertArrayHasKey('title', $errors);
        self::assertArrayHasKey('text', $errors);
    }

    public function testIdNoNumericoDevuelve404(): void
    {
        // Antes "/api/notes/1abc" se convertía a la nota 1.
        self::assertSame(404, $this->sendAsA('GET', '/api/notes/1abc')->status);
        self::assertSame(404, $this->sendAsA('GET', '/api/notes/abc')->status);
    }

    public function testTituloDemasiadoLargoDevuelve400(): void
    {
        $response = $this->sendAsA('POST', '/api/notes', ['title' => str_repeat('x', 251)]);

        self::assertSame(400, $response->status);
    }

    public function testCuerpoNoJsonDevuelve400(): void
    {
        $response = $this->send(
            'POST',
            '/api/notes',
            ['X-Api-Key' => TestDatabase::KEY_A],
            null,
        );

        self::assertSame(400, $response->status);
    }

    public function testPageSizeFueraDeRangoDevuelve400(): void
    {
        $response = $this->sendAsA('GET', '/api/notes', null, ['pageSize' => '999']);

        self::assertSame(400, $response->status);
    }

    public function testCuerpoDemasiadoGrandeDevuelve413(): void
    {
        // Supera MAX_BODY_BYTES (1 MB) por defecto.
        $big = ['title' => 'x', 'text' => str_repeat('a', 1_100_000)];
        $response = $this->sendAsA('POST', '/api/notes', $big);

        self::assertSame(413, $response->status);
    }
}
