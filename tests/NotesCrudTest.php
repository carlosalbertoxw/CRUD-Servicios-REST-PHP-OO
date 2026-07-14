<?php

declare(strict_types=1);

namespace Tests;

final class NotesCrudTest extends IntegrationTestCase
{
    public function testListaDevuelveLasNotasDelCliente(): void
    {
        $body = $this->decode($this->sendAsA('GET', '/api/notes'));

        self::assertSame(2, $body['totalCount']);
        self::assertCount(2, $body['items']);
        self::assertArrayHasKey('createdAt', $body['items'][0]);
        self::assertArrayNotHasKey('text', $body['items'][0]); // el resumen no trae contenido
    }

    public function testCrearDevuelve201ConLocationYTimestamps(): void
    {
        $response = $this->sendAsA('POST', '/api/notes', ['title' => 'Nueva', 'text' => 'Contenido']);

        self::assertSame(201, $response->status);
        self::assertArrayHasKey('Location', $response->headers);

        $note = $this->decode($response);
        self::assertSame('Nueva', $note['title']);
        self::assertNotEmpty($note['createdAt']);
        self::assertSame('/api/notes/' . $note['id'], $response->headers['Location']);
    }

    public function testObtenerPorIdDevuelveElContenido(): void
    {
        $created = $this->decode($this->sendAsA('POST', '/api/notes', ['title' => 'T', 'text' => 'cuerpo']));

        $note = $this->decode($this->sendAsA('GET', '/api/notes/' . $created['id']));

        self::assertSame('cuerpo', $note['text']);
    }

    public function testObtenerInexistenteDevuelve404(): void
    {
        self::assertSame(404, $this->sendAsA('GET', '/api/notes/99999')->status);
    }

    public function testActualizarDevuelve204YCambiaLaNota(): void
    {
        $created = $this->decode($this->sendAsA('POST', '/api/notes', ['title' => 'Antes', 'text' => 'x']));

        $update = $this->sendAsA('PUT', '/api/notes/' . $created['id'], ['title' => 'Después', 'text' => 'y']);
        self::assertSame(204, $update->status);

        $note = $this->decode($this->sendAsA('GET', '/api/notes/' . $created['id']));
        self::assertSame('Después', $note['title']);
        self::assertSame('y', $note['text']);
    }

    public function testActualizarInexistenteDevuelve404(): void
    {
        self::assertSame(404, $this->sendAsA('PUT', '/api/notes/99999', ['title' => 'X'])->status);
    }

    public function testEliminarDevuelve204YLuego404(): void
    {
        $created = $this->decode($this->sendAsA('POST', '/api/notes', ['title' => 'Borrar']));

        self::assertSame(204, $this->sendAsA('DELETE', '/api/notes/' . $created['id'])->status);
        self::assertSame(404, $this->sendAsA('DELETE', '/api/notes/' . $created['id'])->status);
    }

    public function testMetodoNoPermitidoDevuelve405ConAllow(): void
    {
        $response = $this->sendAsA('PATCH', '/api/notes/1');

        self::assertSame(405, $response->status);
        self::assertArrayHasKey('Allow', $response->headers);
    }
}
