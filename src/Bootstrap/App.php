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
 * la conexión a BD), define las rutas y ejecuta la petición aplicando HTTPS en
 * producción, límite de tamaño, rate limiting, autenticación, cabeceras de
 * seguridad y manejo de errores.
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

    /** @return list<\App\Http\Route> rutas registradas (p. ej. para contrastarlas con la spec OpenAPI) */
    public function routes(): array
    {
        return $this->router->routes();
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
        // 0) En producción solo HTTPS (TLS terminado en el reverse proxy). Los
        //    health checks quedan exentos: los sondeos del orquestador van por HTTP.
        //    La redirección solo apunta a un host de ALLOWED_HOSTS: el Host lo
        //    envía el cliente y usarlo sin validar permitiría un open redirect.
        if ($this->config->isProduction() && !$this->isHealthCheck($request) && !$this->ipResolver->isHttps($request)) {
            $host = $this->allowedHost($request);
            if ($host === null) {
                return Response::problem(400, 'Host no permitido.', 'Usa HTTPS con un host válido.');
            }

            return Response::redirect('https://' . $host . $request->path . $this->queryString($request));
        }

        // 1) Límite de tamaño del cuerpo.
        $maxBody = $this->config->getInt('MAX_BODY_BYTES', 1_048_576);
        if (strlen($request->rawBody) > $maxBody) {
            return Response::problem(413, 'La petición es demasiado grande.');
        }

        // 2) Rate limiting por IP real (ventana fija). Los health checks no
        //    consumen cuota.
        $rate = $this->isHealthCheck($request)
            ? null
            : $this->rateLimiter()->check($this->ipResolver->resolve($request));
        if ($rate !== null && !$rate->allowed) {
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

        // Los controladores se construyen solo cuando su ruta coincide (así la
        // conexión a la BD es perezosa). $notes('list') devuelve el handler que
        // llama a NotesController::list().
        $notes = $this->handler(fn() => $this->notesController());
        $health = $this->handler(fn() => $this->healthController());
        $docs = $this->handler(fn() => $this->docsController());

        $router->get('/api/notes', $notes('list'));
        $router->get('/api/notes/{id:\d+}', $notes('getById'));
        $router->post('/api/notes', $notes('create'));
        $router->put('/api/notes/{id:\d+}', $notes('update'));
        $router->delete('/api/notes/{id:\d+}', $notes('delete'));

        $router->get('/health/live', $health('live'), requiresAuth: false);
        $router->get('/health/ready', $health('ready'), requiresAuth: false);

        $router->get('/openapi.json', $docs('spec'), requiresAuth: false);
        $router->get('/docs', $docs('ui'), requiresAuth: false);

        return $router;
    }

    /**
     * Fábrica de handlers para un controlador: devuelve una función que, dado el
     * nombre de un método, produce el callable que el router invoca.
     *
     * @param callable(): object $controllerFactory
     *
     * @return callable(string): (callable(Request, array<string,string>, ?string): Response)
     */
    private function handler(callable $controllerFactory): callable
    {
        return static fn(string $method): callable
            => static fn(Request $request, array $params, ?string $ownerKeyId): Response
                => $controllerFactory()->{$method}($request, $params, $ownerKeyId);
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

    /**
     * Cabeceras de seguridad: nosniff siempre (la API solo responde JSON/HTML
     * propio); HSTS solo en producción, donde se sirve por HTTPS.
     */
    private function withSecurityHeaders(Response $response): Response
    {
        $headers = ['X-Content-Type-Options' => 'nosniff'];
        if ($this->config->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $response->withHeaders($headers);
    }

    private function isHealthCheck(Request $request): bool
    {
        return str_starts_with($request->path, '/health/');
    }

    /**
     * Host configurado (ALLOWED_HOSTS, separados por coma) que coincide con el de
     * la petición, sin puerto y sin distinguir mayúsculas; null si no está en la
     * lista. Se devuelve el valor de la configuración, no el de la petición.
     */
    private function allowedHost(Request $request): ?string
    {
        $requested = strtolower((string) preg_replace('/:\d+$/', '', (string) $request->header('Host')));
        foreach (explode(',', (string) $this->config->get('ALLOWED_HOSTS', '')) as $allowed) {
            $allowed = trim($allowed);
            if ($allowed !== '' && strtolower($allowed) === $requested) {
                return $allowed;
            }
        }

        return null;
    }

    private function queryString(Request $request): string
    {
        return $request->query === [] ? '' : '?' . http_build_query($request->query);
    }

    private function configureErrorDisplay(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->config->isProduction() ? '0' : '1');
    }

    private function handleUnexpected(Throwable $e): Response
    {
        // Una línea JSON por error (stderr del contenedor): fácil de indexar en
        // una plataforma de logs.
        error_log((string) json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'level' => 'error',
            'service' => 'notes-api',
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // Nunca se filtran detalles internos al cliente en producción.
        $detail = $this->config->isProduction() ? null : $e->getMessage();

        return Response::problem(500, 'Ocurrió un error al procesar la petición.', $detail);
    }
}
