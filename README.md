# CRUD de Servicios REST — PHP OO

Servicio REST para gestionar notas, escrito en **PHP orientado a objetos sin
framework** (solo Composer para el autoload PSR-4 y la carga de variables de
entorno). Demuestra patrones de nivel producción: autenticación por API key,
aislamiento multi-tenant, paginación por keyset, búsqueda de texto completo,
rate limiting, health checks, documentación OpenAPI, migraciones y pruebas de
integración.

## Características

- **Autenticación por API key** (`X-Api-Key: <key_id>.<secreto>`): los secretos
  se guardan hasheados (SHA-256), nunca en claro; comparación en tiempo
  constante; soporte de revocación y expiración por key.
- **Aislamiento multi-tenant**: cada cliente solo ve y modifica sus propias
  notas (acotadas por `owner_key_id`).
- **Paginación por keyset** (`afterId` + `pageSize`): costo constante en la BD
  sin importar la profundidad de la página.
- **Búsqueda de texto completo** sobre título y contenido (índice FULLTEXT).
- **Rate limiting** por IP con ventana fija (por defecto 100 req/60 s), con
  cabecera `Retry-After`. Detrás de un reverse proxy usa la IP real
  (`X-Forwarded-For` con proxies de confianza).
- **Cache de validación de keys** en memoria (APCu): el caso caliente autentica
  sin tocar la BD.
- **Semántica REST correcta**: `200/201/204/400/401/404/405/413/429` (y `308`
  hacia HTTPS en producción), con errores en formato Problem Details
  (`application/problem+json`, RFC 9457).
- **Prepared statements** en todo el acceso a datos (defensa contra inyección
  SQL).
- **Documentación OpenAPI 3.1** con UI interactiva (Scalar) en `/docs`.
- **Health checks** para orquestadores: `/health/live` y `/health/ready`.
- **Cabeceras de seguridad**: `X-Content-Type-Options: nosniff` siempre; en
  producción, además, redirección a HTTPS (308) y HSTS.
- **Migraciones** versionadas e idempotentes y **pruebas de integración**
  (PHPUnit 12) con **CI** en GitHub Actions (PHP 8.3, 8.4 y 8.5), análisis
  estático con **PHPStan** y estilo **PER-CS** verificado con PHP-CS-Fixer.

## Requisitos

- Docker y Docker Compose (el compose trae su propio MySQL 8.4, publicado en
  `localhost:3308` para no chocar con los proyectos C# y Java).
- Para desarrollar sin Docker: PHP 8.3 o superior con `pdo_mysql` (y opcionalmente
  `apcu`) y [Composer](https://getcomposer.org/).

> La API usa el usuario de aplicación `notes_app` (solo permisos DML, lo crea
> [`db/init-users.sql`](db/init-users.sql)). El esquema vive en
> [`db/migrations/`](db/migrations).

## Cómo ejecutarlo

```bash
docker compose up --build -d
```

Esto, en orden: levanta MySQL, construye la imagen, ejecuta el servicio
`migrations` (aplica el esquema y los datos de ejemplo y termina) y arranca la
API en `http://localhost:8081` con `APP_ENV=development` (HTTP plano).

> Los proyectos hermanos en C# y Java publican MySQL y la API en otros puertos
> (3306/8080 y 3307/8082), así que los tres stacks pueden correr a la vez.

Comprobación rápida (la key de desarrollo sembrada es `local-dev.dev-secret`):

```bash
curl http://localhost:8081/health/ready
curl -H "X-Api-Key: local-dev.dev-secret" http://localhost:8081/api/notes
```

- **Documentación interactiva**: abre `http://localhost:8081/docs`.
- **Especificación OpenAPI**: `http://localhost:8081/openapi.json`.
- En [`api.http`](api.http) hay ejemplos de todas las operaciones listos para
  clientes REST (VS Code REST Client, JetBrains HTTP, etc.).

## Endpoints

Todos los `/api/notes*` requieren la cabecera `X-Api-Key` y operan sobre las
notas del cliente autenticado.

| Método | Ruta               | Descripción                                            |
|--------|--------------------|--------------------------------------------------------|
| GET    | `/api/notes`       | Lista notas (paginación keyset + búsqueda opcional).   |
| GET    | `/api/notes/{id}`  | Obtiene una nota con su contenido.                     |
| POST   | `/api/notes`       | Crea una nota. Responde `201` con cabecera `Location`. |
| PUT    | `/api/notes/{id}`  | Actualiza una nota. Responde `204`.                    |
| DELETE | `/api/notes/{id}`  | Elimina una nota. Responde `204`.                      |
| GET    | `/health/live`     | Liveness (no toca la BD). Anónimo.                     |
| GET    | `/health/ready`    | Readiness (comprueba la BD). Anónimo.                  |
| GET    | `/openapi.json`    | Especificación OpenAPI 3.1. Anónimo.                  |
| GET    | `/docs`            | UI interactiva (Scalar). Anónimo.                     |

**Parámetros de listado**: `afterId` (cursor, entero ≥ 0), `pageSize` (1–100,
por defecto 20) y `search` (≤ 100 caracteres). La respuesta incluye
`nextAfterId`: pásalo como `afterId` para la siguiente página (`null` = no hay
más). No incluye un total de registros: calcularlo exigiría un `COUNT(*)` sobre
todas las notas del cliente en cada página.

**Identificadores**: `{id}` debe ser numérico; `/api/notes/abc` o
`/api/notes/1abc` responden `404`.

**Validación del cuerpo**: `title` obligatorio (cadena de ≤ 250 caracteres, no
vacía ni solo espacios) y `text` opcional (cadena de ≤ 100 000 caracteres). Otros
tipos (números, booleanos, objetos) se rechazan con `400`.

**Fechas**: `createdAt` y `updatedAt` en UTC con formato ISO-8601 y sufijo `Z`.

**Errores**: sin key válida, `401` con `WWW-Authenticate: ApiKey`; al superar el
rate limiting, `429` con `Retry-After` (segundos que faltan para que se renueve
la ventana; los health checks no consumen cuota); cuerpo mayor a 1 MB, `413`.
Los de validación (`400`) incluyen `errors` con los mensajes por campo.

### Ejemplo de creación

```bash
curl -X POST http://localhost:8081/api/notes \
  -H "X-Api-Key: local-dev.dev-secret" \
  -H "Content-Type: application/json" \
  -d '{"title":"Mi nota","text":"Contenido"}'
```

## API keys

Los secretos solo se almacenan hasheados. Para emitir una nueva key usa el
script CLI (usa el usuario administrador `notes_user`, con permiso de INSERT):

```bash
docker compose exec api php bin/create-api-key.php "Nombre del cliente"
```

Imprime **una sola vez** la key completa `<key_id>.<secreto>`: guárdala, no se
puede recuperar. Las keys se revocan o caducan, no se borran (la FK de
`notes.owner_key_id` lo impide si tienen notas); una revocación surte efecto tras
el TTL del cache, 60 s por defecto:

```sql
UPDATE api_keys SET revoked_at = UTC_TIMESTAMP() WHERE key_id = '<key_id>';                   -- revocar
UPDATE api_keys SET expires_at = UTC_TIMESTAMP() + INTERVAL 90 DAY WHERE key_id = '<key_id>'; -- caducar
```

## Migraciones

El esquema vive en [`db/migrations/`](db/migrations) como scripts versionados. El
runner los aplica en orden, de forma idempotente, registrando los ya aplicados
en la tabla `php_schema_migrations`:

```bash
docker compose run --rm migrations php bin/migrate.php          # solo esquema
docker compose run --rm migrations php bin/migrate.php --seed   # esquema + datos de ejemplo
```

En `docker compose up` esto lo hace automáticamente el servicio `migrations`
antes de arrancar la API.

## Pruebas

Pruebas de integración con PHPUnit que ejercitan la API contra una base de datos
MySQL de test (`notes_test`, aislada de la real). Con el MySQL del compose
levantado (`docker compose up -d mysql`):

```bash
composer install
DB_HOST=127.0.0.1 DB_PORT=3308 DB_USER=root DB_PASSWORD=root_password composer test
```

Calidad del código:

```bash
composer analyse   # PHPStan (nivel 6)
composer cs        # verifica el estilo PER-CS (PHP-CS-Fixer)
composer cs-fix    # lo corrige
composer audit     # dependencias con vulnerabilidades conocidas
```

El CI ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) valida el
`composer.json`, audita las dependencias, verifica estilo y análisis estático,
corre las pruebas contra un MySQL efímero en PHP 8.3, 8.4 y 8.5 y construye la
imagen Docker en cada push y pull request. [CodeQL](.github/workflows/codeql.yml)
revisa los workflows de GitHub Actions (CodeQL no analiza PHP; el código lo
cubre PHPStan).

## Configuración

Variables de entorno (ver [`.env.example`](.env.example); en Docker se definen
en [`docker-compose.yml`](docker-compose.yml)):

| Variable                     | Por defecto   | Descripción                                  |
|------------------------------|---------------|----------------------------------------------|
| `APP_ENV`                    | `production`  | `production` exige HTTPS (308 + HSTS) y oculta detalles de error; `development` sirve por HTTP y los muestra. |
| `DB_HOST` / `DB_PORT`        | `mysql` / `3306` | Host y puerto de MySQL.                   |
| `DB_NAME`                    | `notes`       | Base de datos.                               |
| `DB_USER` / `DB_PASSWORD`    | `notes_app` / … | Usuario de aplicación (solo DML).          |
| `ADMIN_DB_USER` / `…PASSWORD`| `notes_user` / … | Usuario admin (migraciones y crear keys). |
| `RATE_LIMIT_PERMIT`          | `100`         | Peticiones permitidas por ventana.           |
| `RATE_LIMIT_WINDOW_SECONDS`  | `60`          | Duración de la ventana.                      |
| `AUTH_KEY_CACHE_SECONDS`     | `60`          | TTL del cache de validación de keys.         |
| `MAX_BODY_BYTES`             | `1048576`     | Tamaño máximo del cuerpo (1 MB). En la imagen Docker, Apache además corta en 1 MB (`LimitRequestBody` del [Dockerfile](Dockerfile)): para subir el límite cambia ambos. |
| `TRUSTED_PROXIES`            | *(vacío)*     | IPs/CIDRs de proxies de confianza para `X-Forwarded-For` y `X-Forwarded-Proto`. |

## Estructura del proyecto

```
public/            Webroot: front controller (index.php) y .htaccess
src/
  Bootstrap/       App: contenedor y pipeline (HTTPS, límite de tamaño, rate
                   limit, auth, enrutado, cabeceras de seguridad, errores)
  Config/          Carga de configuración desde variables de entorno
  Database/        Fábrica de conexiones PDO
  Http/            Request, Response (JSON / Problem Details), Router (con
                   restricciones {id:\d+}), ClientIpResolver (IP y esquema
                   reales tras proxy)
  Auth/            API keys: autenticador, repositorio y decorador con cache
  Cache/           Abstracción de cache (APCu con fallback inerte)
  RateLimit/       Limitador por ventana fija
  Repository/      Acceso a datos de notas (prepared statements)
  Model/           Note, NoteSummary, PagedResult, NoteRequest (validación)
  Controller/      NotesController, HealthController, DocsController
bin/               create-api-key.php, migrate.php
db/migrations/     Esquema versionado; db/seed.sql (datos de ejemplo);
                   db/init-users.sql (usuario de aplicación con mínimo privilegio)
resources/         openapi.json (especificación de la API)
tests/             Pruebas de integración (PHPUnit)
phpstan.neon       Configuración de PHPStan
.php-cs-fixer.dist.php  Reglas de estilo (PER-CS)
.github/           CI y CodeQL (workflows) y Dependabot
```

## Notas de producción

- Sirve detrás de un reverse proxy que termine TLS; declara los proxies en
  `TRUSTED_PROXIES` para que el rate limiting use la IP real del cliente y la
  redirección a HTTPS reconozca las peticiones que ya llegaron cifradas
  (`X-Forwarded-Proto: https`). Los health checks no se redirigen ni consumen
  cuota de rate limiting.
- Apache rechaza cuerpos mayores a 1 MB (`LimitRequestBody`) antes de llegar a
  PHP; la app aplica además `MAX_BODY_BYTES`.
- Los errores no controlados se registran como una línea JSON en el log de
  errores del contenedor.
- `/docs` carga Scalar desde jsDelivr con versión fija e integridad (SRI). Para
  actualizarlo, cambia la versión en
  [`DocsController`](src/Controller/DocsController.php) y recalcula el hash:
  `curl -sL <url> | openssl dgst -sha384 -binary | openssl base64 -A`.
- Inyecta los secretos desde un gestor seguro; no los subas al repositorio.
- Los secretos de API key se guardan hasheados; usa `expires_at` para forzar
  rotación y revisa `last_used_at` para detectar keys en desuso.

## Licencia

[MIT](LICENSE)
