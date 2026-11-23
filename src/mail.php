<?php

declare(strict_types=1);

/*
 * Sending plain-text e-mail: PHP's mail(), or a log transport that writes the
 * message to a file instead (development and tests). No dependencies.
 * Requires src/bootstrap.php (Members, write_file_atomic(), config()).
 *
 * Rules (docs/architecture.md §10): recipient and sender are validated ASCII
 * addresses without CR/LF; every header is fixed by this file, and the only
 * request-derived header value is the cleaned subject; the body is plain text.
 */

const SS_MAIL_TRANSPORTS = ['mail', 'log'];
const SS_MAIL_SUBJECT_MAX_CHARS = 100; // 100 characters of 4 bytes each still fit in one header line (998)

/**
 * Send a plain-text message. Returns true if it was handed to mail() or, with
 * the log transport, written to a file; false if nothing was sent: invalid
 * recipient or sender, empty subject, unknown transport, or a failure. Failures
 * are logged without addresses or content. "True" does not mean "delivered".
 */
function send_mail(string $to, string $subject, string $body): bool
{
    $transport = mail_transport();
    if ($transport === null) {
        return false;
    }
    $message = mail_compose($to, $subject, $body);
    if ($message === null) {
        return false;
    }
    return $transport === 'log' ? mail_deliver_log($message) : mail_deliver_mail($message);
}

/** Log a problem once per request (config problems repeat on every send otherwise). */
function mail_log_once(string $message): void
{
    static $logged = [];
    if (!isset($logged[$message])) {
        $logged[$message] = true;
        error_log('[surveyscopes] ' . $message);
    }
}

/** The configured transport ('mail' or 'log'), or null for any other value: nothing is sent then. */
function mail_transport(): ?string
{
    $value = config('mail.transport', 'mail');
    if (is_string($value) && in_array($value, SS_MAIL_TRANSPORTS, true)) {
        return $value;
    }
    mail_log_once('invalid config mail.transport (must be "mail" or "log"), nothing will be sent');
    return null;
}

/** The sender address from config mail.from, or null if it is missing or not a valid address. */
function mail_from(): ?string
{
    $from = config('mail.from');
    $valid = is_string($from) ? Members::validEmail($from) : null;
    if ($valid === null) {
        mail_log_once('config mail.from is missing or not a valid address, nothing will be sent');
    }
    return $valid;
}

/**
 * Optional bounce address (config mail.envelope_from), passed to the mail
 * transfer agent with -f. Only plain characters are accepted because the
 * value ends up on a command line; anything else is ignored.
 */
function mail_envelope_from(): ?string
{
    $value = config('mail.envelope_from');
    if ($value === null || $value === '') {
        return null;
    }
    $valid = is_string($value) ? Members::validEmail($value) : null;
    if ($valid === null || preg_match('/^[a-z0-9._+-]+@[a-z0-9.-]+$/', $valid) !== 1) {
        mail_log_once('config mail.envelope_from is not a plain valid address, ignored');
        return null;
    }
    return $valid;
}

/**
 * Subject as stored in the message: control and invisible formatting
 * characters (which include CR and LF) become spaces, whitespace is
 * collapsed, at most 100 characters. Null if nothing is left or the text is
 * not valid UTF-8.
 */
function mail_clean_subject(string $subject): ?string
{
    $clean = preg_replace('/[\p{Cc}\p{Cf}\s]+/u', ' ', $subject);
    if (!is_string($clean)) {
        return null; // invalid UTF-8
    }
    $clean = trim($clean);
    if ($clean === '') {
        return null;
    }
    return mb_strlen($clean, 'UTF-8') > SS_MAIL_SUBJECT_MAX_CHARS ? mb_substr($clean, 0, SS_MAIL_SUBJECT_MAX_CHARS, 'UTF-8') : $clean;
}

/**
 * Build the message, or null if the recipient, sender, subject or body is unusable.
 * Returns ['to', 'from', 'subject' (MIME-encoded), 'headers' (name => value,
 * all fixed by this function), 'body' (quoted-printable, CRLF line ends), 'envelope' (?string)].
 */
function mail_compose(string $to, string $subject, string $body, ?int $time = null): ?array
{
    $recipient = Members::validEmail($to);
    $from = $recipient === null ? null : mail_from();
    $cleanSubject = $from === null ? null : mail_clean_subject($subject);
    if ($recipient === null || $from === null || $cleanSubject === null || !mb_check_encoding($body, 'UTF-8')) {
        return null;
    }

    $text = str_replace(["\r\n", "\r"], "\n", $body);
    // The indent is the length of "Subject: ", so folded lines stay within 78 characters.
    $encodedSubject = mb_encode_mimeheader($cleanSubject, 'UTF-8', 'B', "\r\n", strlen('Subject: '));
    return [
        'to' => $recipient,
        'from' => $from,
        'subject' => $encodedSubject,
        'headers' => [
            'From' => $from,
            'To' => $recipient,
            'Subject' => $encodedSubject,
            'Date' => gmdate('r', $time ?? time()),
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => 'quoted-printable',
        ],
        'body' => quoted_printable_encode(str_replace("\n", "\r\n", $text)),
        'envelope' => mail_envelope_from(),
    ];
}

/** Hand the message to PHP's mail(). */
function mail_deliver_mail(array $message): bool
{
    // mail() adds To and Subject itself; everything else goes in as additional headers.
    $headers = array_diff_key($message['headers'], ['To' => 1, 'Subject' => 1]);
    // mail() wants the subject without line breaks: unfold the encoded words (the .eml keeps the folded form).
    $subject = (string) preg_replace('/\r\n[ \t]+/', ' ', $message['subject']);
    // PHP's manual: Unix mail transfer agents expect LF in the body (CRLF can become CRCRLF); Windows needs CRLF.
    $body = PHP_OS_FAMILY === 'Windows' ? $message['body'] : str_replace("\r\n", "\n", $message['body']);
    $params = $message['envelope'] !== null ? '-f' . $message['envelope'] : '';

    $sent = @mail($message['to'], $subject, $body, $headers, $params);
    if (!$sent) {
        error_log('[surveyscopes] mail() reported a failure; the message was not sent (no address or content logged)');
    }
    return $sent;
}

/** Directory of the log transport: config mail.log_dir, default data/mail. */
function mail_log_dir(): string
{
    $dir = config('mail.log_dir');
    return is_string($dir) && $dir !== '' ? rtrim($dir, '/\\') : SS_ROOT . '/data/mail';
}

/**
 * Log transport: write the complete message as an .eml file (mode 0600) instead
 * of sending it. For development and tests only: the file can contain live links.
 */
function mail_deliver_log(array $message): bool
{
    $dir = mail_log_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        error_log('[surveyscopes] mail log directory cannot be created');
        return false;
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }

    $lines = [];
    foreach ($message['headers'] as $name => $value) {
        $lines[] = $name . ': ' . $value;
    }
    $name = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.eml';
    if (!write_file_atomic($dir . '/' . $name, implode("\r\n", $lines) . "\r\n\r\n" . $message['body'], 0600)) {
        error_log('[surveyscopes] mail log file cannot be written');
        return false;
    }
    error_log('[surveyscopes] mail transport "log": message written to ' . $name . ' (development only, the file may contain live links)');
    return true;
}
