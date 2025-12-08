<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Models\SiteKey;

/**
 * ApiKeyMiddleware: valida site + oauth_bridge_api_key
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
        // Expect the canonical parameter `oauth_bridge_api_key` only.
        $apiKey = $request->post('oauth_bridge_api_key', $request->get('oauth_bridge_api_key'));
        $provider = $request->post('provider', $request->get('provider'));

        if (empty($site) || empty($apiKey)) {
            return false;
        }

        return SiteKey::validate(rtrim((string)$site, '/'), (string)$apiKey, $provider ?: null);
    }
}
