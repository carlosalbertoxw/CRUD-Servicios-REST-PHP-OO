<?php

declare(strict_types=1);

namespace App\Auth;

/** Resultado de un intento de autenticación por API key. */
final class AuthResult
{
    private function __construct(
        public readonly bool $authenticated,
        public readonly ?string $keyId,
        public readonly ?string $clientName,
        public readonly ?string $failureReason,
    ) {
    }

    public static function success(string $keyId, string $clientName): self
    {
        return new self(true, $keyId, $clientName, null);
    }

    /** Sin cabecera: no se presentaron credenciales. */
    public static function missing(): self
    {
        return new self(false, null, null, 'No se proporcionó una API key.');
    }

    /** Credenciales presentes pero inválidas. */
    public static function failed(string $reason): self
    {
        return new self(false, null, null, $reason);
    }
}
