<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Auth\ApiKeyAuthenticator;
use App\Auth\CachedApiKeyRepository;
use App\Auth\MySqlApiKeyRepository;
use App\Cache\ApcuCache;
use App\Cache\Cache;
use App\Cache\NullCache;
use App\Config\Config;
use App\Controller\DocsController;
use App\Controller\HealthController;
use App\Controller\NotesController;
use App\Database\Database;
use App\Http\ClientIpResolver;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\RateLimit\RateLimiter;
use App\Repository\NoteRepository;
use Throwable;

/**
 * Contenedor y pipeline de la aplicación: construye las dependencias (perezosa
 * la conexión a BD), define las rutas y ejecuta la petición aplicando límite de
 * tamaño, rate limiting, autenticación, cabeceras de seguridad y manejo de
 * errores.
 */
final class App
{
    private Config $config;
    private Database $database;
    private Cache $cache;
    private ClientIpResolver $ipResolver;
    private Router $router;

    public function __construct(private readonly string $basePath)
    {
        $this->config = Config::load($basePath);
        $this->configureErrorDisplay();

        $this->database = Database::fromConfig($this->config);
        $this->cache = ApcuCache::isAvailable() ? new ApcuCache() : new NullCache();
        $this->ipResolver = ClientIpResolver::fromConfigValue($this->config->get('TRUSTED_PROXIES'));
        $this->router = $this->buildRouter();
    }

    public function run(): void
    {
        $this->handle(Request::fromGlobals())->send();
    }

    /** Procesa una petición y devuelve la respuesta (punto de entrada para pruebas). */
    public function handle(Request $request): Response
    {
        try {
            $response = $this->dispatch($request);
        } catch (Throwable $e) {
            $response = $this->handleUnexpected($e);
        }

        return $this->withSecurityHeaders($response);
    }

    private function dispatch(Request $request): Response
    {
        // 1) Límite de tamaño del cuerpo.
        $maxBody = $this->config->getInt('MAX_BODY_BYTES', 1_048_576);
        if (strlen($request->rawBody) > $maxBody) {
            return Response::problem(413, 'La petición es demasiado grande.');
        }

        // 2) Rate limiting por IP real (ventana fija).
        $clientIp = $this->ipResolver->resolve($request);
        $rate = $this->rateLimiter()->check($clientIp);
        if (!$rate->allowed) {
            return Response::problem(
                429,
                'Demasiadas peticiones.',
                'Se superó el límite de peticiones. Intenta más tarde.',
                [],
                ['Retry-After' => (string) $rate->retryAfterSeconds],
            );
        }

        // 3) Enrutado.
        $match = $this->router->match($request);
        if ($match === null) {
            return Response::problem(404, 'No encontrado.', 'El recurso solicitado no existe.');
        }
        if (isset($match['allow'])) {
            return Response::problem(
                405,
                'Método no permitido.',
                null,
                [],
                ['Allow' => implode(', ', $match['allow'])],
            );
        }

        /** @var \App\Http\Route $route */
        $route = $match['route'];
        $params = $match['params'];

        // 4) Autenticación (fail-closed): salvo rutas marcadas como anónimas.
        $ownerKeyId = null;
        if ($route->requiresAuth) {
            $auth = $this->authenticator()->authenticate($request);
            if (!$auth->authenticated) {
                return Response::problem(
                    401,
                    'No autorizado.',
                    $auth->failureReason,
                    [],
                    ['WWW-Authenticate' => ApiKeyAuthenticator::SCHEME],
                );
            }
            $ownerKeyId = $auth->keyId;
        }

        return ($route->handler)($request, $params, $ownerKeyId);
    }

    private function buildRouter(): Router
    {
        $router = new Router();

        $router->get('/api/notes', fn (Request $r, array $p, ?string $o) => $this->notesController()->list($r, $p, $o));
        $router->get('/api/notes/{id}', fn (Request $r, array $p, ?string $o) => $this->notesController()->getById($r, $p, $o));
        $router->post('/api/notes', fn (Request $r, array $p, ?string $o) => $this->notesController()->create($r, $p, $o));
        $router->put('/api/notes/{id}', fn (Request $r, array $p, ?string $o) => $this->notesController()->update($r, $p, $o));
        $router->delete('/api/notes/{id}', fn (Request $r, array $p, ?string $o) => $this->notesController()->delete($r, $p, $o));

        $router->get('/health/live', fn (Request $r, array $p, ?string $o) => $this->healthController()->live($r, $p, $o), requiresAuth: false);
        $router->get('/health/ready', fn (Request $r, array $p, ?string $o) => $this->healthController()->ready($r, $p, $o), requiresAuth: false);

        $router->get('/openapi.json', fn (Request $r, array $p, ?string $o) => $this->docsController()->spec($r, $p, $o), requiresAuth: false);
        $router->get('/docs', fn (Request $r, array $p, ?string $o) => $this->docsController()->ui($r, $p, $o), requiresAuth: false);

        return $router;
    }

    private function notesController(): NotesController
    {
        return new NotesController(new NoteRepository($this->database->connection()));
    }

    private function healthController(): HealthController
    {
        return new HealthController($this->database);
    }

    private function docsController(): DocsController
    {
        return new DocsController($this->basePath);
    }

    private function authenticator(): ApiKeyAuthenticator
    {
        $repository = new CachedApiKeyRepository(
            new MySqlApiKeyRepository($this->database->connection()),
            $this->cache,
            $this->config->getInt('AUTH_KEY_CACHE_SECONDS', 60),
        );

        return new ApiKeyAuthenticator($repository);
    }

    private function rateLimiter(): RateLimiter
    {
        return new RateLimiter(
            $this->cache,
            $this->config->getInt('RATE_LIMIT_PERMIT', 100),
            $this->config->getInt('RATE_LIMIT_WINDOW_SECONDS', 60),
        );
    }

    /** En producción se añaden cabeceras de seguridad (HSTS) a toda respuesta. */
    private function withSecurityHeaders(Response $response): Response
    {
        if (!$this->config->isProduction()) {
            return $response;
        }

        return $response->withHeaders([
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function configureErrorDisplay(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->config->isProduction() ? '0' : '1');
    }

    private function handleUnexpected(Throwable $e): Response
    {
        error_log(sprintf('[notes-api] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));

        // Nunca se filtran detalles internos al cliente en producción.
        $detail = $this->config->isProduction() ? null : $e->getMessage();

        return Response::problem(500, 'Ocurrió un error al procesar la petición.', $detail);
    }
}
