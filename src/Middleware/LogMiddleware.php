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
            // Keep logging for callback and auth start/refresh endpoints (non-GET).
            // We'll explicitly include `/auth/{provider}/start`, `/auth/{provider}/refresh` and any `/callback` paths.
            if ($method !== 'POST' && $method !== 'PUT' && $method !== 'DELETE' && $method !== 'PATCH') {
                return true;
            }

            // Only continue for relevant endpoints
            $isAuthStartOrRefresh = preg_match('#^/auth/([^/]+)/(start|refresh)#', $path);
            $isCallback = (strpos($path, '/callback') !== false);
            if (!$isAuthStartOrRefresh && !$isCallback) {
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

                // determine provider and action: prefer explicit payload provider, else infer from route
                $provider = $payload['provider'] ?? null;
                $action = null;

                if (preg_match('#^/auth/([^/]+)/(start|refresh)#', $path, $m)) {
                    $provider = $m[1];
                    $op = $m[2];
                    if ($op === 'start') {
                        $action = 'oauth_start';
                    } elseif ($op === 'refresh') {
                        $action = 'oauth_refresh_token';
                    }

                    // Replace full api key with last 6 chars only to avoid logging secrets
                    if (isset($payload['oauth_bridge_api_key']) && is_string($payload['oauth_bridge_api_key'])) {
                        $key = $payload['oauth_bridge_api_key'];
                        $payload['api_key_last6'] = substr($key, -6);
                        unset($payload['oauth_bridge_api_key']);
                    } elseif (isset($payload['api_key']) && is_string($payload['api_key'])) {
                        $key = $payload['api_key'];
                        $payload['api_key_last6'] = substr($key, -6);
                        unset($payload['api_key']);
                    }
                } elseif (strpos($path, '/callback') !== false) {
                    if (!$provider) {
                        $provider = 'callback';
                    }
                    $action = 'oauth_callback';
                }

                if (!$action) {
                    // fallback action name (conservative): use a short action token
                    $action = 'write';
                }

                // Record only higher-value write events (non-GET) that are not admin read navigation.
                LogModel::record($payload['site'] ?? null, $provider, $action, $payload, $actorId, $siteKeyId);
        } catch (\Throwable $e) {
            // don't block the request on logging failures
            error_log('LogMiddleware error: ' . $e->getMessage());
        }
        return true;
    }
}
