<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Request;

/**
 * Autenticación por API key.
 *
 * El cliente envía "X-Api-Key: <key_id>.<secreto>". Se busca la key activa por
 * key_id y se compara el SHA-256 del secreto en tiempo constante contra el hash
 * almacenado. El secreto nunca se guarda ni se registra en claro.
 */
final class ApiKeyAuthenticator
{
    public const HEADER = 'X-Api-Key';
    public const SCHEME = 'ApiKey';

    public function __construct(private readonly ApiKeyRepositoryInterface $apiKeys) {}

    /**
     * Autentica la petición.
     *
     * @return AuthResult resultado con el key_id/cliente, o un fallo tipificado
     */
    public function authenticate(Request $request): AuthResult
    {
        $header = $request->header(self::HEADER);
        if ($header === null || $header === '') {
            return AuthResult::missing();
        }

        $parts = explode('.', $header, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return AuthResult::failed("Formato inválido; se espera '" . self::HEADER . ": <key_id>.<secreto>'.");
        }

        [$keyId, $secret] = $parts;

        $apiKey = $this->apiKeys->getActiveKey($keyId);
        if ($apiKey === null || !$this->secretMatches($secret, $apiKey->keyHash)) {
            return AuthResult::failed('API key inválida, revocada o expirada.');
        }

        // Telemetría de mejor esfuerzo: no debe tumbar una autenticación válida.
        try {
            $this->apiKeys->registerUse($apiKey->keyId);
        } catch (\Throwable) {
            // Ignorado a propósito.
        }

        return AuthResult::success($apiKey->keyId);
    }

    private function secretMatches(string $providedSecret, string $storedHash): bool
    {
        $providedHash = hash('sha256', $providedSecret, true);

        return hash_equals($storedHash, $providedHash);
    }
}
