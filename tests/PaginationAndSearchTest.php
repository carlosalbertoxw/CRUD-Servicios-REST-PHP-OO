<?php

declare(strict_types=1);

namespace Tests;

final class PaginationAndSearchTest extends IntegrationTestCase
{
    public function testPaginacionKeysetDevuelveNextAfterId(): void
    {
        $page1 = $this->decode($this->sendAsA('GET', '/api/notes', null, ['pageSize' => '1']));

        self::assertCount(1, $page1['items']);
        self::assertSame(2, $page1['totalCount']);
        self::assertNotNull($page1['nextAfterId']);

        $page2 = $this->decode($this->sendAsA('GET', '/api/notes', null, [
            'pageSize' => '1',
            'afterId' => (string) $page1['nextAfterId'],
        ]));

        self::assertCount(1, $page2['items']);
        self::assertNull($page2['nextAfterId']);
        self::assertNotSame($page1['items'][0]['id'], $page2['items'][0]['id']);
    }

    public function testBusquedaFullTextFiltra(): void
    {
        $this->sendAsA('POST', '/api/notes', ['title' => 'Reunión importante', 'text' => 'hablar de presupuesto']);

        $result = $this->decode($this->sendAsA('GET', '/api/notes', null, ['search' => 'presupuesto']));

        self::assertSame(1, $result['totalCount']);
        self::assertStringContainsString('Reunión', $result['items'][0]['title']);
    }

    public function testBusquedaSinCoincidenciasDevuelveVacio(): void
    {
        $result = $this->decode($this->sendAsA('GET', '/api/notes', null, ['search' => 'zzzznoexiste']));

        self::assertSame(0, $result['totalCount']);
        self::assertSame([], $result['items']);
    }
}
