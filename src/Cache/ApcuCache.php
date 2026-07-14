<?php

declare(strict_types=1);

namespace App\Cache;

/** Implementación de Cache sobre APCu (memoria compartida del worker). */
final class ApcuCache implements Cache
{
    public static function isAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    public function fetch(string $key, bool &$found): mixed
    {
        return apcu_fetch($key, $found);
    }

    public function store(string $key, mixed $value, int $ttlSeconds): void
    {
        apcu_store($key, $value, $ttlSeconds);
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        // apcu_add solo crea la clave si no existe: fija el TTL de la ventana
        // una vez, sin reiniciarlo en cada incremento.
        apcu_add($key, 0, $ttlSeconds);

        return (int) apcu_inc($key);
    }
}
