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
        // remove scheme for matching convenience
        $siteNoScheme = preg_replace('#^https?://#i', '', $site);

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

        // If exact DB match failed, try wildcard/pattern matches against active rows
        try {
            if (class_exists(Database::class)) {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare('SELECT id, api_key, active, providers, site_url FROM site_keys WHERE active = 1');
                $stmt->execute();
                while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    if (empty($r['site_url']) || !hash_equals($r['api_key'] ?? '', $apiKey)) {
                        continue;
                    }
                    $pattern = rtrim($r['site_url'], '/');
                    $patternNoScheme = preg_replace('#^https?://#i', '', $pattern);
                    // If pattern contains wildcard, try fnmatch against site without scheme
                    if (strpos($patternNoScheme, '*') !== false) {
                        // build candidate patterns and site variants
                        $patternsToTest = [$patternNoScheme];
                        if (strpos($patternNoScheme, '*.') !== false) {
                            $alt = str_replace('*.', '', $patternNoScheme);
                            if ($alt !== $patternNoScheme) {
                                $patternsToTest[] = $alt;
                            }
                        }

                        // expand candidates (with/without trailing /* and with www. where sensible)
                        $expanded = [];
                        foreach ($patternsToTest as $p) {
                            $expanded[] = $p;
                            if (substr($p, -2) === '/*') {
                                $expanded[] = substr($p, 0, -2);
                            }
                            if (strpos($p, '*') === false && strpos($p, 'www.') !== 0) {
                                $expanded[] = 'www.' . $p;
                                if (substr($p, -2) === '/*') {
                                    $expanded[] = 'www.' . substr($p, 0, -2);
                                }
                            }
                        }

                        // prepare site variants (with and without www.)
                        $siteVariants = [$siteNoScheme];
                        if (strpos($siteNoScheme, 'www.') === 0) {
                            $siteVariants[] = substr($siteNoScheme, 4);
                        } else {
                            $siteVariants[] = 'www.' . $siteNoScheme;
                        }

                        // test all combinations
                        foreach ($expanded as $pat) {
                            foreach ($siteVariants as $siteCandidate) {
                                if (fnmatch($pat, $siteCandidate, FNM_CASEFOLD)) {
                                    // provider restriction check
                                    if ($provider !== null) {
                                        $providers = [];
                                        if (!empty($r['providers'])) {
                                            $decoded = json_decode($r['providers'], true);
                                            if (is_array($decoded)) $providers = $decoded;
                                        }
                                        if (!empty($providers) && !in_array($provider, $providers, true)) {
                                            try {
                                                if (class_exists(Log::class)) {
                                                    Log::record($site, $provider, 'invalid_provider', ['api_key_last6' => substr($apiKey, -6)], null, (int)$r['id']);
                                                }
                                            } catch (\Throwable $e) {}
                                            continue;
                                        }
                                    }
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        // 2) env SITE_KEYS as JSON
        if (!empty($_ENV['SITE_KEYS'])) {
            $map = json_decode($_ENV['SITE_KEYS'], true);
            if (is_array($map)) {
                // Direct exact key
                if (isset($map[$site]) && hash_equals($map[$site], $apiKey)) {
                    return true;
                }
                // Try wildcard patterns
                foreach ($map as $pattern => $keyVal) {
                    if (strpos($pattern, '*') === false) continue;
                    $patternNoScheme = preg_replace('#^https?://#i', '', rtrim($pattern, '/'));
                    $patternsToTest = [$patternNoScheme];
                    if (strpos($patternNoScheme, '*.') !== false) {
                        $alt = str_replace('*.', '', $patternNoScheme);
                        if ($alt !== $patternNoScheme) $patternsToTest[] = $alt;
                    }
                    $expanded = [];
                    foreach ($patternsToTest as $p) {
                        $expanded[] = $p;
                        if (substr($p, -2) === '/*') {
                            $expanded[] = substr($p, 0, -2);
                        }
                        if (strpos($p, '*') === false && strpos($p, 'www.') !== 0) {
                            $expanded[] = 'www.' . $p;
                            if (substr($p, -2) === '/*') {
                                $expanded[] = 'www.' . substr($p, 0, -2);
                            }
                        }
                    }

                    $siteVariants = [$siteNoScheme];
                    if (strpos($siteNoScheme, 'www.') === 0) {
                        $siteVariants[] = substr($siteNoScheme, 4);
                    } else {
                        $siteVariants[] = 'www.' . $siteNoScheme;
                    }

                    foreach ($expanded as $pat) {
                        foreach ($siteVariants as $siteCandidate) {
                            if (fnmatch($pat, $siteCandidate, FNM_CASEFOLD) && hash_equals($keyVal, $apiKey)) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        // 3) config/site keys defined in config/config.php
        if (defined('SITE_KEYS') && is_array(SITE_KEYS)) {
            if (isset(SITE_KEYS[$site]) && hash_equals(SITE_KEYS[$site], $apiKey)) {
                return true;
            }
            foreach (SITE_KEYS as $pattern => $keyVal) {
                if (strpos($pattern, '*') === false) continue;
                $patternNoScheme = preg_replace('#^https?://#i', '', rtrim($pattern, '/'));
                $patternsToTest = [$patternNoScheme];
                if (strpos($patternNoScheme, '*.') !== false) {
                    $alt = str_replace('*.', '', $patternNoScheme);
                    if ($alt !== $patternNoScheme) $patternsToTest[] = $alt;
                }
                $expanded = [];
                foreach ($patternsToTest as $p) {
                    $expanded[] = $p;
                    if (substr($p, -2) === '/*') {
                        $expanded[] = substr($p, 0, -2);
                    }
                    if (strpos($p, '*') === false && strpos($p, 'www.') !== 0) {
                        $expanded[] = 'www.' . $p;
                        if (substr($p, -2) === '/*') {
                            $expanded[] = 'www.' . substr($p, 0, -2);
                        }
                    }
                }

                $siteVariants = [$siteNoScheme];
                if (strpos($siteNoScheme, 'www.') === 0) {
                    $siteVariants[] = substr($siteNoScheme, 4);
                } else {
                    $siteVariants[] = 'www.' . $siteNoScheme;
                }

                foreach ($expanded as $pat) {
                    foreach ($siteVariants as $siteCandidate) {
                        if (fnmatch($pat, $siteCandidate, FNM_CASEFOLD) && hash_equals($keyVal, $apiKey)) {
                            return true;
                        }
                    }
                }
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
            if (isset($sitichiavi) && is_array($sitichiavi)) {
                if (isset($sitichiavi[$site]) && hash_equals($sitichiavi[$site], $apiKey)) {
                    return true;
                }
                foreach ($sitichiavi as $pattern => $keyVal) {
                    if (strpos($pattern, '*') === false) continue;
                    $patternNoScheme = preg_replace('#^https?://#i', '', rtrim($pattern, '/'));
                    $patternsToTest = [$patternNoScheme];
                    if (strpos($patternNoScheme, '*.') !== false) {
                        $alt = str_replace('*.', '', $patternNoScheme);
                        if ($alt !== $patternNoScheme) $patternsToTest[] = $alt;
                    }
                    $expanded = [];
                    foreach ($patternsToTest as $p) {
                        $expanded[] = $p;
                        if (substr($p, -2) === '/*') {
                            $expanded[] = substr($p, 0, -2);
                        }
                        if (strpos($p, '*') === false && strpos($p, 'www.') !== 0) {
                            $expanded[] = 'www.' . $p;
                            if (substr($p, -2) === '/*') {
                                $expanded[] = 'www.' . substr($p, 0, -2);
                            }
                        }
                    }

                    $siteVariants = [$siteNoScheme];
                    if (strpos($siteNoScheme, 'www.') === 0) {
                        $siteVariants[] = substr($siteNoScheme, 4);
                    } else {
                        $siteVariants[] = 'www.' . $siteNoScheme;
                    }

                    foreach ($expanded as $pat) {
                        foreach ($siteVariants as $siteCandidate) {
                            if (fnmatch($pat, $siteCandidate, FNM_CASEFOLD) && hash_equals($keyVal, $apiKey)) {
                                return true;
                            }
                        }
                    }
                }
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

            // If an apiKey is provided, find row by api_key first (fast path) and then verify the site matches.
            if (!empty($apiKey)) {
                $stmt = $pdo->prepare('SELECT id, site_url, api_key, active FROM site_keys WHERE api_key = :api_key LIMIT 1');
                $stmt->execute([':api_key' => (string)$apiKey]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && (int)($row['active'] ?? 0) === 1) {
                    // if no site provided, api key is sufficient to identify the site_key
                    if (empty($site)) {
                        return (int)$row['id'];
                    }
                    // verify the provided site matches the stored site_url (exact or wildcard)
                    $siteNoScheme = preg_replace('#^https?://#i', '', rtrim((string)$site, '/'));
                    $pattern = rtrim($row['site_url'], '/');
                    $patternNoScheme = preg_replace('#^https?://#i', '', $pattern);
                    // exact match
                    if (strcasecmp($patternNoScheme, $siteNoScheme) === 0) {
                        return (int)$row['id'];
                    }
                    // wildcard/pattern match
                    if (strpos($patternNoScheme, '*') !== false) {
                        $patternsToTest = [$patternNoScheme];
                        if (strpos($patternNoScheme, '*.') !== false) {
                            $alt = str_replace('*.', '', $patternNoScheme);
                            if ($alt !== $patternNoScheme) $patternsToTest[] = $alt;
                        }
                        $expanded = [];
                        foreach ($patternsToTest as $p) {
                            $expanded[] = $p;
                            if (substr($p, -2) === '/*') {
                                $expanded[] = substr($p, 0, -2);
                            }
                            if (strpos($p, '*') === false && strpos($p, 'www.') !== 0) {
                                $expanded[] = 'www.' . $p;
                                if (substr($p, -2) === '/*') {
                                    $expanded[] = 'www.' . substr($p, 0, -2);
                                }
                            }
                        }
                        $siteVariants = [$siteNoScheme];
                        if (strpos($siteNoScheme, 'www.') === 0) {
                            $siteVariants[] = substr($siteNoScheme, 4);
                        } else {
                            $siteVariants[] = 'www.' . $siteNoScheme;
                        }
                        foreach ($expanded as $pat) {
                            foreach ($siteVariants as $siteCandidate) {
                                if (fnmatch($pat, $siteCandidate, FNM_CASEFOLD)) {
                                    return (int)$row['id'];
                                }
                            }
                        }
                    }
                    // api_key matched but site did not match -> do not return id
                    return null;
                }
                // if api key present but not found/active, fallthrough to try site-only matching
            }

            // If no apiKey fast-path match, fall back to site-only exact / wildcard matching
            if (!empty($site)) {
                $stmt = $pdo->prepare('SELECT id FROM site_keys WHERE site_url = :site LIMIT 1');
                $stmt->execute([':site' => rtrim((string)$site, '/')] );
                $r = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($r && isset($r['id'])) return (int)$r['id'];

                // No exact match: try wildcard/pattern matches against active rows
                $siteNoScheme = preg_replace('#^https?://#i', '', rtrim((string)$site, '/'));
                $stmt2 = $pdo->prepare('SELECT id, site_url, api_key, active FROM site_keys WHERE active = 1');
                $stmt2->execute();
                while ($row = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
                    if (empty($row['site_url'])) continue;
                    $pattern = rtrim($row['site_url'], '/');
                    $patternNoScheme = preg_replace('#^https?://#i', '', $pattern);
                    if (strpos($patternNoScheme, '*') === false) continue;

                    $patternsToTest = [$patternNoScheme];
                    if (strpos($patternNoScheme, '*.') !== false) {
                        $alt = str_replace('*.', '', $patternNoScheme);
                        if ($alt !== $patternNoScheme) $patternsToTest[] = $alt;
                    }
                    $expanded = [];
                    foreach ($patternsToTest as $p) {
                        $expanded[] = $p;
                        if (substr($p, -2) === '/*') {
                            $expanded[] = substr($p, 0, -2);
                        }
                        if (strpos($p, '*') === false && strpos($p, 'www.') !== 0) {
                            $expanded[] = 'www.' . $p;
                            if (substr($p, -2) === '/*') {
                                $expanded[] = 'www.' . substr($p, 0, -2);
                            }
                        }
                    }

                    $siteVariants = [$siteNoScheme];
                    if (strpos($siteNoScheme, 'www.') === 0) {
                        $siteVariants[] = substr($siteNoScheme, 4);
                    } else {
                        $siteVariants[] = 'www.' . $siteNoScheme;
                    }

                    foreach ($expanded as $pat) {
                        foreach ($siteVariants as $siteCandidate) {
                            if (fnmatch($pat, $siteCandidate, FNM_CASEFOLD)) {
                                return (int)$row['id'];
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and return null
        }
        return null;
    }
}
