<?php

namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * Session helper wrapper
 *
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Session
{
    /**
     * Recupera un valore dalla sessione.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     * @since 0.0.1
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Imposta un valore in sessione.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     * @since 0.0.1
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Verifica se una chiave è presente in sessione.
     *
     * @param string $key
     * @return bool
     * @since 0.0.1
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Rimuove una chiave dalla sessione.
     *
     * @param string $key
     * @return void
     * @since 0.0.1
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Genera (o ritorna) un token CSRF memorizzato in sessione.
     *
     * @return string
     * @since 0.0.1
     */
    public static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Valida un token CSRF confrontandolo con quello in sessione.
     *
     * @param string|null $token
     * @return bool
     * @since 0.0.1
     */
    public static function validateCsrfToken(?string $token): bool
    {
        if (empty($_SESSION['_csrf_token'])) {
            return false;
        }
        if (empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['_csrf_token'], $token);
    }
}
