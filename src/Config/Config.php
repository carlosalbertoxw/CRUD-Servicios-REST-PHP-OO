<?php

declare(strict_types=1);

namespace App\Config;

use Dotenv\Dotenv;

/**
 * Configuración de la aplicación tomada de variables de entorno.
 *
 * En Docker los valores llegan por docker-compose; en local pueden venir de un
 * archivo .env (opcional). Nunca se hardcodean credenciales en el código.
 */
final class Config
{
    /** @var array<string,string> */
    private array $values;

    private function __construct(array $values)
    {
        $this->values = $values;
    }

    public static function load(string $basePath): self
    {
        // safeLoad: no falla si no existe .env (caso Docker con environment:).
        Dotenv::createImmutable($basePath)->safeLoad();

        return new self(array_merge($_ENV, $_SERVER));
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->values[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key);

        return $value === null ? $default : (int) $value;
    }

    public function isProduction(): bool
    {
        return $this->get('APP_ENV', 'production') === 'production';
    }
}
