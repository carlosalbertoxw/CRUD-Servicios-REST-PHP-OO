<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Respuesta HTTP: código de estado, cabeceras y cuerpo.
 *
 * Ofrece atajos para JSON y para errores en formato Problem Details
 * (RFC 9457, application/problem+json), igual que los proyectos C# y Java.
 */
final class Response
{
    /** @param array<string,string> $headers */
    private function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {}

    /** @param array<string,string> $headers */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($status, array_merge([
            'Content-Type' => 'application/json;charset=utf-8',
        ], $headers), $body === false ? '{}' : $body);
    }

    public static function noContent(): self
    {
        return new self(204, [], '');
    }

    /** Redirección permanente que conserva el método y el cuerpo (308). */
    public static function redirect(string $location): self
    {
        return new self(308, ['Location' => $location], '');
    }

    /**
     * Respuesta con cuerpo ya serializado (JSON, HTML...): para servir la spec
     * OpenAPI y la UI de documentación.
     *
     * @param array<string,string> $headers
     */
    public static function raw(string $body, int $status = 200, array $headers = []): self
    {
        return new self($status, $headers, $body);
    }

    /**
     * Error en formato Problem Details.
     *
     * @param array<string,mixed> $extensions  campos extra (p. ej. "errors")
     * @param array<string,string> $headers
     */
    public static function problem(
        int $status,
        string $title,
        ?string $detail = null,
        array $extensions = [],
        array $headers = [],
    ): self {
        $problem = array_merge([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
        ], $detail !== null ? ['detail' => $detail] : [], $extensions);

        $body = json_encode($problem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($status, array_merge([
            'Content-Type' => 'application/problem+json;charset=utf-8',
        ], $headers), $body === false ? '{}' : $body);
    }

    /**
     * Devuelve una copia con cabeceras adicionales (las existentes tienen
     * prioridad si hay colisión).
     *
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, array_merge($headers, $this->headers), $this->body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->body !== '') {
            echo $this->body;
        }
    }
}
