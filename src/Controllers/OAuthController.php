<?php

namespace Immaginificio\OAuthProxyBridge\Controllers;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Core\Response;
use Immaginificio\OAuthProxyBridge\Models\SiteKey;
use Immaginificio\OAuthProxyBridge\Services\ServiceManager;
use Immaginificio\OAuthProxyBridge\Core\Session;
use Immaginificio\OAuthProxyBridge\Models\Log;
use Immaginificio\OAuthProxyBridge\Core\Database;

/**
 * Controller for OAuth endpoints
 *
 * @package Immaginificio\OAuthProxyBridge\Controllers
 * @since 0.0.1
 */
class OAuthController
{
    protected ServiceManager $services;

    /**
     * Construct the OAuth controller and initialize the ServiceManager.
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        $this->services = new ServiceManager();
    }

    /**
     * Start the authorization flow for a provider.
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     * @since 0.0.1
     */
/**
 * start
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $params
 * @return mixed
 * @since 0.0.1
 */
    public function start(Request $request, Response $response, array $params): void
    {
        $provider = $params['provider'] ?? null;
        $site = $request->post('site', $request->get('site'));
        $apiKey = $request->post('api_key_server', $request->get('api_key_server'));

        if (!$provider || !$site || !$apiKey) {
            $response->status(400)->send('Missing parameters');
            return;
        }

        if (!SiteKey::validate(rtrim((string)$site, '/'), (string)$apiKey, $provider)) {
            $response->status(403)->send('API key validation failed');
            return;
        }

        $service = $this->services->get($provider);
        if (!$service) {
            $response->status(404)->send('Provider not found');
            return;
        }

        // Generate state and store in session via Session helper
        $state = bin2hex(random_bytes(16));
        Session::set('oauth2state', $state);
        Session::set('site', rtrim((string)$site, '/'));
        Session::set('provider', $provider);

        // Log the oauth start attempt (don't include full state or API keys)
        try {
            $sid = session_id();
            Log::record(rtrim((string)$site, '/'), $provider, 'oauth_start', [
                'state_last6' => substr($state, -6),
                'session_id_last8' => $sid ? substr($sid, -8) : null,
            ]);
        } catch (\Throwable $e) {
            // silently ignore logging errors
        }

        $authUrl = $service->getAuthUrl(['state' => $state]);

        // Redirect user to provider auth URL
        $response->redirect($authUrl);
    }

    /**
        * Provider callback endpoint.
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     * @since 0.0.1
     */
    public function callback(Request $request, Response $response, array $params = []): void
    {
        $state = $request->get('state') ?? null;
        $code = $request->get('code') ?? null;

        if (!$state || !$code) {
            $response->status(400)->send('Invalid callback request');
            return;
        }

        $storedState = Session::get('oauth2state');
        if ($storedState === null || $state !== $storedState) {
            // Log invalid state event for debugging/audit (include session id and stored state's suffix)
            try {
                $siteLogged = Session::get('site') ?? null;
                $providerLogged = Session::get('provider') ?? $params['provider'] ?? null;
                $sid = session_id();
                Log::record($siteLogged, $providerLogged, 'oauth_callback_invalid_state', [
                    'received_state_last6' => substr((string)$state, -6),
                    'stored_state_last6' => $storedState ? substr((string)$storedState, -6) : null,
                    'session_id_last8' => $sid ? substr($sid, -8) : null,
                ]);
            } catch (\Throwable $e) {
                // ignore logging errors
            }

            Session::remove('oauth2state');
            $response->status(400)->send('Invalid state, possible CSRF');
            return;
        }

        $site = Session::get('site');
        $provider = Session::get('provider') ?? 'google';
        $service = $this->services->get($provider);
        $tokenData = $service->exchangeCode(['code' => $code]);

        if (isset($tokenData['error'])) {
            try {
                Log::record($site ?? null, $provider ?? null, 'oauth_callback_token_error', ['error' => $tokenData['error'] ?? 'unknown']);
            } catch (\Throwable $e) {
            }
            $response->status(500)->send('Token exchange error');
            return;
        }

        // Successful token exchange: log the event (do not store tokens in logs)
        try {
            Log::record($site ?? null, $provider ?? null, 'oauth_callback_success', ['has_access_token' => !empty($tokenData['access_token']) ? 1 : 0]);
        } catch (\Throwable $e) {
        }

        // Handoff to client site: use POST auto-submit to avoid tokens in query string
        $access = htmlspecialchars($tokenData['access_token'] ?? '', ENT_QUOTES, 'UTF-8');
        $refresh = htmlspecialchars($tokenData['refresh_token'] ?? '', ENT_QUOTES, 'UTF-8');
        // Prefer nonce stored in session, but fall back to any client_wpnonce saved with the start token (by state)
        $wpnonce = Session::get('wpnonce') ?? '';
        if (empty($wpnonce)) {
            try {
                $db = Database::getConnection();
                    $stmt = $db->prepare('SELECT client_wpnonce, client_server_secret FROM oauth_start_tokens WHERE state = :state ORDER BY created_at DESC LIMIT 1');
                    $stmt->execute([':state' => $state]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row) {
                        if (!empty($row['client_wpnonce'])) {
                            $wpnonce = $row['client_wpnonce'];
                        }
                        $clientServerSecret = !empty($row['client_server_secret']) ? $row['client_server_secret'] : null;
                    }
            } catch (\Throwable $e) {
                // ignore DB lookup errors, leave wpnonce empty
            }
        }
        $callbackUrl = rtrim((string)$site, '/') . '/wp-admin/admin-post.php?action=imm_google_business_profile_api_oauth_callback';

        // Prepare the POST payload that will be sent to the client callback
        $post = [
            'access_token' => $tokenData['access_token'] ?? '',
            'refresh_token' => $tokenData['refresh_token'] ?? '',
            '_wpnonce' => $wpnonce,
        ];

        // Build the minimal auto-submitting HTML fallback (do not send yet).
        // This will be returned only if server->server POST is not possible or fails.
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>OAuth callback</title></head><body>'; 
        $html .= '<form id="oauthForm" method="POST" action="' . htmlspecialchars($callbackUrl) . '">';
        foreach ($post as $k => $v) {
            $html .= '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
        }
        $html .= '</form>';
        $html .= '<script>document.getElementById("oauthForm").submit();</script>';
        $html .= '</body></html>';

        // If we have a server secret provided by the client site, try a server->server POST to the WP callback
        if (!empty($clientServerSecret)) {
            try {
                $postUrl = $callbackUrl;
                $postData = [
                    'access_token' => $tokenData['access_token'] ?? '',
                    'refresh_token' => $tokenData['refresh_token'] ?? '',
                    'api_key_server' => $clientServerSecret,
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $postUrl);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                // execute
                $bodyResp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                try {
                    Log::record($site ?? null, $provider ?? null, 'oauth_callback_serverpost', ['http_code' => $httpCode]);
                } catch (\Throwable $e) {
                }

                // Consider 2xx/3xx responses success: redirect browser to WP admin confirmation page
                if ($httpCode >= 200 && $httpCode < 400) {
                    $successRedirect = rtrim((string)$site, '/') . '/wp-admin/themes.php?page=imm-theme-settings&tab=recensioni&gbp_connected=1';
                    $response->redirect($successRedirect);
                    return;
                }
                // otherwise fall through and render the manual form so developer can inspect
            } catch (\Throwable $e) {
                // ignore and fall back to form auto-submit
            }
        }

        $response->send($html);
    }

    /**
        * Refresh endpoint: exchange a refresh token for new access tokens.
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     * @since 0.0.1
     */
/**
 * refresh
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $params
 * @return mixed
 * @since 0.0.1
 */
    public function refresh(Request $request, Response $response, array $params): void
    {
        $provider = $params['provider'] ?? null;
        $refreshToken = $request->post('refresh_token', null);
        $site = $request->post('site', $request->get('site'));
        $apiKey = $request->post('api_key_server', $request->get('api_key_server'));

        if (!$provider || !$refreshToken || !$site || !$apiKey) {
            $response->status(400)->send('Missing parameters');
            return;
        }

        if (!SiteKey::validate(rtrim((string)$site, '/'), (string)$apiKey, $provider)) {
            $response->status(403)->send('API key validation failed');
            return;
        }

        $service = $this->services->get($provider);
        if (!$service) {
            $response->status(404)->send('Provider not found');
            return;
        }

        $token = $service->refreshToken((string)$refreshToken);
        $response->json($token);
    }

    /**
     * Server->server: create a one-time start token. Returns JSON with `token` and `expires_at`.
     * Requires valid `site`, `provider` and server-side `api_key_server` (validated via SiteKey).
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     * @since 0.0.1
     */
    public function createStartToken(Request $request, Response $response, array $params = []): void
    {
        $site = $request->post('site', $request->get('site'));
        $provider = $request->post('provider', $request->get('provider')) ?? 'google';
        $apiKey = $request->post('api_key_server', $request->get('api_key_server'));

        if (!$site || !$apiKey) {
            $response->status(400)->json(['ok' => false, 'error' => 'Missing parameters']);
            return;
        }

        if (!SiteKey::validate(rtrim((string)$site, '/'), (string)$apiKey, $provider)) {
            $response->status(403)->json(['ok' => false, 'error' => 'API key validation failed']);
            return;
        }

        // Generate a one-time token and a state for OAuth
        $token = bin2hex(random_bytes(32)); // 64 chars
        $state = bin2hex(random_bytes(16));
        // Accept optional client-provided WP nonce (so the bridge can restore it in browser session)
        $clientWpnonce = $request->post('client_wpnonce', $request->get('client_wpnonce')) ?: ($request->post('_wpnonce', $request->get('_wpnonce')) ?? null);
        // Accept optional client-provided server secret so the bridge can later POST server->server
        $clientServerSecret = $request->post('api_key_server', $request->get('api_key_server')) ?: null;
        $created = (new \DateTime('now'))->format('Y-m-d H:i:s');
        $expires = (new \DateTime('now'))->add(new \DateInterval('PT5M'))->format('Y-m-d H:i:s');
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('INSERT INTO oauth_start_tokens (token, site, provider, state, client_wpnonce, client_server_secret, created_at, expires_at, used, client_ip) VALUES (:token, :site, :provider, :state, :client_wpnonce, :client_server_secret, :created_at, :expires_at, 0, :client_ip)');
            $stmt->execute([
                ':token' => $token,
                ':site' => rtrim((string)$site, '/'),
                ':provider' => $provider,
                ':state' => $state,
                ':client_wpnonce' => $clientWpnonce,
                ':client_server_secret' => $clientServerSecret,
                ':created_at' => $created,
                ':expires_at' => $expires,
                ':client_ip' => $clientIp,
            ]);

            // Log token creation (safe fields only)
            try {
                Log::record(rtrim((string)$site, '/'), $provider, 'oauth_start_token_created', [
                    'token_last6' => substr($token, -6),
                    'state_last6' => substr($state, -6),
                ]);
            } catch (\Throwable $e) {
            }

            $response->json(['ok' => true, 'token' => $token, 'expires_at' => $expires]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['ok' => false, 'error' => 'db_error']);
        }
    }

    /**
     * Browser-facing: consume a one-time token, create a session with oauth2state and redirect to provider.
     * GET /start/token?token=...
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     */
    public function consumeStartToken(Request $request, Response $response, array $params = []): void
    {
        $token = $request->get('token') ?? null;
        if (!$token) {
            $response->status(400)->send('Missing token');
            return;
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('SELECT token, site, provider, state, expires_at, used FROM oauth_start_tokens WHERE token = :token LIMIT 1');
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $response->status(404)->send('Token not found');
                return;
            }

            if (!empty($row['used'])) {
                $response->status(400)->send('Token already used');
                return;
            }

            $now = new \DateTime('now');
            $expires = new \DateTime($row['expires_at']);
            if ($now > $expires) {
                $response->status(400)->send('Token expired');
                return;
            }

            // Mark token as used (atomic-ish)
            $upd = $db->prepare('UPDATE oauth_start_tokens SET used = 1 WHERE token = :token');
            $upd->execute([':token' => $token]);

            // Create session variables for browser -> then redirect to provider
            Session::set('oauth2state', $row['state']);
            Session::set('site', $row['site']);
            Session::set('provider', $row['provider']);
            // Restore WP nonce into session so the subsequent POST to admin-post.php contains it
            if (!empty($row['client_wpnonce'])) {
                Session::set('wpnonce', $row['client_wpnonce']);
            }

            // Log consumption (include a safe suffix of the client wpnonce for debugging)
            try {
                Log::record($row['site'], $row['provider'], 'oauth_start_token_consumed', [
                    'token_last6' => substr($token, -6),
                    'state_last6' => substr($row['state'], -6),
                    'wpnonce_last6' => !empty($row['client_wpnonce']) ? substr($row['client_wpnonce'], -6) : null,
                    'session_id_last8' => session_id() ? substr(session_id(), -8) : null,
                ]);
            } catch (\Throwable $e) {
            }

            // Ensure session data is written and cookie headers are sent before redirecting
            if (function_exists('session_write_close')) {
                session_write_close();
            }

            $service = $this->services->get($row['provider']);
            if (!$service) {
                $response->status(404)->send('Provider not found');
                return;
            }

            $authUrl = $service->getAuthUrl(['state' => $row['state']]);
            $response->redirect($authUrl);
        } catch (\Throwable $e) {
            $response->status(500)->send('Server error');
        }
    }
}
