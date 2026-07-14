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
    public function __construct(private readonly PDO $pdo)
    {
    }

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

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM notes WHERE {$filter}");
        $countStmt->execute($params);
        $totalCount = (int) $countStmt->fetchColumn();

        // afterId y limit son enteros ya validados: se interpolan de forma segura
        // (MySQL no admite placeholders en LIMIT con prepares nativos de forma
        // portable). Los valores del cliente siguen yendo como parámetros.
        $afterId = max(0, $afterId);
        $limit = $pageSize + 1;
        $sql = "SELECT id, title, created_at, updated_at
                FROM notes
                WHERE {$filter} AND id > :afterId
                ORDER BY id
                LIMIT {$limit}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params + ['afterId' => $afterId]);
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > $pageSize;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(static fn (array $row) => NoteSummary::fromRow($row), $rows);
        $nextAfterId = $hasMore && $items !== [] ? end($items)->id : null;

        return new PagedResult($items, $pageSize, $totalCount, $nextAfterId);
    }

    public function getById(string $ownerKeyId, int $id): ?Note
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, text, created_at, updated_at
             FROM notes
             WHERE id = :id AND owner_key_id = :ownerKeyId'
        );
        $stmt->execute(['id' => $id, 'ownerKeyId' => $ownerKeyId]);
        $row = $stmt->fetch();

        return $row === false ? null : Note::fromRow($row);
    }

    public function create(string $ownerKeyId, string $title, ?string $text): Note
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notes (owner_key_id, title, text) VALUES (:ownerKeyId, :title, :text)'
        );
        $stmt->execute(['ownerKeyId' => $ownerKeyId, 'title' => $title, 'text' => $text]);

        // Releer la fila para devolver los timestamps generados por la BD.
        return $this->getById($ownerKeyId, (int) $this->pdo->lastInsertId());
    }

    public function update(string $ownerKeyId, int $id, string $title, ?string $text): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notes SET title = :title, text = :text
             WHERE id = :id AND owner_key_id = :ownerKeyId'
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
            'DELETE FROM notes WHERE id = :id AND owner_key_id = :ownerKeyId'
        );
        $stmt->execute(['id' => $id, 'ownerKeyId' => $ownerKeyId]);

        return $stmt->rowCount() > 0;
    }
}
