<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Note;
use App\Model\NoteSummary;
use App\Model\PagedResult;
use PDO;

/**
 * Acceso a datos de notas. Toda operación está acotada al dueño (ownerKeyId,
 * el key_id autenticado): un cliente nunca ve ni modifica notas de otro.
 *
 * Usa siempre prepared statements: los valores del cliente van como parámetros,
 * nunca concatenados en el SQL (defensa contra inyección).
 */
final class NoteRepository
{
    public function __construct(private readonly PDO $pdo) {}

    /**
     * Página de notas del cliente (id, título y fechas; el contenido se obtiene
     * por id). Paginación por keyset: se piden pageSize+1 filas para saber si
     * hay página siguiente. Con $search se filtra por texto completo.
     *
     * @return PagedResult<NoteSummary>
     */
    public function getPage(string $ownerKeyId, int $afterId, int $pageSize, ?string $search): PagedResult
    {
        $hasSearch = $search !== null && trim($search) !== '';

        $filter = 'owner_key_id = :ownerKeyId';
        $params = ['ownerKeyId' => $ownerKeyId];
        if ($hasSearch) {
            $filter .= ' AND MATCH(title, text) AGAINST(:search IN NATURAL LANGUAGE MODE)';
            $params['search'] = $search;
        }

        // Con prepares nativos (ATTR_EMULATE_PREPARES=false) LIMIT admite
        // placeholders, siempre que se enlacen como enteros.
        $stmt = $this->pdo->prepare(
            "SELECT id, title, created_at, updated_at
             FROM notes
             WHERE {$filter} AND id > :afterId
             ORDER BY id
             LIMIT :limit",
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('afterId', max(0, $afterId), PDO::PARAM_INT);
        $stmt->bindValue('limit', $pageSize + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > $pageSize;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(static fn(array $row) => NoteSummary::fromRow($row), $rows);
        $nextAfterId = $hasMore && $items !== [] ? end($items)->id : null;

        return new PagedResult($items, $pageSize, $nextAfterId);
    }

    public function getById(string $ownerKeyId, int $id): ?Note
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, text, created_at, updated_at
             FROM notes
             WHERE id = :id AND owner_key_id = :ownerKeyId',
        );
        $stmt->execute(['id' => $id, 'ownerKeyId' => $ownerKeyId]);
        $row = $stmt->fetch();

        return $row === false ? null : Note::fromRow($row);
    }

    public function create(string $ownerKeyId, string $title, ?string $text): Note
    {
        // Inserta y relee la fila (mismo cliente, misma conexión) para devolver
        // los timestamps generados por la BD.
        $stmt = $this->pdo->prepare(
            'INSERT INTO notes (owner_key_id, title, text) VALUES (:ownerKeyId, :title, :text)',
        );
        $stmt->execute(['ownerKeyId' => $ownerKeyId, 'title' => $title, 'text' => $text]);

        $note = $this->getById($ownerKeyId, (int) $this->pdo->lastInsertId());
        if ($note === null) {
            throw new \RuntimeException('No se pudo releer la nota recién creada.');
        }

        return $note;
    }

    public function update(string $ownerKeyId, int $id, string $title, ?string $text): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notes SET title = :title, text = :text
             WHERE id = :id AND owner_key_id = :ownerKeyId',
        );
        $stmt->execute([
            'title' => $title,
            'text' => $text,
            'id' => $id,
            'ownerKeyId' => $ownerKeyId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(string $ownerKeyId, int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM notes WHERE id = :id AND owner_key_id = :ownerKeyId',
        );
        $stmt->execute(['id' => $id, 'ownerKeyId' => $ownerKeyId]);

        return $stmt->rowCount() > 0;
    }
}
