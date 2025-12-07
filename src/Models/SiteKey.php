<?php

namespace Immaginificio\OAuthProxyBridge\Models;

use Immaginificio\OAuthProxyBridge\Core\Database;
use Immaginificio\OAuthProxyBridge\Models\Log;

/**
 * SiteKey model: API key validation for client sites
 *
 * @package Immaginificio\OAuthProxyBridge\Models
 * @since 0.0.1
 */
class SiteKey
{
    /**
     * Validate a site and its API key checking DB, env and legacy configurations.
     * Sources (in order): `SITE_KEYS` env var (JSON map), `config/config.php` constant SITE_KEYS, legacy `_legacy` mapping.
     *
     * @param string $site
     * @param string $apiKey
     * @return bool
     * @since 0.0.1
     */
    public static function validate(string $site, string $apiKey, ?string $provider = null): bool
    {
        // Normalize site
        $site = rtrim($site, '/');

        // 1) Check DB table if available
        try {
            if (class_exists(Database::class)) {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare('SELECT id, api_key, active, providers FROM site_keys WHERE site_url = :site LIMIT 1');
                $stmt->execute([':site' => $site]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && (int)$row['active'] === 1 && hash_equals($row['api_key'], $apiKey)) {
                    // if provider restriction is provided, enforce it
                    if ($provider !== null) {
                        $providers = [];
                        if (!empty($row['providers'])) {
                            $decoded = json_decode($row['providers'], true);
                            if (is_array($decoded)) $providers = $decoded;
                        }
                        // if providers are configured and the requested provider is not in the list, deny
                        if (!empty($providers) && !in_array($provider, $providers, true)) {
                            // log invalid provider attempt (do not expose full api key)
                                try {
                                    if (class_exists(Log::class)) {
                                        Log::record($site, $provider, 'invalid_provider', ['api_key_last6' => substr($apiKey, -6)], null, (int)$row['id']);
                                    }
                                } catch (\Throwable $e) {
                                // ignore logging errors
                            }
                            return false;
                        }
                    }
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // ignore DB errors; fallback to other sources
        }

        // 2) env SITE_KEYS as JSON
        if (!empty($_ENV['SITE_KEYS'])) {
            $map = json_decode($_ENV['SITE_KEYS'], true);
            if (is_array($map) && isset($map[$site]) && hash_equals($map[$site], $apiKey)) {
                return true;
            }
        }

        // 3) config/site keys defined in config/config.php
        if (defined('SITE_KEYS') && is_array(SITE_KEYS)) {
            if (isset(SITE_KEYS[$site]) && hash_equals(SITE_KEYS[$site], $apiKey)) {
                return true;
            }
        }

        // 4) legacy fallback - do not modify legacy code, but allow read-only check
        $legacy = __DIR__ . '/../../_legacy/config.php';
        if (file_exists($legacy)) {
            $sitichiavi = [];
            try {
                // include in isolated namespace: legacy defines $sitichiavi variable
                include $legacy;
            } catch (\Throwable $e) {
            }
            if (isset($sitichiavi) && is_array($sitichiavi) && isset($sitichiavi[$site]) && hash_equals($sitichiavi[$site], $apiKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the id of a `site_key` given a `site_url` and/or `api_key`.
     * Returns `int` or `null` if not found.
     *
     * @param string|null $site
     * @param string|null $apiKey
     * @return int|null
     */
    public static function findIdBySiteOrApi(?string $site, ?string $apiKey): ?int
    {
        if (empty($site) && empty($apiKey)) {
            return null;
        }
        try {
            $pdo = Database::getConnection();
            if (!empty($site) && !empty($apiKey)) {
                $stmt = $pdo->prepare('SELECT id FROM site_keys WHERE site_url = :site AND api_key = :api_key LIMIT 1');
                $stmt->execute([':site' => rtrim((string)$site, '/'), ':api_key' => (string)$apiKey]);
            } elseif (!empty($site)) {
                $stmt = $pdo->prepare('SELECT id FROM site_keys WHERE site_url = :site LIMIT 1');
                $stmt->execute([':site' => rtrim((string)$site, '/')] );
            } else {
                return null;
            }
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($r && isset($r['id'])) {
                return (int)$r['id'];
            }
        } catch (\Throwable $e) {
            // ignore and return null
        }
        return null;
    }
}
