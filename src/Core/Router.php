<?php

namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * Router core semplice
 *
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Router
{
    protected array $routes = [];
    protected array $globalMiddleware = [];

    /**
     * Register a GET route.
     *
     * @param string $path
     * @param callable|string $handler
     * @param array $middleware
     * @return $this
     * @since 0.0.1
     */
    public function get(string $path, $handler, array $middleware = []): self
    {
        $this->add('GET', $path, $handler, $middleware);
        return $this;
    }

    /**
     * Register a POST route.
     *
     * @param string $path
     * @param callable|string $handler
     * @param array $middleware
     * @return $this
     * @since 0.0.1
     */
    public function post(string $path, $handler, array $middleware = []): self
    {
        $this->add('POST', $path, $handler, $middleware);
        return $this;
    }

    /**
     * Register a global middleware or list of middleware to apply to all routes.
     * @param string|array $middleware
     * @return $this
     */
    public function addGlobalMiddleware($middleware): self
    {
        if (is_array($middleware)) {
            foreach ($middleware as $m) {
                $this->globalMiddleware[] = $m;
            }
        } else {
            $this->globalMiddleware[] = $middleware;
        }
        return $this;
    }
/**
 * add
 *
 * @param mixed $method
 * @param mixed $path
 * @param mixed $handler
 * @param mixed $middleware
 * @return mixed
 * @since 0.0.1
 */

    protected function add(string $method, string $path, $handler, array $middleware = []): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    /**
     * Dispatch the incoming HTTP request: match a route, run middleware and call the handler.
     *
     * @return void
     * @since 0.0.1
     */
    public function dispatch(): void
    {
        $request = new Request();
        $response = new Response();

        $method = $request->method();
        $uri = rtrim($request->path(), '/');
        if ($uri === '') {
            $uri = '/';
        }

        $allowedMethodsForUri = [];
        $matchedAny = false;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                // keep scanning to detect 405 (method not allowed)
                $pattern = $this->convertPathToRegex($route['path']);
                if (preg_match($pattern, $uri)) {
                    $allowedMethodsForUri[] = $route['method'];
                    $matchedAny = true;
                }
                continue;
            }
            $pattern = $this->convertPathToRegex($route['path']);
            if (preg_match($pattern, $uri, $matches)) {
                $params = $this->extractParams($matches);

                // Prepare handler callable
                $callable = $this->resolveHandler($route['handler']);

                // Execute global middleware first
                foreach ($this->globalMiddleware as $mw) {
                    if (is_string($mw) && class_exists($mw)) {
                        $mwInstance = new $mw();
                        if (method_exists($mwInstance, 'handle')) {
                            $ok = $mwInstance->handle($request);
                            if ($ok === false) {
                                $this->renderError($response, $request, 403, 'Forbidden');
                                return;
                            }
                        }
                    }
                }

                // Execute route middleware (in configured order)
                foreach ($route['middleware'] as $mw) {
                    if (is_string($mw) && class_exists($mw)) {
                        $mwInstance = new $mw();
                        if (method_exists($mwInstance, 'handle')) {
                            $ok = $mwInstance->handle($request);
                            if ($ok === false) {
                                $this->renderError($response, $request, 403, 'Forbidden');
                                return;
                            }
                        }
                    }
                }

                // Call controller
                if (is_callable($callable)) {
                    call_user_func($callable, $request, $response, $params);
                    return;
                }
            }
        }

        if (!empty($allowedMethodsForUri) && $matchedAny) {
            $allow = implode(', ', array_unique($allowedMethodsForUri));
            header('Allow: ' . $allow);
            $response->status(405)->send('Method Not Allowed');
            return;
        }

        $this->renderError($response, $request, 404, 'Not Found');
    }

    /**
     * Render an error response. If the client accepts HTML and a matching
     * error template exists in `views/errors/`, include it; otherwise send
     * a plain text response (suitable for API clients).
     *
     * @param Response $response
     * @param Request $request
     * @param int $code
     * @param string $text
     * @return void
     * @since 0.0.1
     */
    protected function renderError(Response $response, Request $request, int $code, string $text): void
    {
        // set status code for PHP/response
        http_response_code($code);

        // Resolve path to views/errors/<code>.php
        $viewsFile = dirname(__DIR__, 2) . '/views/errors/' . $code . '.php';

        if ($request->acceptsHtml() && file_exists($viewsFile)) {
            // include the error template (server-side include)
            include $viewsFile;
            return;
        }

        // Default fallback for non-HTML clients
        $response->status($code)->send($text);
    }

    /**
     * Convert a route path with `{param}` placeholders into a PCRE regex with named captures.
     *
     * @param string $path
     * @return string Regex pattern
     * @since 0.0.1
     */
    protected function convertPathToRegex(string $path): string
    {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        // Replace {param} with named capture groups
        $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '$#';
    }
/**
 * extractParams
 *
 * @param mixed $matches
 * @return mixed
 * @since 0.0.1
 */

    protected function extractParams(array $matches): array
    {
        $params = [];
        foreach ($matches as $key => $value) {
            if (!is_int($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }
/**
 * resolveHandler
 *
 * @param mixed $handler
 * @return mixed
 * @since 0.0.1
 */

    protected function resolveHandler($handler)
    {
        // Handler formats supported:
        // - Closure/callable
        // - '\\Full\\Class\\Name::method' string
        // - 'Class@method' short string (assumes Controllers namespace)
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_string($handler)) {
            if (strpos($handler, '::') !== false) {
                return explode('::', $handler);
            }
            if (strpos($handler, '@') !== false) {
                [$class, $method] = explode('@', $handler, 2);
                // assume Controllers namespace
                $fqcn = '\\Immaginificio\\OAuthProxyBridge\\Controllers\\' . $class;
                if (class_exists($fqcn)) {
                    $instance = new $fqcn();
                    return [$instance, $method];
                }
            }
        }
        return null;
    }
}
