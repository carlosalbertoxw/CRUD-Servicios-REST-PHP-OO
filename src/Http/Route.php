<?php

declare(strict_types=1);

namespace App\Http;

/** Una ruta registrada: método, patrón compilado a regex y su manejador. */
final class Route
{
    /** @param callable $handler función que recibe (Request, array $params, ?string $ownerKeyId): Response */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly string $regex,
        public readonly mixed $handler,
        public readonly bool $requiresAuth,
    ) {}
}
