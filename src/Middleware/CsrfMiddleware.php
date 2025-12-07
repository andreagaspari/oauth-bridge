<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Core\Session;

/**
 * CSRF middleware - for UI forms (session-based)
 *
 * @package Immaginificio\OAuthProxyBridge\Middleware
 * @since 0.0.1
 */
class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        // Only validate for state-changing methods
        $method = $request->method();
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return true;
        }

        // token from header or POST body
        $token = null;
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $request->post('_csrf', null);

        return Session::validateCsrfToken($token);
    }
}
