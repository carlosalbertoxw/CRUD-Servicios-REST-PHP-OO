<?php

declare(strict_types=1);

use Tests\TestDatabase;

require dirname(__DIR__) . '/vendor/autoload.php';

// La app bajo prueba usa la BD de test (no la real). El nombre por defecto es
// notes_test; el resto se toma del entorno (con valores locales por defecto).
$defaults = [
    'APP_ENV' => 'testing',
    'DB_NAME' => getenv('DB_NAME') ?: 'notes_test',
    'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
    'DB_PORT' => getenv('DB_PORT') ?: '3306',
    'DB_USER' => getenv('DB_USER') ?: 'root',
    'DB_PASSWORD' => getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : 'root_password',
    // Sin límite práctico de rate limiting durante las pruebas.
    'RATE_LIMIT_PERMIT' => '1000000',
    'AUTH_KEY_CACHE_SECONDS' => '0',
];
foreach ($defaults as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

TestDatabase::migrate(dirname(__DIR__));
