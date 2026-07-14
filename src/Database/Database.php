<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;
use PDO;

/**
 * Fábrica de conexiones PDO a MySQL.
 *
 * - Errores como excepciones (no silenciosos).
 * - Prepared statements reales (emulación desactivada): defensa contra
 *   inyección SQL, a diferencia de la concatenación de cadenas anterior.
 * - Zona horaria de sesión en UTC: todas las fechas se guardan y leen en UTC.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $name,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->get('DB_HOST', 'notes-mysql'),
            $config->getInt('DB_PORT', 3306),
            $config->get('DB_NAME', 'notes'),
            $config->get('DB_USER', 'notes_app'),
            $config->get('DB_PASSWORD', ''),
        );
    }

    public function connection(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $this->host,
                $this->port,
                $this->name,
            );

            $this->pdo = new PDO($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Filas coincidentes (no solo modificadas): actualizar con el
                // mismo valor cuenta como acierto, igual que en la referencia.
                PDO::MYSQL_ATTR_FOUND_ROWS => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            ]);
        }

        return $this->pdo;
    }
}
