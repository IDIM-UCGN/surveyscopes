<?php

declare(strict_types=1);

/*
 * Creating and recovering admins, shared by tools/create-admin.php and the
 * setup page (setup.php). No SQL here: everything goes through the
 * repositories. Requires src/bootstrap.php.
 */

/** The address already exists and recovery was not requested. */
final class AdminExistsException extends RuntimeException
{
}

/** The setup token file could not be deleted, so setup must not complete. */
final class SetupTokenException extends RuntimeException
{
}

const SS_SETUP_TOKEN_MIN_LENGTH = 32;
const SS_SETUP_TOKEN_MAX_BYTES = 1024;

/**
 * Create an admin (member + account) or, with $allowUpdate, recover an
 * existing address: admin flag on, status active, new password (an
 * account is created if the member had none). Everything that changes the
 * member or the account happens in one transaction.
 *
 * $beforeCommit, if given, runs after the changes and before the commit; if
 * it throws, everything is rolled back and the exception is rethrown. The
 * setup page uses it to delete its token file, so a token cannot be used twice.
 *
 * Returns ['result' => 'created'|'updated', 'email', 'member_id',
 * 'user_id', 'warning' => ?string].
 */
function provision_admin(
    string $email,
    string $password,
    bool $allowUpdate,
    ?int $createdBy = null,
    ?callable $beforeCommit = null
): array {
    $valid = Members::validEmail($email);
    if ($valid === null) {
        throw new InvalidArgumentException('Invalid e-mail address (ASCII only, at most 254 characters).');
    }
    $problem = validate_new_password($password);
    if ($problem !== null) {
        $messages = [
            'too_short' => 'The password must have at least 12 characters.',
            'too_long' => 'The password must not be longer than 72 bytes.',
            'invalid' => 'The password contains invalid characters.',
        ];
        throw new InvalidArgumentException($messages[$problem] ?? 'The password is not acceptable.');
    }
    // Hash before the transaction, so no row stays locked while bcrypt runs.
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $member = Members::findByEmailForUpdate($valid);
        if ($member === null) {
            $memberId = Members::create($valid, true, $createdBy);
            $userId = Users::create($memberId, $hash);
            Members::markClaimed($memberId);
            $result = 'created';
        } elseif (!$allowUpdate) {
            throw new AdminExistsException('This address already exists.');
        } else {
            $memberId = (int) $member['id'];
            // Granting and enabling: plain updates inside this transaction (no guard needed).
            Members::setAdmin($memberId, true);
            Members::setStatus($memberId, 'active');
            $userId = Users::findIdByMemberIdForUpdate($memberId);
            if ($userId === null) {
                $userId = Users::create($memberId, $hash);
                Members::markClaimed($memberId);
            } else {
                Users::setPassword($userId, $hash);
            }
            $result = 'updated';
        }
        if ($beforeCommit !== null) {
            $beforeCommit();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    // Lift a lockout of this address. The per-IP "pair" keys cannot be found
    // from the address alone; they expire on their own within the window.
    $warning = null;
    if (auth_key() !== null) {
        LoginAttempts::clear(['account' => auth_hmac('account', $valid)]);
    } else {
        $warning = 'app_key is missing or shorter than 32 characters, so throttle rows were not cleared.';
    }

    return [
        'result' => $result,
        'email' => $valid,
        'member_id' => $memberId,
        'user_id' => $userId,
        'warning' => $warning,
    ];
}

/* ---------------------------------------------------------- web setup */

/**
 * Path of the setup token file. The operator creates it (for example by FTP);
 * it enables the setup page and is deleted when setup succeeds.
 */
function setup_token_path(): string
{
    return SS_ROOT . '/data/setup-token';
}

/**
 * Seconds since the token file was created or last changed. The later of
 * mtime and ctime counts, so touching a file cannot make it look older.
 */
function setup_token_age(int $mtime, int $ctime, int $now): int
{
    return max(0, $now - max($mtime, $ctime));
}

/**
 * The setup token, or null if setup is not enabled: no file, a file older
 * than auth.setup_token_max_age_hours, or a token shorter than 32
 * characters. A rejected file is logged, but only on about 1 in 100 calls.
 */
function setup_token(?string $path = null, ?int $now = null): ?string
{
    $path ??= setup_token_path();
    if (!is_file($path)) {
        return null;
    }
    $mtime = @filemtime($path);
    $ctime = @filectime($path);
    $raw = @file_get_contents($path, false, null, 0, SS_SETUP_TOKEN_MAX_BYTES);
    if ($mtime === false || $ctime === false || $raw === false) {
        return null;
    }

    $reason = null;
    $token = trim($raw);
    if (setup_token_age($mtime, $ctime, $now ?? time()) > auth_setting('setup_token_max_age_hours') * 3600) {
        $reason = 'older than ' . auth_setting('setup_token_max_age_hours') . ' hours';
    } elseif (strlen($token) < SS_SETUP_TOKEN_MIN_LENGTH) {
        $reason = 'shorter than ' . SS_SETUP_TOKEN_MIN_LENGTH . ' characters';
    }
    if ($reason !== null) {
        if (random_int(1, 100) === 1) {
            error_log('[surveyscopes] setup token file ignored: ' . $reason);
        }
        return null;
    }
    return $token;
}

/**
 * Process the setup form. Returns 'done', or the key of the problem; the
 * page shows the translation "setup.<key>": failed (wrong token), throttled,
 * unavailable, invalid_email, mismatch, too_short, too_long, invalid,
 * not_writable, delete_failed.
 *
 * An existing address is recovered (admin flag, active, new password), the
 * same as tools/create-admin.php --update: whoever can write the token file
 * into data/ is trusted at that level. The token file is deleted inside the
 * database transaction; if that fails, nothing is changed.
 */
function setup_run(mixed $tokenInput, mixed $email, mixed $password, mixed $repeat, ?string $tokenPath = null): string
{
    $tokenPath ??= setup_token_path();
    if (!auth_available()) {
        return 'unavailable';
    }
    $token = setup_token($tokenPath);
    if ($token === null) {
        return 'failed';
    }

    // Wrong tokens are throttled per client address, like failed logins.
    $keys = ['setup' => auth_hmac('setup', client_ip_key())];
    if (is_throttled($keys)) {
        return 'throttled';
    }
    if (!is_string($tokenInput) || !hash_equals($token, trim($tokenInput))) {
        record_failure($keys);
        return 'failed';
    }

    if (!is_string($email) || Members::validEmail($email) === null) {
        return 'invalid_email';
    }
    if (!is_string($password) || !is_string($repeat) || $password !== $repeat) {
        return 'mismatch';
    }
    $problem = validate_new_password($password);
    if ($problem !== null) {
        return $problem;
    }
    if (!is_writable(dirname($tokenPath))) {
        return 'not_writable';
    }

    try {
        provision_admin($email, $password, true, null, static function () use ($tokenPath): void {
            if (!@unlink($tokenPath)) {
                throw new SetupTokenException('The setup token file could not be deleted.');
            }
        });
    } catch (SetupTokenException) {
        return 'delete_failed';
    }
    return 'done';
}
