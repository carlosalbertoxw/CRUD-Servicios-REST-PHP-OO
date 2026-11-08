<?php

declare(strict_types=1);

namespace App\Auth;

use PDO;

/** Repositorio de API keys sobre MySQL. */
final class MySqlApiKeyRepository implements ApiKeyRepositoryInterface
{
    public function __construct(private readonly PDO $pdo) {}

    public function getActiveKey(string $keyId): ?ApiKey
    {
        $stmt = $this->pdo->prepare(
            'SELECT key_id, key_hash
             FROM api_keys
             WHERE key_id = :keyId
               AND revoked_at IS NULL
               AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
        );
        $stmt->execute(['keyId' => $keyId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new ApiKey(
            (string) $row['key_id'],
            (string) $row['key_hash'],
        );
    }

    public function registerUse(string $keyId): void
    {
        // Actualización laxa: solo escribe si pasó más de una hora desde el
        // último registro, para no convertir cada request en una escritura.
        $stmt = $this->pdo->prepare(
            'UPDATE api_keys
             SET last_used_at = UTC_TIMESTAMP()
             WHERE key_id = :keyId
               AND (last_used_at IS NULL OR last_used_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR)',
        );
        $stmt->execute(['keyId' => $keyId]);
    }
}
