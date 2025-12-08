<?php
/**
 * Registrazione rotte principali
 * @since 0.0.1
 */

use Immaginificio\OAuthProxyBridge\Middleware\ApiKeyMiddleware;
use Immaginificio\OAuthProxyBridge\Middleware\AdminAuthMiddleware;
use Immaginificio\OAuthProxyBridge\Core\Database;

/**
 * @var \Immaginificio\OAuthProxyBridge\Core\Router $router
 */

// One-time token flow (server-to-server + browser consume)
// - POST /auth/{provider}/get-start-token
//     Description: server->server call used by remote sites to request a one-time start token.
//     Body params: `site` (string), `oauth_bridge_api_key` (server secret), optional `client_wpnonce`, optional `provider` (defaults to 'google' if omitted)
//     Response: JSON { ok: true, token: string, expires_at: string }
//     Protected by ApiKeyMiddleware (server-to-server).
$router->post('/auth/{provider}/get-start-token', 'OAuthController@createStartToken', [ApiKeyMiddleware::class]);

// - GET /auth/{provider}/start-with-token
//     Description: browser-facing endpoint that consumes the one-time token and initiates the provider redirect.
//     Query params: `token` (one-time token returned by get-start-token)
//     Behaviour: restores session (state/site/provider/wpnonce) and redirects user to provider auth URL.
$router->get('/auth/{provider}/start-with-token', 'OAuthController@consumeStartToken');

// Rotte OAuth Standard (browser-facing and server-to-server)
// - POST /auth/{provider}/start
//     Description: legacy/browser start endpoint that begins the OAuth flow (expects server->server ApiKey when used by remote sites).
$router->post('/auth/{provider}/start', 'OAuthController@start', [ApiKeyMiddleware::class]);

// - GET /callback
//     Description: provider callback endpoint used by OAuth providers to return `code` + `state`. The controller will complete token exchange and POST tokens back to the originating site.
$router->get('/callback', 'OAuthController@callback');

// Refresh token endpoint (server-to-server)
// - POST /auth/{provider}/refresh
//     Description: exchange a refresh_token for new access tokens. Requires `site`, `refresh_token`, and `oauth_bridge_api_key` in POST body.
$router->post('/auth/{provider}/refresh', 'OAuthController@refresh', [ApiKeyMiddleware::class]);

// Root redirect to admin UI
$router->get('/', function($req, $res) {
	$res->redirect('/admin');
});

// Healthcheck / simple ping
$router->get('/ping', function($req, $res) {
	$res->status(200)->send('OK');
});

// Admin UI routes (serve views)
$router->get('/admin/login', function($req, $res) {
	require __DIR__ . '/views/admin/login.php';
});

$router->get('/admin', function($req, $res) {
	require __DIR__ . '/views/admin/dashboard.php';
});

// Admin UI pages for keys/logs (views)
$router->get('/admin/keys/view', function($req, $res) {
	require __DIR__ . '/views/admin/key-list.php';
});
$router->get('/admin/logs/view', function($req, $res) {
	require __DIR__ . '/views/admin/logs.php';
});

// Admin API (protected by AdminAuthMiddleware)
$router->get('/admin/keys', 'AdminController@listKeys', [AdminAuthMiddleware::class]);
// suggestions for keys (name/site) used by autocomplete
$router->get('/admin/keys/suggestions', 'AdminController@suggestKeys', [AdminAuthMiddleware::class]);
$router->post('/admin/keys', 'AdminController@createKey', [AdminAuthMiddleware::class]);
$router->post('/admin/keys/{id}', 'AdminController@updateKey', [AdminAuthMiddleware::class]);
$router->post('/admin/keys/{id}/delete', 'AdminController@deleteKey', [AdminAuthMiddleware::class]);
// show/regenerate/revoke endpoints for keys
$router->get('/admin/keys/{id}/show', 'AdminController@showKey', [AdminAuthMiddleware::class]);
$router->post('/admin/keys/{id}/regenerate', 'AdminController@regenerateKey', [AdminAuthMiddleware::class]);
$router->post('/admin/keys/{id}/revoke', 'AdminController@revokeKey', [AdminAuthMiddleware::class]);

// Admin API: logs
$router->get('/admin/logs', 'AdminController@listLogs', [AdminAuthMiddleware::class]);

// Suggestions for logs filters (sources, ips)
$router->get('/admin/logs/suggestions', 'AdminController@suggestLogs', [AdminAuthMiddleware::class]);

// Admin UI: users view
$router->get('/admin/users/view', function($req, $res) {
	require __DIR__ . '/views/admin/users.php';
});

// Admin API: users
$router->get('/admin/users', 'AdminController@listUsers', [AdminAuthMiddleware::class]);
$router->post('/admin/users', 'AdminController@createUser', [AdminAuthMiddleware::class]);
$router->post('/admin/users/{id}', 'AdminController@updateUser', [AdminAuthMiddleware::class]);
$router->post('/admin/users/{id}/delete', 'AdminController@deleteUser', [AdminAuthMiddleware::class]);

// Auth routes for admin
$router->post('/admin/login', 'AuthController@login');
$router->post('/admin/logout', 'AuthController@logout');
$router->post('/admin/password-reset/request', 'AuthController@requestPasswordReset');
$router->post('/admin/password-reset/confirm', 'AuthController@confirmPasswordReset');

