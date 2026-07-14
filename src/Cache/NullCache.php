<?php

declare(strict_types=1);

namespace App\Cache;

/**
 * Cache inerte para cuando APCu no está disponible. Degradación segura:
 * la validación de keys siempre va a la BD y el rate limiting no bloquea
 * (cada incremento reporta 1, por debajo de cualquier límite razonable).
 */
final class NullCache implements Cache
{
    public function fetch(string $key, bool &$found): mixed
    {
        $found = false;

        return null;
    }

    public function store(string $key, mixed $value, int $ttlSeconds): void
    {
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        return 1;
    }
}
