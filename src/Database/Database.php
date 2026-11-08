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
    ) {}

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->get('DB_HOST', 'mysql'),
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
                // mismo valor cuenta como acierto, igual que en los proyectos C# y Java.
                self::mysqlAttribute('FOUND_ROWS') => true,
                self::mysqlAttribute('INIT_COMMAND') => "SET time_zone = '+00:00'",
            ]);
        }

        return $this->pdo;
    }

    /**
     * Atributo específico de MySQL: Pdo\Mysql::ATTR_* en PHP 8.4+ (las constantes
     * PDO::MYSQL_ATTR_* están obsoletas desde 8.5) y PDO::MYSQL_ATTR_* en 8.3.
     */
    private static function mysqlAttribute(string $name): int
    {
        $modern = 'Pdo\Mysql::ATTR_' . $name;

        return (int) constant(defined($modern) ? $modern : 'PDO::MYSQL_ATTR_' . $name);
    }
}
