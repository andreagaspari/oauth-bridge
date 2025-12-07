<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Models\SiteKey;

/**
 * ApiKeyMiddleware: valida site + api_key_server nei parametri della richiesta
 *
 * @package Immaginificio\OAuthProxyBridge\Middleware
 * @since 0.0.1
 */
class ApiKeyMiddleware
{
    /**
     * Handle request and return true if allowed, false to stop
     *
     * @param Request $request
     * @return bool
     */
    public function handle(Request $request): bool
    {
        $site = $request->post('site', $request->get('site'));
        $apiKey = $request->post('api_key_server', $request->get('api_key_server'));
        $provider = $request->post('provider', $request->get('provider'));

        if (empty($site) || empty($apiKey)) {
            return false;
        }

        return SiteKey::validate(rtrim((string)$site, '/'), (string)$apiKey, $provider ?: null);
    }
}
