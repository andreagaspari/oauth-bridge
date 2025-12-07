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
        * Retrieve a value from the session.
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
        * Set a value in the session.
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
        * Check if a key exists in the session.
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
        * Remove a key from the session.
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
        * Generate (or return) a CSRF token stored in the session.
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
        * Validate a CSRF token against the session token.
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
