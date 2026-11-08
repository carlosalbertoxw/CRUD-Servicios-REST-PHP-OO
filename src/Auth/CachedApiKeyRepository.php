<?php

declare(strict_types=1);

namespace App\Auth;

use App\Cache\Cache;

/**
 * Decorador con cache sobre el repositorio de API keys: el caso caliente
 * autentica sin tocar la BD (ni lookup ni registro de uso), dejando la
 * petición en cero round-trips a MySQL. El costo asumido es que una revocación
 * tarda hasta el TTL del cache en surtir efecto.
 */
final class CachedApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function __construct(
        private readonly ApiKeyRepositoryInterface $inner,
        private readonly Cache $cache,
        private readonly int $ttlSeconds,
    ) {}

    public function getActiveKey(string $keyId): ?ApiKey
    {
        $cacheKey = 'api-key:' . $keyId;
        $found = false;
        $cached = $this->cache->fetch($cacheKey, $found);
        if ($found) {
            return $cached instanceof ApiKey ? $cached : null;
        }

        $key = $this->inner->getActiveKey($keyId);
        // También se cachea el "no existe" (null) para no martillar la BD con
        // key_ids inválidos.
        $this->cache->store($cacheKey, $key, $this->ttlSeconds);

        return $key;
    }

    public function registerUse(string $keyId): void
    {
        $cacheKey = 'api-key-used:' . $keyId;
        $found = false;
        $this->cache->fetch($cacheKey, $found);
        if ($found) {
            return;
        }

        $this->inner->registerUse($keyId);
        // Menor que el INTERVAL 1 HOUR del UPDATE: la BD sigue siendo la fuente
        // de verdad aunque haya varios workers con caches independientes.
        $this->cache->store($cacheKey, true, 3000);
    }
}
