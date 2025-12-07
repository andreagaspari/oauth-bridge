<?php
/**
 * Registrazione rotte principali
 * @since 0.0.1
 */

use Immaginificio\OAuthProxyBridge\Core\Router;
use Immaginificio\OAuthProxyBridge\Middleware\ApiKeyMiddleware;
use Immaginificio\OAuthProxyBridge\Middleware\AdminAuthMiddleware;
use Immaginificio\OAuthProxyBridge\Core\Database;

// $router è creato in bootstrap.php
/**
 * @var \Immaginificio\OAuthProxyBridge\Core\Router $router
 */
// Rotte OAuth
$router->post('/auth/{provider}/start', 'OAuthController@start', [ApiKeyMiddleware::class]);
$router->get('/callback', 'OAuthController@callback');
$router->post('/auth/{provider}/refresh', 'OAuthController@refresh', [ApiKeyMiddleware::class]);
// One-time token flow: server->server creates a token, browser consumes it
$router->post('/api/start-token', 'OAuthController@createStartToken', [ApiKeyMiddleware::class]);
$router->get('/start/token', 'OAuthController@consumeStartToken');

// Root redirect to admin UI
$router->get('/', function($req, $res) {
	$res->redirect('/admin');
});

// Healthcheck / simple ping
$router->get('/ping', function($req, $res) {
	$res->status(200)->send('OK');
});

// Debug: verifica connessione al DB tramite Core\Database
$router->get('/dbcheck', function($req, $res) {
	try {
			if (!class_exists(Database::class)) {
				throw new \RuntimeException('Database class not available');
			}
			$pdo = Database::getConnection();
		$drivers = \PDO::getAvailableDrivers();
		$res->status(200)->json(['ok' => true, 'drivers' => $drivers]);
	} catch (\Throwable $e) {
		$res->status(500)->json(['ok' => false, 'error' => $e->getMessage()]);
	}
});

// Admin routes (placeholder)
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

