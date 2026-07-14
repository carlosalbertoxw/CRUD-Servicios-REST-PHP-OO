<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Errores de validación del cuerpo de la petición. Lleva un mapa
 * campo => [mensajes], que se expone como "errors" en el Problem Details (400).
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string,string[]> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Uno o más errores de validación ocurrieron.');
    }

    /** @return array<string,string[]> */
    public function errors(): array
    {
        return $this->errors;
    }
}
