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
- **Semántica REST correcta**: `200/201/204/400/401/404/405/413/429` y errores en
  formato Problem Details (`application/problem+json`, RFC 7807).
- **Prepared statements** en todo el acceso a datos (defensa contra inyección
  SQL).
- **Documentación OpenAPI 3.1** con UI interactiva (Scalar) en `/docs`.
- **Health checks** para orquestadores: `/health/live` y `/health/ready`.
- **Cabeceras de seguridad** en producción (HSTS, `X-Content-Type-Options`).
- **Migraciones** versionadas e idempotentes y **pruebas de integración**
  (PHPUnit) con **CI** en GitHub Actions.

## Requisitos

- Docker y Docker Compose.
- El contenedor de **MySQL** compartido (`notes-mysql`) debe estar en marcha; la
  API se conecta a su base de datos `notes` reutilizando su red de Docker.

> La API usa el usuario de aplicación `notes_app` (solo permisos DML). El esquema
> vive en [`db/migrations/`](db/migrations).

## Cómo ejecutarlo

```bash
docker compose up --build -d
```

Esto, en orden: construye la imagen, ejecuta el servicio `migrations` (aplica el
esquema y los datos de ejemplo y termina) y arranca la API en
`http://localhost:8081`.

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
`nextAfterId`: pásalo como `afterId` para la siguiente página.

**Validación del cuerpo**: `title` obligatorio (≤ 250 caracteres) y `text`
opcional (≤ 100 000 caracteres).

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
docker exec notes-php-api php bin/create-api-key.php "Nombre del cliente"
```

Imprime **una sola vez** la key completa `<key_id>.<secreto>`: guárdala, no se
puede recuperar. Para revocar una key, marca `revoked_at`; para caducarla,
`expires_at` (una revocación surte efecto tras el TTL del cache, 60 s por
defecto).

## Migraciones

El esquema vive en [`db/migrations/`](db/migrations) como scripts versionados. El
runner los aplica en orden, de forma idempotente, registrando los ya aplicados
en la tabla `php_schema_migrations`:

```bash
docker exec notes-php-api php bin/migrate.php          # solo esquema
docker exec notes-php-api php bin/migrate.php --seed   # esquema + datos de ejemplo
```

En `docker compose up` esto lo hace automáticamente el servicio `migrations`
antes de arrancar la API.

## Pruebas

Pruebas de integración con PHPUnit que ejercitan la API contra una base de datos
MySQL de test (`notes_test`, aislada de la real):

```bash
composer install
DB_HOST=127.0.0.1 DB_USER=root DB_PASSWORD=root_password vendor/bin/phpunit
```

El CI ([`.github/workflows/ci.yml`](.github/workflows/ci.yml)) valida el
`composer.json`, hace lint de sintaxis, corre las pruebas contra un MySQL
efímero y construye la imagen Docker en cada push y pull request.

## Configuración

Variables de entorno (ver [`.env.example`](.env.example); en Docker se definen
en [`docker-compose.yml`](docker-compose.yml)):

| Variable                     | Por defecto   | Descripción                                  |
|------------------------------|---------------|----------------------------------------------|
| `APP_ENV`                    | `production`  | `development` muestra detalles de error.     |
| `DB_HOST` / `DB_PORT`        | `notes-mysql` / `3306` | Host y puerto de MySQL.             |
| `DB_NAME`                    | `notes`       | Base de datos.                               |
| `DB_USER` / `DB_PASSWORD`    | `notes_app` / … | Usuario de aplicación (solo DML).          |
| `ADMIN_DB_USER` / `…PASSWORD`| `notes_user` / … | Usuario admin (migraciones y crear keys). |
| `RATE_LIMIT_PERMIT`          | `100`         | Peticiones permitidas por ventana.           |
| `RATE_LIMIT_WINDOW_SECONDS`  | `60`          | Duración de la ventana.                      |
| `AUTH_KEY_CACHE_SECONDS`     | `60`          | TTL del cache de validación de keys.         |
| `MAX_BODY_BYTES`             | `1048576`     | Tamaño máximo del cuerpo (1 MB).             |
| `TRUSTED_PROXIES`            | *(vacío)*     | IPs/CIDRs de proxies de confianza para `X-Forwarded-For`. |

## Estructura del proyecto

```
public/            Webroot: front controller (index.php) y .htaccess
src/
  Bootstrap/       App: contenedor y pipeline (límite de tamaño, rate limit,
                   auth, enrutado, cabeceras de seguridad, manejo de errores)
  Config/          Carga de configuración desde variables de entorno
  Database/        Fábrica de conexiones PDO
  Http/            Request, Response (JSON / Problem Details), Router,
                   ClientIpResolver (IP real tras proxy)
  Auth/            API keys: autenticador, repositorio y decorador con cache
  Cache/           Abstracción de cache (APCu con fallback inerte)
  RateLimit/       Limitador por ventana fija
  Repository/      Acceso a datos de notas (prepared statements)
  Model/           Note, NoteSummary, PagedResult, NoteRequest (validación)
  Controller/      NotesController, HealthController, DocsController
bin/               create-api-key.php, migrate.php
db/migrations/     Esquema versionado; db/seed.sql (datos de ejemplo)
resources/         openapi.json (especificación de la API)
tests/             Pruebas de integración (PHPUnit)
.github/           CI (workflows) y Dependabot
```

## Notas de producción

- Sirve detrás de un reverse proxy que termine TLS; declara los proxies en
  `TRUSTED_PROXIES` para que el rate limiting use la IP real del cliente.
- Inyecta los secretos desde un gestor seguro; no los subas al repositorio.
- Los secretos de API key se guardan hasheados; usa `expires_at` para forzar
  rotación y revisa `last_used_at` para detectar keys en desuso.
