<?php

declare(strict_types=1);

/**
 * Accounts for the admin area: site admins and editors only.
 * Participants never have accounts. E-mail addresses are stored normalised.
 */
final class Users
{
    public const ROLES = ['admin', 'editor'];

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    /**
     * Normalised address if it is a valid ASCII e-mail address (no
     * internationalised local parts or domains), else null.
     */
    public static function validEmail(string $email): ?string
    {
        $normalized = self::normalizeEmail($email);
        return strlen($normalized) <= 254 && filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false
            ? $normalized : null;
    }

    /** Full row including password_hash, or null. */
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT id, email, password_hash, role, status, last_login_at
            FROM users WHERE email = ?');
        $stmt->execute([self::normalizeEmail($email)]);
        $row = $stmt->fetch();
        return is_array($row) ? self::cast($row) : null;
    }

    /** Active account without password_hash, or null (disabled or deleted). */
    public static function findActiveById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT id, email, role, status, last_login_at
            FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? self::cast($row) : null;
    }

    /** Create an active account; returns its id. */
    public static function create(string $email, string $passwordHash, string $role): int
    {
        self::assertRole($role);
        $valid = self::validEmail($email);
        if ($valid === null) {
            throw new InvalidArgumentException('Invalid e-mail address (ASCII only).');
        }
        $now = db_now();
        db()->prepare("INSERT INTO users (email, password_hash, role, status, created_at, updated_at)
            VALUES (?, ?, ?, 'active', ?, ?)")
            ->execute([$valid, $passwordHash, $role, $now, $now]);
        return (int) db()->lastInsertId();
    }

    /** Recovery: new password, role, and re-activate the account. */
    public static function setPasswordRoleActive(int $id, string $passwordHash, string $role): void
    {
        self::assertRole($role);
        db()->prepare("UPDATE users SET password_hash = ?, role = ?, status = 'active', updated_at = ?
            WHERE id = ?")
            ->execute([$passwordHash, $role, db_now(), $id]);
    }

    /** Store a rehashed password (PASSWORD_DEFAULT changed). */
    public static function updatePasswordHash(int $id, string $passwordHash): void
    {
        db()->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?')
            ->execute([$passwordHash, db_now(), $id]);
    }

    public static function touchLogin(int $id): void
    {
        $now = db_now();
        db()->prepare('UPDATE users SET last_login_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$now, $now, $id]);
    }

    private static function assertRole(string $role): void
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException('Unknown role: ' . $role);
        }
    }

    private static function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        return $row;
    }
}
