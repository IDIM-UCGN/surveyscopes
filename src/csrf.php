<?php

declare(strict_types=1);

/*
 * CSRF protection for every form (POST). The token lives in the session,
 * so only pages that already use a session (login, admin) call these.
 * On failure, the calling page answers with HTTP 400.
 */

/** Per-session token (32 random bytes, hex). */
function csrf_token(): string
{
    session_begin();
    if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** Hidden form field with the token. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** True if the POSTed token matches the session token. */
function csrf_verify(): bool
{
    session_begin();
    $sent = $_POST['_csrf'] ?? null;
    $expected = $_SESSION['_csrf'] ?? null;
    return is_string($sent) && is_string($expected) && hash_equals($expected, $sent);
}
