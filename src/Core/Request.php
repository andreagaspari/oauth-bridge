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
     * Inizializza array GET/POST/SERVER e legge il body raw.
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
     * Restituisce il metodo HTTP della richiesta (MAIUSCOLO).
     *
     * @return string
     * @since 0.0.1
     */
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Restituisce il path della richiesta (senza query string).
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
     * Recupera un valore dalla querystring ($_GET).
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
     * Recupera un valore da $_POST.
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
     * Unisce GET e POST in un array associativo.
     *
     * @return array
     * @since 0.0.1
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    /**
     * Interpreta il body raw come JSON e lo ritorna come array.
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
