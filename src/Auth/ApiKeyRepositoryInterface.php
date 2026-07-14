<?php

declare(strict_types=1);

namespace App\Auth;

interface ApiKeyRepositoryInterface
{
    /** Devuelve la key activa (no revocada ni expirada) con ese id, o null. */
    public function getActiveKey(string $keyId): ?ApiKey;

    /** Registra el uso de la key (actualización laxa de last_used_at). */
    public function registerUse(string $keyId): void;
}
