<?php

declare(strict_types=1);

/*
 * Stand-in for sendmail in tests/unit.php (Linux and CI only). PHP's mail()
 * pipes the message to the program named by sendmail_path; this script records
 * its arguments and the message it receives on standard input, as JSON, in the
 * file named by its first argument:
 *
 *   php -d sendmail_path="php tests/fake-sendmail.php <outfile>" ...
 */

if (PHP_SAPI !== 'cli' || !isset($argv[1])) {
    exit(1);
}

file_put_contents($argv[1], json_encode([
    'args' => array_slice($argv, 2),
    'message' => stream_get_contents(STDIN),
], JSON_THROW_ON_ERROR));
