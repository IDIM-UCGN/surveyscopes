<?php

declare(strict_types=1);

/*
 * Creating and recovering admins, shared by tools/create-admin.php and
 * (later) the setup page. No SQL here: everything goes through the
 * repositories. Requires src/bootstrap.php.
 */

/** The address already exists and recovery was not requested. */
final class AdminExistsException extends RuntimeException
{
}

/**
 * Create an admin (member + account) or, with $allowUpdate, recover an
 * existing address: admin flag on, status active, new password (an
 * account is created if the member had none). Everything that changes the
 * member or the account happens in one transaction.
 *
 * Returns ['result' => 'created'|'updated', 'email', 'member_id',
 * 'user_id', 'warning' => ?string].
 */
function provision_admin(string $email, string $password, bool $allowUpdate, ?int $createdBy = null): array
{
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
