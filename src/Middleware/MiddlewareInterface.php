<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;

/**
 * Interface for middleware
 *
 * @package Immaginificio\OAuthProxyBridge\Middleware
 * @since 0.0.1
 */
interface MiddlewareInterface
{
    /**
     * Handle the request. Return true to continue, false to stop the request.
     *
     * @param Request $request
     * @return bool
     */
    public function handle(Request $request): bool;
}
