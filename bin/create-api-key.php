<?php

declare(strict_types=1);

/**
 * Genera una nueva API key y la registra (hasheada) en la tabla api_keys.
 *
 * Uso:
 *   php bin/create-api-key.php "Nombre del cliente" [key_id]
 *
 * Imprime UNA sola vez la key completa "<key_id>.<secreto>": guárdala, no se
 * puede recuperar (en la BD solo queda el SHA-256 del secreto).
 *
 * Usa el usuario administrador (ADMIN_DB_USER, con INSERT sobre api_keys), no
 * el usuario de la aplicación.
 */

use App\Config\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$config = Config::load(dirname(__DIR__));

$clientName = $argv[1] ?? null;
if ($clientName === null || trim($clientName) === '') {
    fwrite(STDERR, "Uso: php bin/create-api-key.php \"Nombre del cliente\" [key_id]\n");
    exit(1);
}

$keyId = $argv[2] ?? bin2hex(random_bytes(8));
if (strlen($keyId) > 64) {
    fwrite(STDERR, "El key_id no puede superar los 64 caracteres.\n");
    exit(1);
}

$secret = bin2hex(random_bytes(24));
$keyHash = hash('sha256', $secret, true);

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $config->get('DB_HOST', 'notes-mysql'),
    $config->getInt('DB_PORT', 3306),
    $config->get('DB_NAME', 'notes'),
);

$pdo = new PDO(
    $dsn,
    $config->get('ADMIN_DB_USER', 'notes_user'),
    $config->get('ADMIN_DB_PASSWORD', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$stmt = $pdo->prepare(
    'INSERT INTO api_keys (key_id, key_hash, client_name) VALUES (:key_id, :key_hash, :client_name)'
);
$stmt->execute([
    'key_id' => $keyId,
    'key_hash' => $keyHash,
    'client_name' => $clientName,
]);

echo "API key creada para: {$clientName}\n";
echo "Envíala en la cabecera:  X-Api-Key: {$keyId}.{$secret}\n";
echo "(Guárdala ahora; el secreto no se puede recuperar.)\n";
