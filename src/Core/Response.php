<?php

namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * HTTP Response helper
 *
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected string $body = '';
    /**
        * Set the HTTP status code for the response.
        *
        * @param int $code
        * @return $this
        * @since 0.0.1
        */
    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    /**
        * Add or update a header to be sent.
        *
        * @param string $name
        * @param string $value
        * @return $this
        * @since 0.0.1
        */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
        * Send a JSON response and terminate execution.
        *
        * @param mixed $data
        * @return void
        * @since 0.0.1
        */
    public function json($data): void
    {
        $this->header('Content-Type', 'application/json; charset=utf-8');
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        echo json_encode($data);
    }

    /**
        * Perform an HTTP redirect.
        *
        * @param string $url
        * @param int $status
        * @return void
        * @since 0.0.1
        */
    public function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    /**
        * Send textual content as the response body.
        *
        * @param string $body
        * @return void
        * @since 0.0.1
        */
    public function send(string $body): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        echo $body;
    }
}
