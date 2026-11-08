<?php

declare(strict_types=1);

/**
 * Runner de migraciones idempotente.
 *
 * Aplica en orden los archivos db/migrations/*.sql que aún no se hayan
 * aplicado, registrándolos en la tabla `php_schema_migrations`. Con --seed
 * aplica además db/seed.sql (datos de desarrollo, idempotente).
 *
 * Uso:
 *   php bin/migrate.php [--seed]
 *
 * Usa el usuario administrador (ADMIN_DB_USER, con permisos de DDL), no el de
 * la aplicación. Reintenta la conexión mientras la BD arranca.
 */

use App\Config\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$basePath = dirname(__DIR__);
$config = Config::load($basePath);
$seed = in_array('--seed', $_SERVER['argv'] ?? [], true);

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config->get('DB_HOST', 'mysql'),
    $config->getInt('DB_PORT', 3306),
    $config->get('DB_NAME', 'notes'),
);
$user = $config->get('ADMIN_DB_USER', 'notes_user');
$password = $config->get('ADMIN_DB_PASSWORD', '');

$pdo = connectWithRetry($dsn, $user, $password, 30);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS php_schema_migrations (
        filename   VARCHAR(255) NOT NULL,
        applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (filename)
     ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4',
);

$applied = $pdo->query('SELECT filename FROM php_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);

$files = glob($basePath . '/db/migrations/*.sql') ?: [];
sort($files);

$pending = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Aplicando migración: {$name}\n";
    $pdo->exec((string) file_get_contents($file));

    $stmt = $pdo->prepare('INSERT INTO php_schema_migrations (filename) VALUES (:filename)');
    $stmt->execute(['filename' => $name]);
    $pending++;
}

echo $pending === 0 ? "Sin migraciones pendientes.\n" : "Migraciones aplicadas: {$pending}\n";

if ($seed) {
    echo "Aplicando datos de desarrollo (seed)...\n";
    $pdo->exec((string) file_get_contents($basePath . '/db/seed.sql'));
    echo "Seed aplicado.\n";
}

echo "Listo.\n";

/** Reintenta la conexión mientras la BD termina de arrancar. */
function connectWithRetry(string $dsn, string $user, string $password, int $attempts): PDO
{
    for ($i = 1; $i <= $attempts; $i++) {
        try {
            return new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            if ($i === $attempts) {
                throw $e;
            }
            fwrite(STDERR, "Esperando a la base de datos... (intento {$i}/{$attempts})\n");
            sleep(2);
        }
    }

    throw new RuntimeException('No se pudo conectar a la base de datos.');
}
