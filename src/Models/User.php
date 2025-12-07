<?php

namespace Immaginificio\OAuthProxyBridge\Models;

use Immaginificio\OAuthProxyBridge\Core\Database;

/**
 * User model for admin authentication and password recovery
 *
 * @package Immaginificio\OAuthProxyBridge\Models
 * @since 0.0.1
 */
class User
{
    /**
     * Retrieve a user by email.
     *
     * @param string $email
     * @return array|null
     * @since 0.0.1
     */
    public static function findByEmail(string $email): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Retrieve a user by ID.
     *
     * @param int $id
     * @return array|null
     * @since 0.0.1
     */
    public static function findById(int $id): ?array
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
      * Verify a user's password given their email.
     *
     * @param string $email
     * @param string $password
     * @return bool
     * @since 0.0.1
     */
    public static function verifyPassword(string $email, string $password): bool
    {
        $user = self::findByEmail($email);
        if (!$user) {
            return false;
        }
        return password_verify($password, $user['password_hash']);
    }

    /**
     * Create a new admin user.
     *
     * @param string $email
     * @param string $password
     * @param string|null $name
     * @param bool $isAdmin
     * @return int|null Insert ID or null on failure
     * @since 0.0.1
     */
    public static function create(string $email, string $password, ?string $name = null, bool $isAdmin = true): ?int
    {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name, is_admin) VALUES (:email, :hash, :name, :is_admin)');
            $stmt->execute([':email' => $email, ':hash' => $hash, ':name' => $name, ':is_admin' => $isAdmin ? 1 : 0]);
            return (int)$pdo->lastInsertId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Set a password reset token for the user.
     *
     * @param string $email
     * @param string $token
     * @param string $expiresAt Datetime string
     * @return bool
     * @since 0.0.1
     */
    public static function setResetToken(string $email, string $token, string $expiresAt): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE users SET reset_token = :token, reset_expires_at = :expires WHERE email = :email');
            $stmt->execute([':token' => $token, ':expires' => $expiresAt, ':email' => $email]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Verify that a reset token is valid and not expired.
     *
     * @param string $email
     * @param string $token
     * @return bool
     * @since 0.0.1
     */
    public static function verifyResetToken(string $email, string $token): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('SELECT reset_token, reset_expires_at FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || empty($row['reset_token']) || empty($row['reset_expires_at'])) {
                return false;
            }
            if (!hash_equals($row['reset_token'], $token)) {
                return false;
            }
            return (new \DateTime()) <= new \DateTime($row['reset_expires_at']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Update the user's password and clear the reset token.
     *
     * @param string $email
     * @param string $newPassword
     * @return bool
     * @since 0.0.1
     */
    public static function updatePassword(string $email, string $newPassword): bool
    {
        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, reset_token = NULL, reset_expires_at = NULL WHERE email = :email');
            $stmt->execute([':hash' => $hash, ':email' => $email]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Update a user's profile (name and/or password).
     * Used to allow the current user to update their profile.
     *
     * @param int $id
     * @param array $data ['name' => string|null, 'password' => string|null]
     * @return bool
     * @since 0.0.1
     */
    public static function updateProfile(int $id, array $data): bool
    {
        try {
            $pdo = Database::getConnection();
            $fields = [];
            $params = [':id' => $id];

            if (isset($data['name'])) {
                $fields[] = 'name = :name';
                $params[':name'] = $data['name'];
            }

            if (!empty($data['password'])) {
                $fields[] = 'password_hash = :hash';
                $params[':hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            // allow updating email and admin flag (used by admins)
            if (isset($data['email'])) {
                $fields[] = 'email = :email';
                $params[':email'] = $data['email'];
            }

            if (array_key_exists('is_admin', $data)) {
                $fields[] = 'is_admin = :is_admin';
                $params[':is_admin'] = $data['is_admin'] ? 1 : 0;
            }

            if (empty($fields)) {
                return true; // nothing to update
            }

            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Delete a user by ID.
     *
     * @param int $id
     * @return bool
     * @since 0.0.1
     */
    public static function deleteById(int $id): bool
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
