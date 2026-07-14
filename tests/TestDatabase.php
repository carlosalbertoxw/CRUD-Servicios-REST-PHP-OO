<?php

declare(strict_types=1);

namespace Tests;

use PDO;

/**
 * Prepara una base de datos de pruebas aislada (separada de la BD real) y la
 * deja en un estado conocido antes de cada test.
 *
 * Se conecta como root (configurable) para poder crear la BD de test y aplicar
 * el esquema; la aplicación bajo prueba usa esa misma BD.
 */
final class TestDatabase
{
    /** key_id + secreto sembrados y usados por los tests. */
    public const KEY_A = 'local-dev.dev-secret';
    public const KEY_A_ID = 'local-dev';
    public const KEY_B = 'client-b.secret-b';
    public const KEY_B_ID = 'client-b';

    private static ?PDO $pdo = null;

    /** Configuración de conexión tomada del entorno (con valores por defecto locales). */
    public static function config(): array
    {
        return [
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => getenv('DB_NAME') ?: 'notes_test',
            'user' => getenv('DB_USER') ?: 'root',
            'password' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : 'root_password',
        ];
    }

    /** Crea la BD de test y aplica migraciones + una key extra para multi-tenant. */
    public static function migrate(string $basePath): void
    {
        $c = self::config();

        $root = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $c['host'], $c['port']),
            $c['user'],
            $c['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $root->exec(sprintf('CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4', $c['name']));
        $root->exec(sprintf('USE `%s`', $c['name']));

        foreach (glob($basePath . '/db/migrations/*.sql') ?: [] as $file) {
            $root->exec((string) file_get_contents($file));
        }

        self::$pdo = $root;
        self::reset();
    }

    /** Deja las tablas en un estado determinista antes de cada test. */
    public static function reset(): void
    {
        $pdo = self::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('TRUNCATE TABLE notes');
        $pdo->exec('TRUNCATE TABLE api_keys');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Dos clientes: A (con notas de ejemplo) y B (vacío, para multi-tenant).
        $insertKey = $pdo->prepare(
            'INSERT INTO api_keys (key_id, key_hash, client_name) VALUES (:id, UNHEX(:hash), :name)'
        );
        $insertKey->execute(['id' => self::KEY_A_ID, 'hash' => hash('sha256', 'dev-secret'), 'name' => 'Cliente A']);
        $insertKey->execute(['id' => self::KEY_B_ID, 'hash' => hash('sha256', 'secret-b'), 'name' => 'Cliente B']);

        $insertNote = $pdo->prepare(
            'INSERT INTO notes (owner_key_id, title, text) VALUES (:owner, :title, :text)'
        );
        $insertNote->execute(['owner' => self::KEY_A_ID, 'title' => 'Nota de ejemplo 1', 'text' => 'Contenido uno.']);
        $insertNote->execute(['owner' => self::KEY_A_ID, 'title' => 'Nota de ejemplo 2', 'text' => 'Contenido dos.']);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('TestDatabase::migrate() no se ha ejecutado.');
        }

        return self::$pdo;
    }
}
