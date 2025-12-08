<?php

namespace Immaginificio\OAuthProxyBridge\Models;

use Immaginificio\OAuthProxyBridge\Core\Database;
use Immaginificio\OAuthProxyBridge\Models\SiteKey;
use Immaginificio\OAuthProxyBridge\Models\User;
use Immaginificio\OAuthProxyBridge\Core\Auth;

/**
 * Log model - write audit logs to DB
 *
 * @package Immaginificio\OAuthProxyBridge\Models
 * @since 0.0.1
 */
class Log
{
    /**
        * Record a log event into the database.
        *
        * @param string|null $site
        * @param string|null $provider
        * @param string|null $action
        * @param mixed $payload
        * @return bool
        * @since 0.0.1
        */
    public static function record(?string $site, ?string $provider, ?string $action, $payload = null, ?int $userId = null, ?int $siteKeyId = null): bool
    {
        try {
            // helper to detect caller IP
            // prefer X-Forwarded-For, X-Real-IP, then REMOTE_ADDR
            // defined as a small closure to keep scope local
            $getIp = function(): ?string {
                $headers = [
                    'HTTP_X_FORWARDED_FOR', 'X-Forwarded-For',
                    'HTTP_X_REAL_IP', 'X-Real-IP',
                    'HTTP_CLIENT_IP', 'Client-IP',
                ];
                // check common server vars
                foreach ($headers as $h) {
                    $val = $_SERVER[$h] ?? ($_SERVER[str_replace('-', '_', strtoupper($h))] ?? null);
                    if (!empty($val)) {
                        // X-Forwarded-For can contain a list
                        $parts = explode(',', $val);
                        $ip = trim($parts[0]);
                        if ($ip) return $ip;
                    }
                }
                return $_SERVER['REMOTE_ADDR'] ?? null;
            };
            $pdo = Database::getConnection();
            // preserve whether the caller explicitly provided a userId (may be null)
            $callerProvidedUserId = $userId;
                // determine actor/source and current admin id via Auth helper
                // If a $site parameter (URL) is provided and no explicit userId was supplied,
                // prefer it as the actor and try to resolve a site_key id. This prevents showing
                // the current admin username for events that originate from remote sites (server->server).
                // Do NOT override when an explicit $userId (actor) was provided by the caller (admin actions).
                if (empty($userId) && !empty($site) && (strpos($site, 'http') === 0 || filter_var($site, FILTER_VALIDATE_URL))) {
                    try {
                        $found = SiteKey::findIdBySiteOrApi($site, null);
                        if (!empty($found)) {
                            $siteKeyId = (int)$found;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                    $actor = $site;
                    $userId = null;
                }

                // Special-case: for auth login events prefer resolution from payload email or provided userId
                $isAuthLogin = ($provider === 'auth' && stripos((string)$action, 'login') !== false);

                if ($isAuthLogin) {
                    // 1) If explicit userId provided, resolve it
                    if (!empty($userId)) {
                        try {
                            $u = User::findById((int)$userId);
                            if ($u) {
                                $actor = !empty($u['name']) ? $u['name'] : ($u['email'] ?? null);
                            }
                        } catch (\Throwable $e) {
                            // ignore
                        }
                    }

                    // 2) If not resolved, prefer payload email mapping
                    if (empty($actor) && is_array($payload)) {
                        $email = $payload['email'] ?? null;
                        if (!empty($email)) {
                            try {
                                $u2 = User::findByEmail($email);
                                if ($u2) {
                                    $actor = !empty($u2['name']) ? $u2['name'] : ($u2['email'] ?? null);
                                    if (empty($userId) && !empty($u2['id'])) {
                                        $userId = (int)$u2['id'];
                                    }
                                } else {
                                    // email provided but no user found -> mark as unknown
                                    $actor = 'Sconosciuta';
                                }
                            } catch (\Throwable $e) {
                                // ignore
                            }
                        }
                    }

                    // 3) fallback: do not use current session for auth/login events (avoid showing current admin)
                    if (empty($actor)) {
                        $actor = $site;
                    }
                } else {
                    // Non-auth-login default flow: prefer explicit userId, then session/token, then payload email, then site
                    $actor = null;
                    if (!empty($userId)) {
                        try {
                            $u = User::findById((int)$userId);
                            if ($u) {
                                $actor = !empty($u['name']) ? $u['name'] : ($u['email'] ?? null);
                            }
                        } catch (\Throwable $e) {
                            // ignore lookup errors
                        }
                    }

                    if (empty($actor)) {
                        $actor = Auth::currentActorLabel();
                        if ($callerProvidedUserId === null && $siteKeyId === null) {
                            $userId = Auth::currentAdminId();
                        }
                    }

                    if (empty($actor) && is_array($payload)) {
                        $email = $payload['email'] ?? null;
                        if (!empty($email)) {
                            try {
                                $u2 = User::findByEmail($email);
                                if ($u2) {
                                    $actor = !empty($u2['name']) ? $u2['name'] : ($u2['email'] ?? null);
                                    if (empty($userId) && !empty($u2['id'])) {
                                        $userId = (int)$u2['id'];
                                    }
                                } else {
                                    $actor = 'Sconosciuta';
                                }
                            } catch (\Throwable $e) {
                                // ignore
                            }
                        }
                    }

                    if (empty($actor)) {
                        $actor = $site;
                    }
                }
            if (!empty($userId)) {
                try {
                    $u = User::findById((int)$userId);
                    if ($u) {
                        $actor = !empty($u['name']) ? $u['name'] : ($u['email'] ?? null);
                    }
                } catch (\Throwable $e) {
                    // ignore lookup errors
                }
            }

            // If no explicit userId resolved to an actor, fall back to current session/token
            if (empty($actor)) {
                $actor = Auth::currentActorLabel();
                if ($callerProvidedUserId === null && $siteKeyId === null) {
                    $userId = Auth::currentAdminId();
                }
            }

            // If still no actor, and payload contains an email, try to map it to an existing user.
            if (empty($actor) && is_array($payload)) {
                $email = $payload['email'] ?? null;
                if (!empty($email)) {
                    try {
                        $u2 = User::findByEmail($email);
                        if ($u2) {
                            $actor = !empty($u2['name']) ? $u2['name'] : ($u2['email'] ?? null);
                            // ensure the user_id is set for this event when possible
                            if (empty($userId) && !empty($u2['id'])) {
                                $userId = (int)$u2['id'];
                            }
                        } else {
                            // email provided but no user found -> mark as unknown
                            $actor = 'Sconosciuta';
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
            }

            // fallback to provided site if still empty
            if (empty($actor)) {
                $actor = $site;
            }

            // auto-detect site_key_id from request params if not provided
            if ($siteKeyId === null) {
                $siteParam = $_REQUEST['site'] ?? $_REQUEST['site_url'] ?? null;
                // Use canonical `oauth_bridge_api_key` only
                $apiKeyParam = $_REQUEST['oauth_bridge_api_key'] ?? $_REQUEST['api_key'] ?? null;
                if ($siteParam || $apiKeyParam) {
                    // use centralized SiteKey helper
                    try {
                        $siteKeyId = SiteKey::findIdBySiteOrApi($siteParam, $apiKeyParam);
                    } catch (\Throwable $e) {
                        // ignore lookup errors
                    }
                }
            }

            // If this event is tied to a site key (server->server), prefer the site key's NAME
            // as the actor (human-friendly). Only apply this override when no explicit
            // admin/user actor was provided by the caller. This prevents admin UI actions
            // that pass a $userId from being attributed to the site key.
            if ($siteKeyId !== null) {
                try {
                    $pdo2 = Database::getConnection();
                    $sstmt = $pdo2->prepare('SELECT name, site_url FROM site_keys WHERE id = :id LIMIT 1');
                    $sstmt->execute([':id' => $siteKeyId]);
                    $srow = $sstmt->fetch(\PDO::FETCH_ASSOC);
                    if ($srow) {
                        $keyName = !empty($srow['name']) ? $srow['name'] : null;
                        $keySite = !empty($srow['site_url']) ? $srow['site_url'] : null;
                        // Only override the resolved actor when there is no explicit userId
                        // (i.e. caller didn't pass an admin actor). If a userId is present,
                        // keep the actor derived from the user.
                        if (empty($userId)) {
                            if (!empty($keyName)) {
                                $actor = $keyName;
                            } elseif (!empty($keySite)) {
                                $actor = $keySite;
                            } else {
                                $actor = $site ?? $actor;
                            }
                        }
                    } else {
                        $actor = $site ?? $actor;
                    }
                } catch (\Throwable $e) {
                    // on any DB error fall back to site
                    $actor = $site ?? $actor;
                }
                // ensure we do not attribute this event to an admin user only when
                // the caller did not explicitly provide a userId
                if ($callerProvidedUserId === null) {
                    $userId = null;
                }
            }

            // include ip in payload for legacy schemas and keep as separate column for relational schema
            $ip = $getIp();
            if (!is_string($payload)) {
                $payloadArr = is_array($payload) || is_object($payload) ? $payload : ['value' => $payload];
                // attach ip to payload copy for auditing
                if (is_array($payloadArr)) {
                    $payloadArr['_ip'] = $ip;
                }
                $payloadJson = json_encode($payloadArr);
            } else {
                // string payload — wrap with ip
                $payloadJson = json_encode(['message' => $payload, '_ip' => $ip]);
            }

            // Prefer new relational insert: user_id, site_key_id, source, provider, action, payload
            try {
                $stmt = $pdo->prepare('INSERT INTO logs (user_id, site_key_id, ip, source, provider, action, payload) VALUES (:user_id, :site_key_id, :ip, :source, :provider, :action, :payload)');
                $stmt->execute([
                    ':user_id' => $userId,
                    ':site_key_id' => $siteKeyId,
                    ':ip' => $ip,
                    ':source' => $actor,
                    ':provider' => $provider,
                    ':action' => $action,
                    ':payload' => $payloadJson,
                ]);
            } catch (\PDOException $e) {
                // fallback for older schema: try inserting into source or site_url
                error_log('Log::record relational insert failed: ' . $e->getMessage() . ' — falling back to legacy inserts');
                // First try to include ip in the fallback insert (if the column exists in this schema)
                try {
                    $stmt = $pdo->prepare('INSERT INTO logs (ip, source, provider, action, payload) VALUES (:ip, :source, :provider, :action, :payload)');
                    $stmt->execute([':ip' => $ip, ':source' => $actor, ':provider' => $provider, ':action' => $action, ':payload' => $payloadJson]);
                } catch (\PDOException $e2) {
                    // if ip column not available, try legacy source-only insert
                    try {
                        $stmt = $pdo->prepare('INSERT INTO logs (source, provider, action, payload) VALUES (:source, :provider, :action, :payload)');
                        $stmt->execute([':source' => $actor, ':provider' => $provider, ':action' => $action, ':payload' => $payloadJson]);
                    } catch (\PDOException $e3) {
                        // last-resort: try legacy site_url column, prefer including ip if possible
                        try {
                            $stmt = $pdo->prepare('INSERT INTO logs (site_url, ip, provider, action, payload) VALUES (:site, :ip, :provider, :action, :payload)');
                            $stmt->execute([':site' => $actor, ':ip' => $ip, ':provider' => $provider, ':action' => $action, ':payload' => $payloadJson]);
                        } catch (\PDOException $e4) {
                            // fallback without ip for very old schemas
                            try {
                                $stmt = $pdo->prepare('INSERT INTO logs (site_url, provider, action, payload) VALUES (:site, :provider, :action, :payload)');
                                $stmt->execute([':site' => $actor, ':provider' => $provider, ':action' => $action, ':payload' => $payloadJson]);
                            } catch (\Throwable $e5) {
                                throw $e5;
                            }
                        }
                    }
                }
            }
            return true;
        } catch (\Throwable $e) {
            error_log('Log::record error: ' . $e->getMessage());
            return false;
        }
    }
}
