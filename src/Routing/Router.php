<?php

declare(strict_types=1);

namespace App\Routing;

use App\Utils\Response;

class Router
{
    private array $routes = [];

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function options(string $path, callable|array $handler): void
    {
        $this->addRoute('OPTIONS', $path, $handler, []);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        $this->routes[] = [
            'method'      => $method,
            'path'        => rtrim($path, '/') ?: '/',
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Remove prefixo caso a aplicação esteja servida em subpasta (ex: /public)
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($requestUri, $scriptName)) {
            $requestUri = substr($requestUri, strlen($scriptName));
        }

        $requestUri = rtrim($requestUri, '/') ?: '/';

        $matchedMethod = false;

        foreach ($this->routes as $route) {
            if ($route['path'] === $requestUri) {
                if ($route['method'] === $requestMethod) {
                    $context = [];

                    // Executa Middlewares associados à rota
                    foreach ($route['middlewares'] as $middleware) {
                        $context = array_merge($context, (array) call_user_func($middleware));
                    }

                    // Executa Handler
                    if (is_array($route['handler'])) {
                        [$class, $method] = $route['handler'];
                        $controller = new $class();
                        $controller->$method($context);
                    } else {
                        call_user_func($route['handler'], $context);
                    }
                    return;
                }
                $matchedMethod = true;
            }
        }

        if ($matchedMethod) {
            Response::error('Método HTTP não permitido para esta rota.', 405);
        }

        Response::error('Recurso não encontrado.', 404);
    }
}
