<?php

declare(strict_types=1);

/** Thrown when a change would leave no active admin who can log in. */
final class LastAdminException extends RuntimeException
{
}

/**
 * Members: the person record (e-mail, admin flag, status). A member may
 * exist before the person has an account; credentials live in `users`.
 * The admin flag and status are read from here on every request.
 */
final class Members
{
    public const STATUSES = ['active', 'disabled'];

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

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT id, email, is_admin, status, created_by, claimed_at
            FROM members WHERE email = ?');
        $stmt->execute([self::normalizeEmail($email)]);
        $row = $stmt->fetch();
        return is_array($row) ? self::cast($row) : null;
    }

    /** Same as findByEmail(), locking the row (or the gap) until the transaction ends. */
    public static function findByEmailForUpdate(string $email): ?array
    {
        self::assertInTransaction();
        $stmt = db()->prepare('SELECT id, email, is_admin, status, created_by, claimed_at
            FROM members WHERE email = ? FOR UPDATE');
        $stmt->execute([self::normalizeEmail($email)]);
        $row = $stmt->fetch();
        return is_array($row) ? self::cast($row) : null;
    }

    /** Create an active member; returns its id. */
    public static function create(string $email, bool $isAdmin, ?int $createdBy): int
    {
        $valid = self::validEmail($email);
        if ($valid === null) {
            throw new InvalidArgumentException('Invalid e-mail address (ASCII only).');
        }
        $now = db_now();
        db()->prepare("INSERT INTO members (email, is_admin, status, created_by, created_at, updated_at)
            VALUES (?, ?, 'active', ?, ?, ?)")
            ->execute([$valid, $isAdmin ? 1 : 0, $createdBy, $now, $now]);
        return (int) db()->lastInsertId();
    }

    /** Record that the member now has an account (only the first time). */
    public static function markClaimed(int $id): void
    {
        $now = db_now();
        db()->prepare('UPDATE members SET claimed_at = ?, updated_at = ? WHERE id = ? AND claimed_at IS NULL')
            ->execute([$now, $now, $id]);
    }

    /**
     * Grant or revoke the admin flag. Revoking is guarded: it never removes
     * the last active admin who has an account (LastAdminException).
     */
    public static function setAdmin(int $id, bool $isAdmin): void
    {
        $sql = 'UPDATE members SET is_admin = ?, updated_at = ? WHERE id = ?';
        $params = [$isAdmin ? 1 : 0, db_now(), $id];
        if ($isAdmin) {
            db()->prepare($sql)->execute($params);
            return;
        }
        self::guarded($id, $sql, $params);
    }

    /**
     * Enable or disable a member. Disabling is guarded: it never removes
     * the last active admin who has an account (LastAdminException).
     */
    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unknown status: ' . $status);
        }
        $sql = 'UPDATE members SET status = ?, updated_at = ? WHERE id = ?';
        $params = [$status, db_now(), $id];
        if ($status === 'active') {
            db()->prepare($sql)->execute($params);
            return;
        }
        self::guarded($id, $sql, $params);
    }

    /** Active admins who have an account; with $lock, their rows stay locked until the transaction ends. */
    public static function countActiveAdminsWithAccount(bool $lock = false): int
    {
        if ($lock) {
            self::assertInTransaction();
        }
        $stmt = db()->prepare("SELECT m.id FROM members m JOIN users u ON u.member_id = m.id
            WHERE m.is_admin = 1 AND m.status = 'active'" . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute();
        return count($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Run an UPDATE that may take admin power away from member $id.
     * Uses the caller's transaction if one is open, otherwise its own.
     */
    private static function guarded(int $id, string $sql, array $params): void
    {
        $own = !db()->inTransaction();
        if ($own) {
            db()->beginTransaction();
        }
        try {
            $stmt = db()->prepare('SELECT m.id, m.is_admin, m.status, u.id AS user_id
                FROM members m LEFT JOIN users u ON u.member_id = m.id
                WHERE m.id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $target = $stmt->fetch();
            if (is_array($target) && (int) $target['is_admin'] === 1 && $target['status'] === 'active'
                && $target['user_id'] !== null && self::countActiveAdminsWithAccount(true) <= 1) {
                throw new LastAdminException('The last active admin with an account cannot lose admin rights.');
            }
            db()->prepare($sql)->execute($params);
            if ($own) {
                db()->commit();
            }
        } catch (Throwable $e) {
            if ($own && db()->inTransaction()) {
                db()->rollBack();
            }
            throw $e;
        }
    }

    private static function assertInTransaction(): void
    {
        if (!db()->inTransaction()) {
            throw new LogicException('FOR UPDATE needs an open transaction.');
        }
    }

    private static function cast(array $row): array
    {
        $row['id'] = (int) $row['id'];
        $row['is_admin'] = (int) $row['is_admin'] === 1;
        $row['created_by'] = $row['created_by'] === null ? null : (int) $row['created_by'];
        return $row;
    }
}
