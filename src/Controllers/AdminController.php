<?php

namespace Immaginificio\OAuthProxyBridge\Controllers;

use Immaginificio\OAuthProxyBridge\Core\Database;
use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Core\Response;
use Immaginificio\OAuthProxyBridge\Models\Log;
use Immaginificio\OAuthProxyBridge\Models\User;
use Immaginificio\OAuthProxyBridge\Core\Auth;
use Immaginificio\OAuthProxyBridge\Services\ServiceManager;

/**
 * AdminController: gestione chiavi siti e log (API)
 *
 * @package Immaginificio\OAuthProxyBridge\Controllers
 * @since 0.0.1
 */
class AdminController
{
    /**
     * List registered site keys.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function listKeys(Request $request, Response $response): void
    {
        try {
            $pdo = Database::getConnection();
            $page = max(1, (int)($request->get('page') ?? 1));
            $per = max(1, min(100, (int)($request->get('per_page') ?? 25)));
            $offset = ($page - 1) * $per;

            // Apply basic filters: q (search name or site_url), active (1/0)
            $where = [];
            $params = [];
            $q = $request->get('q');
            if (!empty($q)) {
                $where[] = '(name LIKE :q OR site_url LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }
            $provider = $request->get('provider');
            if ($provider !== null && $provider !== '') {
                // providers stored as JSON array; use LIKE on serialized JSON to match provider string
                $where[] = 'providers LIKE :provider_like';
                $params[':provider_like'] = '%"' . $provider . '"%';
            }
            $active = $request->get('active');
            if ($active !== null && $active !== '') {
                $where[] = 'active = :active';
                $params[':active'] = (int)$active;
            }

            $whereSql = '';
            if (!empty($where)) {
                $whereSql = 'WHERE ' . implode(' AND ', $where);
            }

            // total count
            $totStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM site_keys $whereSql");
            $totStmt->execute($params);
            $tot = $totStmt->fetch(\PDO::FETCH_ASSOC);
            $total = isset($tot['c']) ? (int)$tot['c'] : 0;

            $sql = 'SELECT id, site_url, name, description, providers, active, created_at FROM site_keys ' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->bindValue(':limit', $per, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // decode providers JSON into arrays for clients
            foreach ($rows as &$r) {
                if (isset($r['providers'])) {
                    $r['providers'] = $r['providers'] ? json_decode($r['providers'], true) : [];
                } else {
                    $r['providers'] = [];
                }
            }

            $meta = ['total' => $total, 'page' => $page, 'per_page' => $per, 'total_pages' => (int)ceil($total / max(1,$per))];
            // ensure consistent key name for clients
            $response->json(['data' => $rows, 'meta' => $meta]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * List recent logs.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function listLogs(Request $request, Response $response): void
    {
        try {
            $pdo = Database::getConnection();
            $page = max(1, (int)($request->get('page') ?? 1));
            $per = max(1, min(200, (int)($request->get('per_page') ?? 25)));
            $offset = ($page - 1) * $per;
            // Apply filters if provided
            $where = [];
            $params = [];
            $src = $request->get('source');
            if (!empty($src)) {
                // match exact source or names (partial) or payload containing the value
                // Use unique placeholders for each LIKE to avoid driver issues with repeated named params
                $jsonEmail = "JSON_UNQUOTE(JSON_EXTRACT(l.payload, '$.email'))";
                $jsonName = "JSON_UNQUOTE(JSON_EXTRACT(l.payload, '$.name'))";
                $where[] = "(l.source = :src_exact OR u.name LIKE :src_like_u OR sk.name LIKE :src_like_sk OR $jsonEmail LIKE :src_like_email OR $jsonName LIKE :src_like_name OR l.payload LIKE :src_like_payload)";
                $params['src_exact'] = $src;
                $likeVal = '%' . $src . '%';
                $params['src_like_u'] = $likeVal;
                $params['src_like_sk'] = $likeVal;
                $params['src_like_email'] = $likeVal;
                $params['src_like_name'] = $likeVal;
                $params['src_like_payload'] = $likeVal;
            }
            $provider = $request->get('provider');
            if (!empty($provider)) {
                $where[] = 'l.provider = :provider';
                $params['provider'] = $provider;
            }
            $ip = $request->get('ip');
            if (!empty($ip)) {
                // allow wildcard '*' -> SQL '%'
                $ipLike = str_replace('*', '%', $ip);
                $where[] = "l.ip LIKE :ip";
                $params['ip'] = $ipLike;
            }
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            if (!empty($dateFrom)) {
                $where[] = "DATE(l.created_at) >= :date_from";
                $params['date_from'] = $dateFrom;
            }
            if (!empty($dateTo)) {
                $where[] = "DATE(l.created_at) <= :date_to";
                $params['date_to'] = $dateTo;
            }

            $whereSql = '';
            if (!empty($where)) {
                $whereSql = 'WHERE ' . implode(' AND ', $where);
            }

            $tot = $pdo->prepare("SELECT COUNT(*) AS c FROM logs l LEFT JOIN users u ON l.user_id = u.id LEFT JOIN site_keys sk ON l.site_key_id = sk.id $whereSql");
            $tot->execute($params);
            $totRow = $tot->fetch(\PDO::FETCH_ASSOC);
            $total = isset($totRow['c']) ? (int)$totRow['c'] : 0;

            $sql = 'SELECT l.id, l.user_id, l.source, u.name AS user_name, sk.site_url AS site_url, sk.name AS site_name, l.provider, l.action, l.payload, l.ip, l.created_at'
                . ' FROM logs l'
                . ' LEFT JOIN users u ON l.user_id = u.id'
                . ' LEFT JOIN site_keys sk ON l.site_key_id = sk.id'
                . " $whereSql"
                . ' ORDER BY l.created_at DESC'
                . ' LIMIT :limit OFFSET :offset';

            $stmt = $pdo->prepare($sql);
            // merge limit/offset into params (use same named placeholders)
            $params['limit'] = $per;
            $params['offset'] = $offset;
            try {
                $stmt->execute($params);
            } catch (\Throwable $e) {
                // Log SQL and params for debugging
                error_log('AdminController::listLogs SQL Error: ' . $e->getMessage());
                error_log('SQL: ' . $sql);
                error_log('Params: ' . var_export($params, true));
                throw $e;
            }
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $meta = ['total' => $total, 'page' => $page, 'per_page' => $per, 'total_pages' => (int)ceil($total / max(1,$per))];
            $response->json(['data' => $rows, 'meta' => $meta]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Suggest values for filters (source names and IPs).
     * GET /admin/logs/suggestions?type=source|ip&q=...
     */
    public function suggestLogs(Request $request, Response $response): void
    {
        try {
            $pdo = Database::getConnection();
            $type = $request->get('type');
            $q = $request->get('q');
            $limit = 20;
            $results = [];
            if ($type === 'source') {
                // users names
                $stmt = $pdo->prepare('SELECT DISTINCT name FROM users WHERE name IS NOT NULL AND name != "" AND name LIKE :q LIMIT :limit');
                $stmt->bindValue(':q', '%' . ($q ?? '') . '%');
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $r) { $results[] = $r['name']; }
                // site key names
                $stmt2 = $pdo->prepare('SELECT DISTINCT name FROM site_keys WHERE name IS NOT NULL AND name != "" AND name LIKE :q LIMIT :limit');
                $stmt2->bindValue(':q', '%' . ($q ?? '') . '%');
                $stmt2->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt2->execute();
                $rows2 = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows2 as $r) { $results[] = $r['name']; }
                // always include wildcard option
                if (!in_array('Sconosciuta', $results, true)) $results[] = 'Sconosciuta';
            } elseif ($type === 'ip') {
                $stmt = $pdo->prepare('SELECT DISTINCT ip FROM logs WHERE ip IS NOT NULL AND ip != "" AND ip LIKE :q LIMIT :limit');
                $stmt->bindValue(':q', '%' . ($q ?? '') . '%');
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $r) { $results[] = $r['ip']; }
            }
            $response->json(['data' => array_values(array_unique($results))]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Create a new site key.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function createKey(Request $request, Response $response): void
    {
        $body = $request->json() ?? $request->all();
        $site = $body['site_url'] ?? null;
        $desc = $body['description'] ?? null;
        $active = isset($body['active']) ? (int)$body['active'] : 1;

        if (empty($site)) {
            $response->status(400)->json(['error' => 'missing_site_url']);
            return;
        }

        // validate site_url format/pattern (allow wildcard subdomain like "*.example.it" and optional path)
        if (!$this->validateSiteUrl($site)) {
            $response->status(400)->json(['error' => 'invalid_site_url']);
            return;
        }

        $apiKey = bin2hex(random_bytes(32));

        // validate providers against available providers
        $providersInput = isset($body['providers']) && is_array($body['providers']) ? array_values($body['providers']) : [];
        $svc = new ServiceManager();
        $available = $svc->availableProviders();
        if (!empty($providersInput)) {
            $invalid = array_values(array_diff($providersInput, $available));
            if (!empty($invalid)) {
                $response->status(400)->json(['error' => 'invalid_providers', 'invalid' => $invalid]);
                return;
            }
        }

        try {
            $pdo = Database::getConnection();
            $name = $body['name'] ?? null;
            $providers = !empty($providersInput) ? json_encode($providersInput) : null;
            $stmt = $pdo->prepare('INSERT INTO site_keys (site_url, name, api_key, description, providers, active) VALUES (:site, :name, :api_key, :desc, :providers, :active)');
            $stmt->execute([':site' => rtrim($site, '/'), ':name' => $name, ':api_key' => $apiKey, ':desc' => $desc, ':providers' => $providers, ':active' => $active]);
            $id = $pdo->lastInsertId();
            // record admin action in logs (actor = current admin user)
            $actorId = Auth::currentAdminId();
            Log::record($site ?: null, 'site_key', 'create', ['id' => $id, 'name' => $name], $actorId, (int)$id);

            $response->status(201)->json(['id' => $id, 'site_url' => $site, 'name' => $name, 'api_key' => $apiKey]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Update an existing site key.
     *
     * @param Request $request
     * @param Response $response
     * @param array $params Route parameters (expects 'id')
     * @return void
     * @since 0.0.1
     */
/**
 * updateKey
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $params
 * @return mixed
 * @since 0.0.1
 */
    public function updateKey(Request $request, Response $response, array $params): void
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }

        $body = $request->json() ?? $request->all();
        $desc = $body['description'] ?? null;
        $active = isset($body['active']) ? (int)$body['active'] : null;
        $providers = isset($body['providers']) ? $body['providers'] : null; // expect array or null
        $name = isset($body['name']) ? $body['name'] : null;
        $siteUrl = isset($body['site_url']) ? $body['site_url'] : null;

        try {
            $pdo = Database::getConnection();
            $fields = [];
            $paramsExec = [':id' => $id];
            // validate providers if present
            if ($providers !== null && is_array($providers)) {
                $svc = new ServiceManager();
                $available = $svc->availableProviders();
                $invalid = array_values(array_diff(array_values($providers), $available));
                if (!empty($invalid)) {
                    $response->status(400)->json(['error' => 'invalid_providers', 'invalid' => $invalid]);
                    return;
                }
            }
            if ($desc !== null) {
                $fields[] = 'description = :desc';
                $paramsExec[':desc'] = $desc;
            }
            if ($active !== null) {
                $fields[] = 'active = :active';
                $paramsExec[':active'] = $active;
            }
            if ($providers !== null) {
                // ensure JSON encoding
                $fields[] = 'providers = :providers';
                $paramsExec[':providers'] = json_encode(array_values((array)$providers));
            }
            if ($name !== null) {
                $fields[] = 'name = :name';
                $paramsExec[':name'] = $name;
            }
            if ($siteUrl !== null) {
                // validate new site_url value
                if (!$this->validateSiteUrl($siteUrl)) {
                    $response->status(400)->json(['error' => 'invalid_site_url']);
                    return;
                }
                $fields[] = 'site_url = :site_url';
                $paramsExec[':site_url'] = rtrim($siteUrl, '/');
            }

            if (empty($fields)) {
                $response->status(400)->json(['error' => 'nothing_to_update']);
                return;
            }

            $sql = 'UPDATE site_keys SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($paramsExec);
            // log update
            $actorId = Auth::currentAdminId();
            // try to fetch site_url for context
            $siteRow = $pdo->prepare('SELECT site_url FROM site_keys WHERE id = :id');
            $siteRow->execute([':id' => $id]);
            $siteUrl = $siteRow->fetchColumn() ?: null;
            Log::record($siteUrl, 'site_key', 'update', ['id' => (int)$id, 'fields' => $fields], $actorId, (int)$id);

            $response->json(['ok' => true]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Validate accepted site_url formats.
     * Accepted:
     *  - full URLs with http/https (validated via FILTER_VALIDATE_URL)
    *  - host patterns with optional leading wildcard (e.g. "*.example.it"), optionally followed by a path (e.g. "*.example.it/*")
     *  - must be shorter than 255 chars
     *
     * @param string $site
     * @return bool
     */
    private function validateSiteUrl(string $site): bool
    {
        $site = trim($site);
        if ($site === '') return false;
        if (strlen($site) > 255) return false;

        // allow full URLs
        if (filter_var($site, FILTER_VALIDATE_URL)) return true;

        // allow patterns like "*.example.it" or "*.example.it/*" or "example.it/path*"
        // hostname may optionally start with "*."; then require at least one dot in hostname
        // path is optional and may contain any characters (including *)
        // We reject strings that contain scheme (://) to avoid ambiguity
        if (strpos($site, '://') !== false) return false;

        // split host and path
        $parts = explode('/', $site, 2);
        $host = $parts[0];

        // host must match pattern: optional '*.' then labels with letters/numbers/hyphen and at least one dot
        if (!preg_match('/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/i', $host)) {
            return false;
        }

        return true;
    }

    /**
     * Delete a site key.
     *
     * @param Request $request
     * @param Response $response
     * @param array $params Route parameters (expects 'id')
     * @return void
     * @since 0.0.1
     */
/**
 * deleteKey
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $params
 * @return mixed
 * @since 0.0.1
 */
    public function deleteKey(Request $request, Response $response, array $params): void
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('DELETE FROM site_keys WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $actorId = Auth::currentAdminId();
            // record deletion
            Log::record(null, 'site_key', 'delete', ['id' => (int)$id], $actorId, (int)$id);

            $response->json(['ok' => true]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Return the API key for a site key (admin-only)
     * GET /admin/keys/{id}/show
     */
    public function showKey(Request $request, Response $response, array $params): void
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT api_key FROM site_keys WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $key = $stmt->fetchColumn();
            if ($key === false) {
                $response->status(404)->json(['error' => 'not_found']);
                return;
            }
            $response->json(['api_key' => $key]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Regenerate API key for a site key and (re)activate it.
     * POST /admin/keys/{id}/regenerate
     */
    public function regenerateKey(Request $request, Response $response, array $params): void
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }
        try {
            $pdo = Database::getConnection();
            $newKey = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('UPDATE site_keys SET api_key = :api_key, active = 1 WHERE id = :id');
            $stmt->execute([':api_key' => $newKey, ':id' => $id]);
            $actorId = Auth::currentAdminId();
            Log::record(null, 'site_key', 'regenerate', ['id' => (int)$id], $actorId, (int)$id);
            $response->json(['api_key' => $newKey]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Suggestions for keys (name and site_url) used by admin autocomplete.
     * GET /admin/keys/suggestions?q=...
     */
    public function suggestKeys(Request $request, Response $response): void
    {
        try {
            $pdo = Database::getConnection();
            $q = $request->get('q');
            $limit = 20;
            $results = [];
            // search both name and site_url
            $stmt = $pdo->prepare('SELECT DISTINCT name FROM site_keys WHERE name IS NOT NULL AND name != "" AND name LIKE :q LIMIT :limit');
            $stmt->bindValue(':q', '%' . ($q ?? '') . '%');
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) { $results[] = $r['name']; }

            $stmt2 = $pdo->prepare('SELECT DISTINCT site_url FROM site_keys WHERE site_url IS NOT NULL AND site_url != "" AND site_url LIKE :q LIMIT :limit');
            $stmt2->bindValue(':q', '%' . ($q ?? '') . '%');
            $stmt2->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt2->execute();
            $rows2 = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows2 as $r) { $results[] = $r['site_url']; }

            $response->json(['data' => array_values(array_unique($results))]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Revoke (deactivate) a site key.
     * POST /admin/keys/{id}/revoke
     */
    public function revokeKey(Request $request, Response $response, array $params): void
    {
        $id = $params['id'] ?? null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE site_keys SET active = 0 WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $actorId = Auth::currentAdminId();
            Log::record(null, 'site_key', 'revoke', ['id' => (int)$id], $actorId, (int)$id);
            $response->json(['ok' => true]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * List admin users (for the dashboard)
     * GET /admin/users
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function listUsers(Request $request, Response $response): void
    {
        try {
            $pdo = Database::getConnection();
            $page = max(1, (int)($request->get('page') ?? 1));
            $per = max(1, min(100, (int)($request->get('per_page') ?? 25)));
            $offset = ($page - 1) * $per;

            $tot = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch(\PDO::FETCH_ASSOC);
            $total = isset($tot['c']) ? (int)$tot['c'] : 0;

            $stmt = $pdo->prepare('SELECT id, email, name, is_admin, created_at FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset');
            $stmt->bindValue(':limit', $per, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $current = Auth::currentAdminId();
            $meta = ['total' => $total, 'page' => $page, 'per_page' => $per, 'total_pages' => (int)ceil($total / max(1,$per))];
            $response->json(['data' => $rows, 'current_user_id' => $current, 'meta' => $meta]);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => 'db_error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Create a new admin user
     * POST /admin/users
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function createUser(Request $request, Response $response): void
    {
        $body = $request->json() ?? $request->all();
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;
        $passwordConfirm = $body['password_confirm'] ?? null;
        $name = $body['name'] ?? null;
        $isAdmin = isset($body['is_admin']) ? (bool)$body['is_admin'] : false;

        if (!$email || !$password) {
            $response->status(400)->json(['error' => 'missing_fields']);
            return;
        }

        // server-side password confirmation check
        if ($password !== null) {
            if ($passwordConfirm === null) {
                $response->status(400)->json(['error' => 'missing_password_confirm']);
                return;
            }
            if ($password !== $passwordConfirm) {
                $response->status(400)->json(['error' => 'password_mismatch']);
                return;
            }
        }

        $id = User::create($email, $password, $name, $isAdmin);
        if ($id) {
            $actorId = Auth::currentAdminId();
            Log::record(null, 'user', 'create', ['id' => $id, 'email' => $email], $actorId, null);
            $response->status(201)->json(['id' => $id, 'email' => $email]);
        } else {
            $response->status(500)->json(['error' => 'create_failed']);
        }
    }

    /**
     * Update a user's profile (only the user themself can edit their own profile)
     * POST /admin/users/{id}
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     * @since 0.0.1
     */
/**
 * updateUser
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $params
 * @return mixed
 * @since 0.0.1
 */
    public function updateUser(Request $request, Response $response, array $params): void
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }

        $current = Auth::currentAdminId();
        if ($current === null) {
            $response->status(403)->json(['error' => 'forbidden']);
            return;
        }

        // allow editing if the current user is the same user, or if the current user is an admin
        if ((int)$current !== $id) {
            $me = User::findById((int)$current);
            if (!$me || empty($me['is_admin'])) {
                $response->status(403)->json(['error' => 'forbidden']);
                return;
            }
        }

        $body = $request->json() ?? $request->all();
        $data = [];
        if (isset($body['name'])) { $data['name'] = $body['name']; }
        if (!empty($body['password'])) { $data['password'] = $body['password']; }
        $passwordConfirm = $body['password_confirm'] ?? null;
        // admins may update email and is_admin when editing other users
        if (isset($body['email'])) { $data['email'] = $body['email']; }
        if (isset($body['is_admin'])) { $data['is_admin'] = (int)$body['is_admin']; }

        // Prevent an admin from removing their own admin flag
        if ((int)$current === $id && array_key_exists('is_admin', $data) && !$data['is_admin']) {
            $response->status(400)->json(['error' => 'cannot_demote_self']);
            return;
        }

        if (empty($data)) {
            $response->status(400)->json(['error' => 'nothing_to_update']);
            return;
        }

        // if email is being updated, ensure uniqueness
        if (isset($data['email'])) {
            $existing = User::findByEmail($data['email']);
            if ($existing && (int)$existing['id'] !== $id) {
                $response->status(400)->json(['error' => 'email_taken']);
                return;
            }
        }

        // if password is being updated, ensure confirmation matches
        if (isset($data['password'])) {
            if ($passwordConfirm === null) {
                $response->status(400)->json(['error' => 'missing_password_confirm']);
                return;
            }
            if ($data['password'] !== $passwordConfirm) {
                $response->status(400)->json(['error' => 'password_mismatch']);
                return;
            }
        }

        $ok = User::updateProfile($id, $data);
        if ($ok) {
            $actorId = Auth::currentAdminId();
            Log::record(null, 'user', 'update', ['id' => (int)$id, 'payload' => $data], $actorId, null);
            $response->json(['ok' => true]);
        } else {
            $response->status(500)->json(['error' => 'update_failed']);
        }
    }

    /**
     * Delete a user (only admins can delete other users; self-deletion is not allowed)
     * POST /admin/users/{id}/delete
     *
     * @param Request $request
     * @param Response $response
     * @param array $params
     * @return void
     * @since 0.0.1
     */
/**
 * deleteUser
 *
 * @param mixed $request
 * @param mixed $response
 * @param mixed $params
 * @return mixed
 * @since 0.0.1
 */
    public function deleteUser(Request $request, Response $response, array $params): void
    {
        $id = isset($params['id']) ? (int)$params['id'] : null;
        if (!$id) {
            $response->status(400)->json(['error' => 'missing_id']);
            return;
        }

        $current = Auth::currentAdminId();
        if ($current === null) {
            $response->status(403)->json(['error' => 'forbidden']);
            return;
        }

        // cannot delete self
        if ((int)$current === $id) {
            $response->status(400)->json(['error' => 'cannot_delete_self']);
            return;
        }

        // check current user is admin
        $me = User::findById((int)$current);
        if (!$me || empty($me['is_admin'])) {
            $response->status(403)->json(['error' => 'forbidden']);
            return;
        }

        $ok = User::deleteById($id);
        if ($ok) {
            $actorId = Auth::currentAdminId();
            Log::record(null, 'user', 'delete', ['id' => (int)$id], $actorId, null);
            $response->json(['ok' => true]);
        } else {
            $response->status(500)->json(['error' => 'delete_failed']);
        }
    }
}
