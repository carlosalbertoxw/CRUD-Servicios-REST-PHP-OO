<?php

declare(strict_types=1);

namespace Tests;

final class MultiTenantTest extends IntegrationTestCase
{
    public function testUnClienteNoVeLasNotasDeOtro(): void
    {
        // Cliente B empieza sin notas (las de ejemplo son de A).
        $listB = $this->decode($this->send('GET', '/api/notes', ['X-Api-Key' => TestDatabase::KEY_B]));
        self::assertSame([], $listB['items']);

        // B crea la suya.
        $created = $this->decode($this->send(
            'POST',
            '/api/notes',
            ['X-Api-Key' => TestDatabase::KEY_B],
            ['title' => 'Privada de B'],
        ));

        // A no puede verla por id.
        $response = $this->sendAsA('GET', '/api/notes/' . $created['id']);
        self::assertSame(404, $response->status);
    }

    public function testUnClienteNoPuedeModificarNotasDeOtro(): void
    {
        // A crea una nota; B intenta actualizarla y eliminarla.
        $created = $this->decode($this->sendAsA('POST', '/api/notes', ['title' => 'De A']));

        $update = $this->send('PUT', '/api/notes/' . $created['id'], ['X-Api-Key' => TestDatabase::KEY_B], ['title' => 'Hackeada']);
        self::assertSame(404, $update->status);

        $delete = $this->send('DELETE', '/api/notes/' . $created['id'], ['X-Api-Key' => TestDatabase::KEY_B]);
        self::assertSame(404, $delete->status);
    }
}
