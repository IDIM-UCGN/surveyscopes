<?php

declare(strict_types=1);

/*
 * CLI: create the first admin, or recover access to an existing address.
 *
 *   php tools/create-admin.php <email> [--update]
 *
 * Without --update an existing address is refused. With --update the
 * address gets the admin flag, is enabled again and gets the new password
 * (an account is created if it had none); throttle rows for the address
 * are cleared.
 *
 * Password: typed twice at a prompt. It is hidden only on POSIX systems
 * with a terminal, stty and shell_exec(). On Windows (and wherever these
 * are missing) the password is VISIBLE while you type it.
 *
 * SS_PASSWORD is for CI only: it is read only when the environment has
 * CI=true, and setting it anywhere else is refused, so a password does not
 * end up in a shell history or process list by accident.
 *
 * Exit code 0 on success; 1 on any error, with one line on STDERR.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$usage = 'Usage: php tools/create-admin.php <email> [--update]';

/** Print one line on STDERR and exit with 1. */
$fail = static function (string $message): void {
    fwrite(STDERR, str_replace(["\r", "\n"], ' ', $message) . "\n");
    exit(1);
};

/** Can input be hidden with stty on this system? */
$canHide = static function (): bool {
    return DIRECTORY_SEPARATOR === '/' && function_exists('shell_exec') && stream_isatty(STDIN);
};

/** Read one line from STDIN, hidden if possible. */
$prompt = static function (string $label) use ($canHide): string {
    fwrite(STDERR, $label);
    $hide = $canHide();
    if ($hide) {
        shell_exec('stty -echo 2>/dev/null');
    }
    $line = fgets(STDIN);
    if ($hide) {
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDERR, "\n");
    }
    if ($line === false) {
        throw new RuntimeException('No password entered.');
    }
    return rtrim($line, "\r\n");
};

try {
    $email = null;
    $update = false;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--update') {
            $update = true;
        } elseif (str_starts_with($arg, '-')) {
            $fail('Unknown option ' . $arg . '. ' . $usage);
        } elseif ($email === null) {
            $email = $arg;
        } else {
            $fail('Too many arguments. ' . $usage);
        }
    }
    if ($email === null) {
        $fail('Missing e-mail address. ' . $usage);
    }

    $_SERVER['REQUEST_URI'] = '/';
    require __DIR__ . '/../src/bootstrap.php';
    require __DIR__ . '/../src/setup.php';

    if (!db_configured()) {
        $fail('No database configured.');
    }

    $envPassword = getenv('SS_PASSWORD');
    if ($envPassword !== false) {
        if (getenv('CI') !== 'true') {
            $fail('SS_PASSWORD is only accepted in CI (CI=true). Unset it and use the prompt.');
        }
        $password = $envPassword;
    } else {
        if (!stream_isatty(STDIN)) {
            $fail('No terminal for the password prompt (SS_PASSWORD is only accepted in CI).');
        }
        if (!$canHide()) {
            fwrite(STDERR, "Warning: the password will be VISIBLE while you type (no stty on this system, e.g. Windows).\n");
        }
        $password = $prompt('Password (12 characters to 72 bytes): ');
        if (!hash_equals($password, $prompt('Repeat password: '))) {
            $fail('The passwords do not match.');
        }
    }

    run_migrations(db());
    $result = provision_admin($email, $password, $update);

    echo $result['result'] . ' ' . $result['email'] . "\n";
    if ($result['warning'] !== null) {
        fwrite(STDERR, 'Warning: ' . $result['warning'] . "\n");
    }
    exit(0);
} catch (AdminExistsException $e) {
    $fail('This address already exists. Use --update to recover it.');
} catch (Throwable $e) {
    $fail(get_class($e) . ': ' . $e->getMessage());
}
