<?php
/**
 * Bootstrap file for OAuth Proxy Bridge
 * This file initializes the application by:
 * - Loading Composer's autoload
 * - Loading environment variables from .env
 * - Starting a secure session
 * - Loading configuration from `config/` if available
 * - Preparing a `$router` variable (fallback if the class is not yet implemented)
 *
 * @since 0.0.1
 * @package OAuthProxyBridge
 */

declare(strict_types=1);

use Immaginificio\OAuthProxyBridge\Core\Router;
use Immaginificio\OAuthProxyBridge\Middleware\RateLimitMiddleware;
use Immaginificio\OAuthProxyBridge\Middleware\LogMiddleware;

// Autoload (Composer)
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
	require $vendorAutoload;
} else {
	// Do not interrupt execution: the development environment might not have run `composer install` yet.
	error_log('Composer autoload non trovato. Esegui `composer install`.');
}

// Dotenv
if (class_exists(\Dotenv\Dotenv::class)) {
	try {
		$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
		$dotenv->safeLoad();
	} catch (Throwable $e) {
		error_log('Errore nel caricamento di .env: ' . $e->getMessage());
	}
}

// Load configuration if available
if (file_exists(__DIR__ . '/config/config.php')) {
	require_once __DIR__ . '/config/config.php';
}

// Secure session (only if not already started)
if (session_status() !== PHP_SESSION_ACTIVE) {
	$cookieParams = session_get_cookie_params();
	// Choose secure flag: prefer explicit env var, fall back to HTTPS detection (including proxy header)
	$secure = isset($_ENV['SESSION_SECURE']) ? filter_var($_ENV['SESSION_SECURE'], FILTER_VALIDATE_BOOLEAN) : (!empty($_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'));
	// Choose SameSite depending on whether cookie is Secure.
	// Browsers require SameSite=None to be paired with Secure=true; use 'Lax' for local/dev HTTP.
	$samesite = $secure ? 'None' : 'Lax';
	session_set_cookie_params([
		'lifetime' => $cookieParams['lifetime'],
		'path' => $cookieParams['path'],
		'domain' => $cookieParams['domain'],
		'secure' => $secure,
		'httponly' => true,
		'samesite' => $samesite
	]);
	session_start();
}

// Prepare a router variable: if the Core\Router class does not exist yet, provide a minimal fallback
/** @var Router|null $router */
$router = null; // typed via use Router where available
if (class_exists(Router::class)) {
	$router = new Router();
} else {
	// Minimal fallback router to avoid fatal error when running the app before full implementation
	$router = new class {
		public function dispatch()
		{
			header($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1' . ' 503 Service Unavailable', true, 503);
			echo 'Router non ancora implementato. Completa l\'implementazione in `src/Core/Router.php`.';
		}
	};
}

// Load routes if available
if (file_exists(__DIR__ . '/routes.php')) {
	require_once __DIR__ . '/routes.php';
}

// Register some default global middleware (rate limit, logging) if available
if (isset($router) && method_exists($router, 'addGlobalMiddleware')) {
	$global = [];
	if (class_exists(RateLimitMiddleware::class)) {
		$global[] = RateLimitMiddleware::class;
	}
	if (class_exists(LogMiddleware::class)) {
		$global[] = LogMiddleware::class;
	}
	if (!empty($global)) {
		$router->addGlobalMiddleware($global);
	}
}
