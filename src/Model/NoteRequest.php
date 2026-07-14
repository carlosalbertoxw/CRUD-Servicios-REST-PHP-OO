<?php

declare(strict_types=1);

namespace App\Model;

use App\Exception\ValidationException;

/**
 * Cuerpo de las peticiones de creación y actualización de notas, ya validado.
 *
 * Reglas (idénticas a la referencia): título obligatorio de ≤250 caracteres;
 * contenido opcional de ≤100.000 caracteres.
 */
final class NoteRequest
{
    private const MAX_TITLE = 250;
    private const MAX_TEXT = 100_000;

    public function __construct(
        public readonly string $title,
        public readonly ?string $text,
    ) {
    }

    /**
     * Parsea y valida el JSON del cuerpo.
     *
     * @throws ValidationException si el JSON es inválido o incumple las reglas
     */
    public static function fromJson(string $rawBody): self
    {
        $data = json_decode($rawBody === '' ? 'null' : $rawBody, true);

        if (!is_array($data)) {
            throw new ValidationException(['body' => ['El cuerpo debe ser un objeto JSON válido.']]);
        }

        $errors = [];

        $title = self::readString($data, 'title');
        if ($title === null || $title === '') {
            $errors['title'][] = 'El título es obligatorio.';
        } elseif (mb_strlen($title) > self::MAX_TITLE) {
            $errors['title'][] = 'El título no puede superar los 250 caracteres.';
        }

        $text = self::readString($data, 'text');
        if ($text !== null && mb_strlen($text) > self::MAX_TEXT) {
            $errors['text'][] = 'El contenido no puede superar los 100.000 caracteres.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self((string) $title, $text);
    }

    /** @param array<string,mixed> $data */
    private static function readString(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return is_scalar($data[$key]) ? (string) $data[$key] : '';
    }
}
