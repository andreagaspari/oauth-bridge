<?php

namespace Immaginificio\OAuthProxyBridge\Core;

use Immaginificio\OAuthProxyBridge\Models\User;

/**
 * Auth helper for admin
 *
 * @package Immaginificio\OAuthProxyBridge\Core
 * @since 0.0.1
 */
class Auth
{
    /**
     * Return the current admin user record or null if not logged in.
     *
     * @return array|null
     * @since 0.0.1
     */
    public static function user(): ?array
    {
        $id = Session::get('admin_user_id');
        if (!$id) {
            return null;
        }
        return User::findById((int)$id);
    }

    /**
     * Check whether an admin user is currently authenticated.
     *
     * @return bool
     * @since 0.0.1
     */
    public static function check(): bool
    {
        return (bool)Session::get('admin_user_id');
    }

    /**
     * Log out the current admin user by removing session keys.
     *
     * @return void
     * @since 0.0.1
     */
    public static function logout(): void
    {
        Session::remove('admin_user_id');
        Session::remove('admin_user_email');
    }

    /**
     * Return current admin id or null.
     *
     * @return int|null
     */
    public static function currentAdminId(): ?int
    {
        $id = Session::get('admin_user_id');
        return $id ? (int)$id : null;
    }

    /**
     * Return current admin display name (name or email) or null.
     *
     * @return string|null
     */
    public static function currentAdminName(): ?string
    {
        $name = Session::get('admin_user_name');
        if (!empty($name)) {
            return (string)$name;
        }
        $email = Session::get('admin_user_email');
        return $email ? (string)$email : null;
    }

    /**
     * Actor label used in logs: prefer admin name/email, otherwise 'Admin Token' or null.
     *
     * @return string|null
     */
    public static function currentActorLabel(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $name = static::currentAdminName();
        if (!empty($name)) {
            return $name;
        }
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($auth && stripos($auth, 'Bearer') !== false) {
            return 'Admin Token';
        }
        return null;
    }
}
