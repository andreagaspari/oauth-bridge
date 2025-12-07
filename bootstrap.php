<?php
/**
 * Bootstrap dell'applicazione OAuth Proxy Bridge
 * - Carica l'autoload di Composer
 * - Carica le variabili d'ambiente da .env
 * - Inizializza la sessione in modo sicuro
 * - Carica la configurazione di `config/` se presente
 * - Prepara una variabile `$router` (fallback se la classe non è ancora implementata)
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
	// Non interrompiamo l'esecuzione: l'ambiente di sviluppo potrebbe non aver eseguito ancora `composer install`.
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

// Carica config se presente
if (file_exists(__DIR__ . '/config/config.php')) {
	require_once __DIR__ . '/config/config.php';
}

// Sessione sicura (solo se non già avviata)
if (session_status() !== PHP_SESSION_ACTIVE) {
	$cookieParams = session_get_cookie_params();
	session_set_cookie_params([
		'lifetime' => $cookieParams['lifetime'],
		'path' => $cookieParams['path'],
		'domain' => $cookieParams['domain'],
		'secure' => isset($_ENV['SESSION_SECURE']) ? filter_var($_ENV['SESSION_SECURE'], FILTER_VALIDATE_BOOLEAN) : (!empty($_SERVER['HTTPS'])),
		'httponly' => true,
		'samesite' => 'Lax'
	]);
	session_start();
}

// Prepara una variabile router: se la classe Core\Router non esiste ancora, fornisco un fallback minimal
/** @var Router|null $router */
$router = null; // typed via use Router where available
if (class_exists(Router::class)) {
	$router = new Router();
} else {
	// Fallback/router minimale che evita fatal error quando si esegue l'app prima dell'implementazione completa
	$router = new class {
		public function dispatch()
		{
			header($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1' . ' 503 Service Unavailable', true, 503);
			echo 'Router non ancora implementato. Completa l\'implementazione in `src/Core/Router.php`.';
		}
	};
}

// Carica le rotte se presenti
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
