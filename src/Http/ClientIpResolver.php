<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Resuelve la IP real del cliente cuando la API corre detrás de un reverse
 * proxy o balanceador que termina TLS.
 *
 * Solo se confía en la cabecera X-Forwarded-For si la conexión entrante viene
 * de un proxy declarado como de confianza (IP o CIDR). En caso contrario se usa
 * la IP de la conexión directa. Así se evita que un cliente falsee su IP para
 * saltarse el rate limiting.
 */
final class ClientIpResolver
{
    /** @var string[] */
    private array $trustedProxies;

    /** @param string[] $trustedProxies IPs o CIDRs de confianza */
    public function __construct(array $trustedProxies)
    {
        $this->trustedProxies = array_filter(array_map('trim', $trustedProxies));
    }

    /** @param string[] $trustedProxies */
    public static function fromConfigValue(?string $csv): self
    {
        return new self($csv === null || $csv === '' ? [] : explode(',', $csv));
    }

    public function resolve(Request $request): string
    {
        $remote = $request->remoteAddr;

        if (!$this->isTrusted($remote)) {
            return $remote;
        }

        $forwardedFor = $request->header('X-Forwarded-For');
        if ($forwardedFor === null || $forwardedFor === '') {
            return $remote;
        }

        // X-Forwarded-For: cliente, proxy1, proxy2... El cliente es el primero.
        $first = trim(explode(',', $forwardedFor)[0]);

        return $first !== '' ? $first : $remote;
    }

    private function isTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $proxy) {
            if (str_contains($proxy, '/')) {
                if ($this->inCidr($ip, $proxy)) {
                    return true;
                }
            } elseif ($proxy === $ip) {
                return true;
            }
        }

        return false;
    }

    private function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false; // Solo IPv4; IPv6 se compara por igualdad exacta arriba.
        }
        $bits = (int) $bits;
        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
