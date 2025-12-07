<?php

namespace Immaginificio\OAuthProxyBridge\Middleware;

use Immaginificio\OAuthProxyBridge\Core\Request;

/**
 * Simple rate limit middleware using filesystem for counters
 *
 * Not suitable for heavy production but OK for shared hosting without Redis.
 *
 * @package Immaginificio\OAuthProxyBridge\Middleware
 * @since 0.0.1
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    protected string $storageDir;
    protected int $limit;
    protected int $windowSeconds;
/**
 * __construct
 *
 * @return mixed
 * @since 0.0.1
 */

    public function __construct()
    {
        $this->storageDir = sys_get_temp_dir() . '/oauth_rate_limit';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0700, true);
        }
        $this->limit = (int)($_ENV['RATE_LIMIT_REQUESTS'] ?? 60);
        $this->windowSeconds = (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
    }
/**
 * handle
 *
 * @param mixed $request
 * @return mixed
 * @since 0.0.1
 */

    public function handle(Request $request): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = hash('sha256', $ip . '|' . $request->path());
        $file = $this->storageDir . '/' . $key . '.json';
        $now = time();

        $data = ['start' => $now, 'count' => 0];
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $d = @json_decode($raw, true);
            if (is_array($d)) {
                $data = $d;
            }
        }

        if ($now - $data['start'] > $this->windowSeconds) {
            $data['start'] = $now;
            $data['count'] = 0;
        }

        $data['count']++;
        // persist
        @file_put_contents($file, json_encode($data));

        if ($data['count'] > $this->limit) {
            // Too many requests
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'rate_limited', 'limit' => $this->limit, 'window' => $this->windowSeconds]);
            exit;
        }

        // allow
        return true;
    }
}
