<?php

declare(strict_types=1);

namespace App\RateLimit;

final class RateLimitResult
{
    private function __construct(
        public readonly bool $allowed,
        public readonly int $retryAfterSeconds,
    ) {
    }

    public static function allowed(): self
    {
        return new self(true, 0);
    }

    public static function rejected(int $retryAfterSeconds): self
    {
        return new self(false, $retryAfterSeconds);
    }
}
