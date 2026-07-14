<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Cache en memoria compartida entre peticiones del mismo worker.
 *
 * Se usa para cachear la validación de API keys (evita tocar la BD en el caso
 * caliente) y para el contador de rate limiting por ventana.
 */
interface Cache
{
    /**
     * Devuelve el valor almacenado. $found indica si hubo acierto, para poder
     * distinguir "no está" de "está guardado como null".
     */
    public function fetch(string $key, bool &$found): mixed;

    public function store(string $key, mixed $value, int $ttlSeconds): void;

    /**
     * Incrementa atómicamente un contador (creándolo con el TTL indicado la
     * primera vez) y devuelve el nuevo valor. Base del rate limiting.
     */
    public function increment(string $key, int $ttlSeconds): int;
}
