<?php
/**
 * Application configuration loader
 * Reads required values from environment (via Dotenv) and exposes
 * minimal constants to the rest of the codebase for backward compatibility.
 *
 * @since 0.0.1
 * @package Immaginificio\OAuthProxyBridge\Config
 */

// Database
if (!defined('MYSQL_HOST')) {
    define('MYSQL_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
}
if (!defined('MYSQL_PORT')) {
    define('MYSQL_PORT', $_ENV['DB_PORT'] ?? 3306);
}
if (!defined('MYSQL_DATABASE')) {
    define('MYSQL_DATABASE', $_ENV['DB_DATABASE'] ?? 'oauth_bridge');
}
if (!defined('MYSQL_USER')) {
    define('MYSQL_USER', $_ENV['DB_USERNAME'] ?? 'root');
}
if (!defined('MYSQL_PASSWORD')) {
    define('MYSQL_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
}

// Google
if (!defined('GOOGLE_CLIENT_ID')) {
    define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
}
if (!defined('GOOGLE_CLIENT_SECRET')) {
    define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
}
if (!defined('GOOGLE_REDIRECT_URI')) {
    define('GOOGLE_REDIRECT_URI', $_ENV['GOOGLE_REDIRECT_URI'] ?? '');
}

// Admin
if (!defined('ADMIN_TOKEN')) {
    define('ADMIN_TOKEN', $_ENV['ADMIN_TOKEN'] ?? '');
}

// Optional SITE_KEYS constant for compatibility (array)
if (!defined('SITE_KEYS') && !empty($_ENV['SITE_KEYS'])) {
    $decoded = json_decode($_ENV['SITE_KEYS'], true);
    if (is_array($decoded)) {
        define('SITE_KEYS', $decoded);
    }
}

// Load provider configurations from config/providers.php
// This file returns an array of provider configurations; import it and
// expose legacy constants (GOOGLE_CLIENT_ID, etc.) for backward compatibility.
$providersFile = __DIR__ . '/providers.php';
$providers = [];
if (file_exists($providersFile)) {
    $providers = include $providersFile;
}

// Map Google provider values into legacy constants if not already defined
if (is_array($providers) && isset($providers['google'])) {
    $g = $providers['google'];
    if (!defined('GOOGLE_CLIENT_ID')) {
        define('GOOGLE_CLIENT_ID', $g['client_id'] ?? ($_ENV['GOOGLE_CLIENT_ID'] ?? ''));
    }
    if (!defined('GOOGLE_CLIENT_SECRET')) {
        define('GOOGLE_CLIENT_SECRET', $g['client_secret'] ?? ($_ENV['GOOGLE_CLIENT_SECRET'] ?? ''));
    }
    if (!defined('GOOGLE_REDIRECT_URI')) {
        define('GOOGLE_REDIRECT_URI', $g['redirect_uri'] ?? ($_ENV['GOOGLE_REDIRECT_URI'] ?? ''));
    }
}
