<?php

declare(strict_types=1);

namespace App\Model;

use App\Exception\ValidationException;

/**
 * Cuerpo de las peticiones de creación y actualización de notas, ya validado.
 *
 * Reglas (idénticas a las de los proyectos C# y Java): título obligatorio
 * (no vacío ni solo espacios) de ≤250 caracteres; contenido opcional de
 * ≤100.000 caracteres. Ambos deben ser cadenas de texto.
 */
final class NoteRequest
{
    private const MAX_TITLE = 250;
    private const MAX_TEXT = 100_000;

    public function __construct(
        public readonly string $title,
        public readonly ?string $text,
    ) {}

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

        $title = self::readString($data, 'title', $errors);
        if (!isset($errors['title'])) {
            if ($title === null || trim($title) === '') {
                $errors['title'][] = 'El título es obligatorio.';
            } elseif (mb_strlen($title) > self::MAX_TITLE) {
                $errors['title'][] = 'El título no puede superar los 250 caracteres.';
            }
        }

        $text = self::readString($data, 'text', $errors);
        if ($text !== null && mb_strlen($text) > self::MAX_TEXT) {
            $errors['text'][] = 'El contenido no puede superar los 100.000 caracteres.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self((string) $title, $text);
    }

    /**
     * Devuelve el campo como string, null si falta, o registra un error si
     * llega con otro tipo (número, booleano, objeto...).
     *
     * @param array<string,mixed>    $data
     * @param array<string,string[]> $errors
     */
    private static function readString(array $data, string $key, array &$errors): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }

        $errors[$key][] = "El campo {$key} debe ser una cadena de texto.";

        return null;
    }
}
