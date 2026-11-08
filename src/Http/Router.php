<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Enrutador por método + ruta con parámetros ({id}, o con restricción
 * {id:\d+}). Distingue "no existe" (404) de "existe pero con otro método"
 * (405), devolviendo Allow.
 */
final class Router
{
    /** @var Route[] */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, bool $requiresAuth = true): void
    {
        // /api/notes/{id}      ->  #^/api/notes/(?P<id>[^/]+)$#
        // /api/notes/{id:\d+}  ->  #^/api/notes/(?P<id>\d+)$#
        $regex = preg_replace_callback(
            '#\{(\w+)(?::([^}]+))?\}#',
            static fn(array $m): string => sprintf('(?P<%s>%s)', $m[1], $m[2] ?? '[^/]+'),
            $pattern,
        );
        $this->routes[] = new Route($method, $pattern, '#^' . $regex . '$#', $handler, $requiresAuth);
    }

    public function get(string $pattern, callable $handler, bool $requiresAuth = true): void
    {
        $this->add('GET', $pattern, $handler, $requiresAuth);
    }

    public function post(string $pattern, callable $handler, bool $requiresAuth = true): void
    {
        $this->add('POST', $pattern, $handler, $requiresAuth);
    }

    public function put(string $pattern, callable $handler, bool $requiresAuth = true): void
    {
        $this->add('PUT', $pattern, $handler, $requiresAuth);
    }

    public function delete(string $pattern, callable $handler, bool $requiresAuth = true): void
    {
        $this->add('DELETE', $pattern, $handler, $requiresAuth);
    }

    /** @return list<Route> rutas registradas, en orden */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Busca la ruta que corresponde a la petición.
     *
     * @return array{route:Route,params:array<string,string>}|array{allow:string[]}|null
     *         Ruta con parámetros; o métodos permitidos (405); o null (404).
     */
    public function match(Request $request): ?array
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            if (preg_match($route->regex, $request->path, $matches) === 1) {
                if ($route->method === $request->method) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    return ['route' => $route, 'params' => $params];
                }
                $allowed[] = $route->method;
            }
        }

        return $allowed === [] ? null : ['allow' => array_values(array_unique($allowed))];
    }
}
