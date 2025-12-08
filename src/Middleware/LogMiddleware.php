<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Models\Log as LogModel;
use Immaginificio\OAuthProxyBridge\Models\SiteKey;
use Immaginificio\OAuthProxyBridge\Core\Auth;

/**
 * LogMiddleware: registra chiamate importanti sui log DB
 *
 * @package Immaginificio\OAuthProxyBridge\Middleware
 * @since 0.0.1
 */
class LogMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        try {
            $method = $request->method();
            $path = $request->path();
            // Only log a small set of non-navigation actions.
            // Avoid recording simple navigation (GET) or admin route reads
            // because controllers already record create/update/delete actions.
            if (strpos($path, '/admin') === 0) {
                return true;
            }
            if ($method === 'GET') {
                return true;
            }
            // Keep logging only for callback endpoints (non-GET).
            // Skip `/auth` endpoints because controllers log auth events explicitly.
            if (strpos($path, '/callback') === false) {
                return true;
            }
                $payload = $request->all();
                // remove potentially sensitive fields
                if (isset($payload['access_token'])) {
                    unset($payload['access_token']);
                }
                if (isset($payload['refresh_token'])) {
                    unset($payload['refresh_token']);
                }
                // try to provide explicit user_id and site_key_id when possible
                $actorId = Auth::currentAdminId();

                $siteKeyId = null;
                $siteParam = $payload['site'] ?? $payload['site_url'] ?? null;
                // Use canonical `oauth_bridge_api_key` only
                $apiKeyParam = $payload['oauth_bridge_api_key'] ?? $payload['api_key'] ?? null;
                if ($siteParam || $apiKeyParam) {
                    try {
                        $siteKeyId = SiteKey::findIdBySiteOrApi($siteParam, $apiKeyParam);
                    } catch (\Throwable $e) {
                        // ignore lookup errors
                    }
                }

                // determine provider: prefer explicit payload provider, else infer from route
                $provider = $payload['provider'] ?? null;
                if (!$provider) {
                    if (strpos($path, '/auth') === 0) {
                        $provider = 'auth';
                    } elseif (strpos($path, '/callback') !== false) {
                        $provider = 'callback';
                    } else {
                        $provider = 'generic';
                    }
                }

                // Record only higher-value write events (non-GET) that are not admin read navigation.
                LogModel::record($payload['site'] ?? null, $provider, $method . ' ' . $path, $payload, $actorId, $siteKeyId);
        } catch (\Throwable $e) {
            // don't block the request on logging failures
            error_log('LogMiddleware error: ' . $e->getMessage());
        }
        return true;
    }
}
