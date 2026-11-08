<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/** Nota completa (incluye el contenido). Se serializa en camelCase con fechas ISO-8601 UTC. */
final class Note implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $text,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @param array<string,mixed> $row fila de la BD */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
            $row['text'] !== null ? (string) $row['text'] : null,
            DateTimeFormat::toIso8601((string) $row['created_at']),
            DateTimeFormat::toIso8601((string) $row['updated_at']),
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'text' => $this->text,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
