<?php

namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * HTTP Request abstraction
 *
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Request
{
    protected array $get;
    protected array $post;
    protected array $server;
    protected $rawBody;
    /**
     * Request constructor.
        * Initialize GET/POST/SERVER arrays and read the raw request body.
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        $this->get = $_GET ?? [];
        $this->post = $_POST ?? [];
        $this->server = $_SERVER ?? [];
        $this->rawBody = file_get_contents('php://input');
    }

    /**
        * Return the HTTP method of the request (UPPERCASE).
     *
     * @return string
     * @since 0.0.1
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
        * Return the request path (without query string).
     *
     * @return string
     * @since 0.0.1
     */
    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $pos = strpos($uri, '?');
        return $pos === false ? $uri : substr($uri, 0, $pos);
    }

    /**
        * Retrieve a value from the query string ($_GET).
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     * @since 0.0.1
     */
    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    /**
        * Retrieve a value from $_POST.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     * @since 0.0.1
     */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /**
        * Merge GET and POST into a single associative array.
     *
     * @return array
     * @since 0.0.1
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    /**
        * Interpret the raw request body as JSON and return it as an array.
     *
     * @return array|null
     * @since 0.0.1
     */
    public function json(): ?array
    {
        $data = json_decode($this->rawBody, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Determines if the client accepts HTML responses (checks the Accept header).
     * Treats "/" as accepting HTML.
     *
     * @return bool
     * @since 0.0.1
     */
    public function acceptsHtml(): bool
    {
        $accept = $this->server['HTTP_ACCEPT'] ?? '';
        if ($accept === '') {
            // No Accept header: assume HTML in typical browser requests
            return true;
        }
        $accept = strtolower($accept);
        if (strpos($accept, '*/*') !== false) {
            return true;
        }
        return strpos($accept, 'text/html') !== false;
    }
}
