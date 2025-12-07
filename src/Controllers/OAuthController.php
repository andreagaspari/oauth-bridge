<?php

namespace Immaginificio\OAuthProxyBridge\Controllers;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Core\Response;
use Immaginificio\OAuthProxyBridge\Models\SiteKey;
use Immaginificio\OAuthProxyBridge\Services\ServiceManager;
use Immaginificio\OAuthProxyBridge\Core\Session;

/**
 * Controller per gli endpoint OAuth
 *
 * @package Immaginificio\OAuthProxyBridge\Controllers
 * @since 0.0.1
 */
class OAuthController
{
    protected ServiceManager $services;

    /**
     * Costruisce il controller OAuth e inizializza il ServiceManager.
     *
     * @since 0.0.1
     */
    public function __construct()
    {
        $this->services = new ServiceManager();
    }

    /**
     * Avvia il flusso di autorizzazione per il provider.
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

        $authUrl = $service->getAuthUrl(['state' => $state]);

        // Redirect user to provider auth URL
        $response->redirect($authUrl);
    }

    /**
     * Callback endpoint per il provider.
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

        if (Session::get('oauth2state') === null || $state !== Session::get('oauth2state')) {
            Session::remove('oauth2state');
            $response->status(400)->send('Invalid state, possible CSRF');
            return;
        }

        $site = Session::get('site');
        $provider = Session::get('provider') ?? 'google';
        $service = $this->services->get($provider);
        $tokenData = $service->exchangeCode(['code' => $code]);

        if (isset($tokenData['error'])) {
            $response->status(500)->send('Token exchange error');
            return;
        }

        // Handoff to client site: use POST auto-submit to avoid tokens in query string
        $access = htmlspecialchars($tokenData['access_token'] ?? '', ENT_QUOTES, 'UTF-8');
        $refresh = htmlspecialchars($tokenData['refresh_token'] ?? '', ENT_QUOTES, 'UTF-8');
        $wpnonce = Session::get('wpnonce') ?? '';
        $callbackUrl = rtrim((string)$site, '/') . '/wp-admin/admin-post.php?action=imm_google_business_profile_api_oauth_callback';

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>OAuth Callback</title></head><body>';
        $html .= '<form id="oauthForm" method="post" action="' . $callbackUrl . '">';
        $html .= '<input type="hidden" name="access_token" value="' . $access . '">';
        $html .= '<input type="hidden" name="refresh_token" value="' . $refresh . '">';
        $html .= '<input type="hidden" name="_wpnonce" value="' . htmlspecialchars($wpnonce, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '</form>';
        $html .= '<script>document.getElementById("oauthForm").submit();</script>';
        $html .= '</body></html>';

        $response->send($html);
    }

    /**
     * Refresh endpoint: scambia refresh token per nuovi access token.
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
}
