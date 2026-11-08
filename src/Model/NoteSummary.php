<?php

declare(strict_types=1);

namespace App\Model;

use JsonSerializable;

/** Vista resumida para listados: no arrastra el contenido completo de la nota. */
final class NoteSummary implements JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['title'],
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
