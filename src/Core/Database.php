<?php

namespace Immaginificio\OAuthProxyBridge\Core;

/**
 * Database connection helper (PDO singleton)
 *
 * @package Immaginificio\OAuthProxyBridge
 * @since 0.0.1
 */
class Database
{
    /**
     * @var \PDO|null
     */
    protected static ?\PDO $pdo = null;

    /**
     * Return a PDO connection (singleton). Reads configuration from environment variables.
     *
     * @return \PDO
     * @since 0.0.1
     */
    public static function getConnection(): \PDO
    {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }

        $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');
        $db = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'oauth_bridge');
        $user = getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root');
        $pass = getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? '');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        self::$pdo = new \PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }
}
