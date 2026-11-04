<?php

declare(strict_types=1);

function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

/** URL of a page in a locale: url('index') -> /de/, url('surveys', 'en') -> /en/surveys */
function url(string $page = 'index', ?string $locale = null, bool $absolute = false): string
{
    $path = '/' . ($locale ?? lang()) . '/' . ($page === 'index' ? '' : $page);
    if (!$absolute) {
        return $path;
    }
    $base = rtrim((string) config('base_url', ''), '/');
    if ($base === '' && isset($_SERVER['HTTP_HOST'])) {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $base = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    }
    return $base . $path;
}

/** Absolute URL without language prefix (x-default, sitemap, robots). */
function url_default(string $page = 'index'): string
{
    $absolute = url('index', default_locale(), true);
    $base = substr($absolute, 0, -strlen('/' . default_locale() . '/'));
    return $base . '/' . ($page === 'index' ? '' : $page);
}

function current_page(): string
{
    return $GLOBALS['ss_page'] ?? 'index';
}

/** Render templates/<name>.php inside the layout. */
function render(string $template, array $vars = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    extract($vars, EXTR_SKIP);
    require SS_ROOT . '/templates/layout-header.php';
    require SS_ROOT . '/templates/' . $template . '.php';
    require SS_ROOT . '/templates/layout-footer.php';
}

/** Render the 404 page and stop. */
function not_found(): void
{
    $GLOBALS['ss_page'] = 'index';
    render('404', ['pageTitle' => tr('404.title'), 'noIndex' => true], 404);
    exit;
}

/**
 * Minimal, safe text markup for admin-managed content:
 * blank line = paragraph, "## " section heading (h2), "### " sub-heading (h3),
 * "- " lists,
 * **bold**, *italic*, [text](https://…). Everything is escaped first.
 */
function markup(string $text): string
{
    $inline = static function (string $line): string {
        $line = e($line);
        $line = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $line);
        $line = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $line);
        return preg_replace_callback(
            '/\[([^\]]+)\]\(((?:https?:\/\/|mailto:|\/)[^\s)]+)\)/',
            fn (array $m): string => '<a href="' . $m[2] . '">' . $m[1] . '</a>',
            $line
        );
    };

    $html = '';
    foreach (preg_split("/\R{2,}/", trim(str_replace("\r", '', $text))) as $block) {
        $lines = explode("\n", $block);
        // A heading line may be followed directly by its text (no blank line).
        if (preg_match('/^(#{1,3}) (.+)$/', $lines[0], $m) === 1) {
            $level = max(2, strlen($m[1])); // the page title is the only <h1>
            $html .= "<h{$level}>" . $inline($m[2]) . "</h{$level}>\n";
            array_shift($lines);
            if ($lines === []) {
                continue;
            }
        }
        if (array_filter($lines, fn ($l) => !str_starts_with($l, '- ')) === []) {
            $html .= "<ul>\n" . implode('', array_map(
                fn ($l) => '<li>' . $inline(substr($l, 2)) . "</li>\n",
                $lines
            )) . "</ul>\n";
        } else {
            $html .= '<p>' . implode("<br>\n", array_map($inline, $lines)) . "</p>\n";
        }
    }
    return $html;
}
