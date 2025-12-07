<?php
/**
 * OAuth 2.0 Bridge - Front Controller
 *
 * Entry point dell'applicazione. Gestisce tutte le richieste HTTP,
 * inizializza il sistema tramite bootstrap.php e delega il flusso
 * al Router principale.
 *
 * @package   OAuthProxyBridge
 * @author    Andrea Gaspari
 * @license   GPL-3.0-or-later
 * @version   0.0.1
 */

require __DIR__ . '/bootstrap.php';

/** @var \Immaginificio\OAuthProxyBridge\Core\Router $router */
// Visual debug helper: mostra informazioni diagnostiche solo se APP_DEBUG=1
$appDebug = getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? '0');
if ((string)$appDebug === '1') {
	echo "<pre style=\"white-space:pre-wrap;background:#111;color:#eee;padding:12px;border-radius:6px;\">";
	echo "DEBUG INFO:\n";
	echo "PHP: " . PHP_VERSION . "\n";
	echo "Script: " . __FILE__ . "\n";
	echo "DocumentRoot: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a') . "\n\n";

	// vendor/autoload
	$autoload = __DIR__ . '/vendor/autoload.php';
	echo "Composer autoload: " . (file_exists($autoload) ? 'found' : 'missing') . "\n";

	// Router availability
	if (isset($router)) {
		echo "Router: set (" . (is_object($router) ? get_class($router) : gettype($router)) . ")\n";
	} else {
		echo "Router: not set\n";
	}

	// Show selected env vars (mask sensitive ones)
	$envKeys = ['APP_ENV','APP_DEBUG','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_DSN','GOOGLE_CLIENT_ID','GOOGLE_CLIENT_SECRET'];
	echo "\nENV:\n";
	foreach ($envKeys as $k) {
		$v = getenv($k) ?: ($_ENV[$k] ?? null);
		if ($v === null) {
			echo "$k = (not set)\n";
			continue;
		}
		if (in_array($k, ['DB_PASSWORD','GOOGLE_CLIENT_SECRET'])) {
			echo "$k = " . str_repeat('*', max(4, min(12, strlen($v)))) . "\n";
			continue;
		}
		echo "$k = $v\n";
	}

	// Try DB connection (catch exception and show message)
	echo "\nDB Connection test:\n";
	try {
		if (class_exists(\Immaginificio\OAuthProxyBridge\Core\Database::class)) {
			$pdo = \Immaginificio\OAuthProxyBridge\Core\Database::getConnection();
			echo "PDO connected: OK (driver: " . $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) . ")\n";
		} else {
			echo "Database class not available\n";
		}
	} catch (Throwable $e) {
		echo "DB error: " . $e->getMessage() . "\n";
	}

	echo "</pre>";
}

$router->dispatch();
