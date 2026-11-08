<?php

declare(strict_types=1);

namespace App\RateLimit;

use App\Cache\Cache;

/**
 * Rate limiting por ventana fija y por IP. Si no hay cache compartido (APCu),
 * degrada de forma segura y no bloquea.
 */
final class RateLimiter
{
    public function __construct(
        private readonly Cache $cache,
        private readonly int $permitLimit,
        private readonly int $windowSeconds,
    ) {}

    public function check(string $clientIp): RateLimitResult
    {
        $now = time();
        $windowId = intdiv($now, $this->windowSeconds);
        $key = sprintf('rl:%s:%d', $clientIp, $windowId);

        $count = $this->cache->increment($key, $this->windowSeconds);

        if ($count > $this->permitLimit) {
            $retryAfter = $this->windowSeconds - ($now % $this->windowSeconds);

            return RateLimitResult::rejected(max(1, $retryAfter));
        }

        return RateLimitResult::allowed();
    }
}
