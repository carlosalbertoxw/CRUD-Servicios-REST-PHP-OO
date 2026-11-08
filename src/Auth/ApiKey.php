<?php

declare(strict_types=1);

namespace App\Auth;

/** Registro de una API key en la tabla api_keys (el secreto solo se guarda hasheado). */
final class ApiKey
{
    public function __construct(
        public readonly string $keyId,
        public readonly string $keyHash,
    ) {}
}
