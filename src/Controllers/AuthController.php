<?php

namespace Immaginificio\OAuthProxyBridge\Controllers;

use Immaginificio\OAuthProxyBridge\Core\Request;
use Immaginificio\OAuthProxyBridge\Core\Response;
use Immaginificio\OAuthProxyBridge\Core\Database;
use Immaginificio\OAuthProxyBridge\Core\Session;
use Immaginificio\OAuthProxyBridge\Models\User;
use Immaginificio\OAuthProxyBridge\Models\Log;
use Immaginificio\OAuthProxyBridge\Core\Auth;

/**
 * AuthController: login/logout and password recovery endpoints for admin users
 *
 * @package Immaginificio\OAuthProxyBridge\Controllers
 * @since 0.0.1
 */
class AuthController
{
    /**
        * Admin user login: verify credentials and set the admin session.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function login(Request $request, Response $response): void
    {
        $body = $request->json() ?? $request->all();
        $email = $body['email'] ?? null;
        $password = $body['password'] ?? null;

        if (!$email || !$password) {
            $response->status(400)->json(['error' => 'missing_credentials']);
            return;
        }

        // Find user if exists (we still log attempts even if user is not found)
        $user = User::findByEmail($email);

        // Check lockout for existing user
        if ($user && !empty($user['locked_until'])) {
            try {
                $lockedUntil = new \DateTime($user['locked_until']);
                $now = new \DateTime();
                if ($lockedUntil > $now) {
                    $response->status(429)->json(['error' => 'too_many_attempts', 'message' => 'Troppi tentativi errati. Utente bloccato per 5 minuti']);
                    return;
                }
            } catch (\Throwable $e) {
                // ignore parse errors and continue
            }
        }

        // Verify password
        $ok = User::verifyPassword($email, $password);
        if (!$ok) {
            // Log failed attempt (payload only email)
            $userId = $user['id'] ?? null;
            Log::record(null, 'auth', 'failed_login', ['email' => $email], $userId, null);

            // If user exists, update counters and possibly lock
            if ($user) {
                try {
                    $pdo = Database::getConnection();
                    $now = new \DateTime();
                    $lastFailed = !empty($user['last_failed_at']) ? new \DateTime($user['last_failed_at']) : null;
                    $withinWindow = $lastFailed && ($now->getTimestamp() - $lastFailed->getTimestamp()) <= (10 * 60);
                    $failed = ($withinWindow ? (int)$user['failed_attempts'] + 1 : 1);
                    $lockedUntil = null;
                    if ($failed >= 5) {
                        $locked = (clone $now)->add(new \DateInterval('PT5M'));
                        $lockedUntil = $locked->format('Y-m-d H:i:s');
                    }
                    $stmt = $pdo->prepare('UPDATE users SET failed_attempts = :failed, last_failed_at = :last, locked_until = :locked WHERE id = :id');
                    $stmt->execute([':failed' => $failed, ':last' => $now->format('Y-m-d H:i:s'), ':locked' => $lockedUntil, ':id' => $user['id']]);
                    if ($lockedUntil) {
                        $response->status(429)->json(['error' => 'too_many_attempts', 'message' => 'Troppi tentativi errati. Utente bloccato per 5 minuti']);
                        return;
                    }
                } catch (\Throwable $e) {
                    // ignore DB update errors
                }
            }

            $response->status(401)->json(['error' => 'invalid_credentials', 'message' => 'Credenziali errate']);
            return;
        }

        // Successful login: reset counters if user exists
        if ($user) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare('UPDATE users SET failed_attempts = 0, last_failed_at = NULL, locked_until = NULL WHERE id = :id');
                $stmt->execute([':id' => $user['id']]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // set session (store name for UI/logs; fallback to email)
        Session::set('admin_user_id', (int)$user['id']);
        Session::set('admin_user_email', $user['email']);
        Session::set('admin_user_name', $user['name'] ?? $user['email']);

        // Log successful login
        try {
            Log::record(null, 'auth', 'success_login', ['email' => $user['email']], $user['id'] ?? null, null);
        } catch (\Throwable $e) {
            // don't block response on logging failures
            error_log('AuthController: failed to record success_login: ' . $e->getMessage());
        }

        $response->json(['ok' => true, 'user' => ['id' => $user['id'], 'email' => $user['email'], 'name' => $user['name']]]);
    }

    /**
        * Log out the current admin user and clear session values.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function logout(Request $request, Response $response): void
    {
        Auth::logout();
        $response->json(['ok' => true]);
    }

    /**
     * Request a password reset token sent via email.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function requestPasswordReset(Request $request, Response $response): void
    {
        $body = $request->json() ?? $request->all();
        $email = $body['email'] ?? null;
        if (!$email) {
            $response->status(400)->json(['error' => 'missing_email']);
            return;
        }

        $user = User::findByEmail($email);
        if (!$user) {
            // do not reveal existence
            $response->json(['ok' => true]);
            return;
        }

        $token = bin2hex(random_bytes(32));
        $expires = (new \DateTime('+1 hour'))->format('Y-m-d H:i:s');
        User::setResetToken($email, $token, $expires);

        // send email (simple mail fallback). In production replace with a proper mailer.
        $resetUrl = ($_ENV['APP_URL'] ?? '') . '/admin/reset-password?email=' . urlencode($email) . '&token=' . urlencode($token);
        $subject = 'Password reset';
        $message = "Per resettare la password visita: $resetUrl\nQuesto link scade in 1 ora.";
        $headers = 'From: no-reply@' . ($_ENV['APP_DOMAIN'] ?? 'example.com') . "\r\n";
        @mail($email, $subject, $message, $headers);

        $response->json(['ok' => true]);
    }

    /**
     * Confirm and apply a password reset using a temporary token.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     * @since 0.0.1
     */
    public function confirmPasswordReset(Request $request, Response $response): void
    {
        $body = $request->json() ?? $request->all();
        $email = $body['email'] ?? null;
        $token = $body['token'] ?? null;
        $newPassword = $body['password'] ?? null;

        if (!$email || !$token || !$newPassword) {
            $response->status(400)->json(['error' => 'missing_params']);
            return;
        }

        if (!User::verifyResetToken($email, $token)) {
            $response->status(400)->json(['error' => 'invalid_or_expired_token']);
            return;
        }

        if (!User::updatePassword($email, $newPassword)) {
            $response->status(500)->json(['error' => 'update_failed']);
            return;
        }

        $response->json(['ok' => true]);
    }
}
