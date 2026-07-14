-- Datos SOLO para desarrollo local. Idempotente: puede aplicarse varias veces.
-- La API key de desarrollo es:  X-Api-Key: local-dev.dev-secret
-- (key_hash = SHA-256 de "dev-secret" en binario)
INSERT IGNORE INTO api_keys (key_id, key_hash, client_name) VALUES
  ('local-dev', UNHEX('298754db2dbab6ec62605ceb0379eb7ee376580359449efe0caa3aa06cd56736'), 'Desarrollo local');

-- Solo inserta las notas de ejemplo si el cliente local-dev aún no tiene notas.
INSERT INTO notes (owner_key_id, title, text)
SELECT 'local-dev', 'Nota de ejemplo 1', 'Contenido de la primera nota de ejemplo.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM notes WHERE owner_key_id = 'local-dev');

INSERT INTO notes (owner_key_id, title, text)
SELECT 'local-dev', 'Nota de ejemplo 2', 'Contenido de la segunda nota de ejemplo.'
FROM DUAL
WHERE (SELECT COUNT(*) FROM notes WHERE owner_key_id = 'local-dev') = 1;
