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
