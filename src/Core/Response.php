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
     * Imposta lo status code della risposta.
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
     * Aggiunge/aggiorna un header da inviare.
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
     * Invia una risposta JSON e termina l'esecuzione.
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
     * Esegue un redirect HTTP.
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
     * Invia contenuto testuale come body della risposta.
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
