<?php

declare(strict_types=1);

/**
 * Failed login / setup attempts for throttling.
 * Keys are HMAC-SHA256 hashes (see auth.php); no e-mail or IP is stored.
 */
final class LoginAttempts
{
    public const TYPES = ['pair', 'account', 'ip', 'setup'];

    /** Number of attempts for one key within the last $minutes. */
    public static function countRecent(string $type, string $hash, int $minutes): int
    {
        self::assertType($type);
        $stmt = db()->prepare('SELECT COUNT(*) FROM login_attempts
            WHERE key_type = ? AND key_hash = ? AND attempted_at > ?');
        $stmt->execute([$type, $hash, gmdate('Y-m-d H:i:s', time() - $minutes * 60)]);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, string> $keys key_type => key_hash */
    public static function record(array $keys): void
    {
        $now = db_now();
        $stmt = db()->prepare('INSERT INTO login_attempts (key_type, key_hash, attempted_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?)');
        foreach ($keys as $type => $hash) {
            self::assertType($type);
            $stmt->execute([$type, $hash, $now, $now, $now]);
        }
    }

    /** @param array<string, string> $keys key_type => key_hash */
    public static function clear(array $keys): void
    {
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE key_type = ? AND key_hash = ?');
        foreach ($keys as $type => $hash) {
            self::assertType($type);
            $stmt->execute([$type, $hash]);
        }
    }

    /** Delete attempts older than $hours; returns the number of deleted rows. */
    public static function purgeOlderThan(int $hours): int
    {
        $stmt = db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?');
        $stmt->execute([gmdate('Y-m-d H:i:s', time() - $hours * 3600)]);
        return $stmt->rowCount();
    }

    private static function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown attempt type: ' . $type);
        }
    }
}
