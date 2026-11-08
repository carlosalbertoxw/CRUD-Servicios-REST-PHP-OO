<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/**
 * Página de resultados con paginación por keyset: para la siguiente página se
 * envía ?afterId={nextAfterId}. Costo constante en la BD sin importar la
 * profundidad de la página (a diferencia de LIMIT/OFFSET). No incluye un total:
 * contarlo exigiría recorrer todas las notas del cliente en cada página.
 *
 * @template T of JsonSerializable
 */
final class PagedResult implements JsonSerializable
{
    /** @param list<T> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $pageSize,
        public readonly ?int $nextAfterId,
    ) {}

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'items' => $this->items,
            'pageSize' => $this->pageSize,
            'nextAfterId' => $this->nextAfterId,
        ];
    }
}
