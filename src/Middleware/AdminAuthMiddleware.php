<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Core\Auth;

/**
 * Simple admin auth middleware using ADMIN_TOKEN Bearer header
 *
 * @package Immaginificio\OAuthProxyBridge\Middleware
 * @since 0.0.1
 */
class AdminAuthMiddleware
{
    public function handle(Request $request): bool
    {
        // 1) If user session is present via Auth helper, allow
        if (Auth::check()) {
            return true;
        }

        // 2) Fallback to Authorization Bearer token for API clients
        $headers = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? null;
        if (!$headers) {
            $headers = $request->get('authorization') ?? null;
        }

        if ($headers && stripos($headers, 'Bearer ') === 0) {
            $token = trim(substr($headers, 7));
            $adminToken = $_ENV['ADMIN_TOKEN'] ?? (defined('ADMIN_TOKEN') ? ADMIN_TOKEN : null);
            if (!empty($adminToken) && hash_equals($adminToken, (string)$token)) {
                return true;
            }
        }

        return false;
    }
}
