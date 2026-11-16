<?php

declare(strict_types=1);

/**
 * Login credentials of members who have claimed an account. The e-mail
 * address, admin flag and status belong to the member (see Members) and
 * are read through a join, so they are current on every request.
 */
final class Users
{
    /** Account with its member's e-mail, flag and status, including password_hash, or null. */
    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT u.id, u.member_id, m.email, m.display_name, m.is_admin, m.create_surveys_allowed, m.status, u.password_hash, u.last_login_at
            FROM users u JOIN members m ON m.id = u.member_id
            WHERE m.email = ?');
        $stmt->execute([Members::normalizeEmail($email)]);
        $row = $stmt->fetch();
        return is_array($row) ? self::cast($row) : null;
    }

    /** Account of an active member, without password_hash, or null (disabled or deleted). */
    public static function findActiveById(int $id): ?array
    {
        $stmt = db()->prepare("SELECT u.id, u.member_id, m.email, m.display_name, m.is_admin, m.create_surveys_allowed, m.status, u.last_login_at
            FROM users u JOIN members m ON m.id = u.member_id
            WHERE u.id = ? AND m.status = 'active'");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return is_array($row) ? self::cast($row) : null;
    }

    /** Id of the member's account, locked until the transaction ends, or null. */
    public static function findIdByMemberIdForUpdate(int $memberId): ?int
    {
        if (!db()->inTransaction()) {
            throw new LogicException('FOR UPDATE needs an open transaction.');
        }
        $stmt = db()->prepare('SELECT id FROM users WHERE member_id = ? FOR UPDATE');
        $stmt->execute([$memberId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Create the account of a member; returns its id. */
    public static function create(int $memberId, string $passwordHash): int
    {
        $now = db_now();
        db()->prepare('INSERT INTO users (member_id, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?)')
            ->execute([$memberId, $passwordHash, $now, $now]);
        return (int) db()->lastInsertId();
    }

    /** Store a new password hash (recovery, or a rehash after PASSWORD_DEFAULT changed). */
    public static function setPassword(int $id, string $passwordHash): void
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

    private static function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['member_id'] = (int) $row['member_id'];
        $row['is_admin'] = (int) $row['is_admin'] === 1;
        $row['create_surveys_allowed'] = (int) $row['create_surveys_allowed'] === 1;
        return $row;
    }
}
