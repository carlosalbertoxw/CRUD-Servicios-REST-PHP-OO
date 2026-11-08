<?php

declare(strict_types=1);

namespace App\Http;

/** Representa la petición HTTP entrante (método, ruta, query, cabeceras, cuerpo y esquema). */
final class Request
{
    /**
     * @param array<string,string> $query
     * @param array<string,string> $headers  cabeceras con clave en minúsculas
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly string $remoteAddr,
        /** La conexión directa llegó por HTTPS (sin contar proxies). */
        public readonly bool $https = false,
    ) {}

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            self::readQuery(),
            self::readHeaders(),
            file_get_contents('php://input') ?: '',
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on',
        );
    }

    /**
     * Constructor de conveniencia para pruebas: arma una petición sin globals.
     *
     * @param array<string,string> $query
     * @param array<string,string> $headers
     */
    public static function create(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        string $rawBody = '',
        string $remoteAddr = '127.0.0.1',
        bool $https = false,
    ): self {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = $value;
        }

        return new self(strtoupper($method), $path, $query, $normalized, $rawBody, $remoteAddr, $https);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function queryParam(string $name, ?string $default = null): ?string
    {
        $value = $this->query[$name] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    /** @return array<string,string> */
    private static function readQuery(): array
    {
        $result = [];
        foreach ($_GET as $key => $value) {
            if (is_string($value)) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    /** @return array<string,string> */
    private static function readHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr((string) $key, 5)));
                $headers[$name] = (string) $value;
            }
        }

        return $headers;
    }
}
