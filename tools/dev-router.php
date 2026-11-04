<?php

declare(strict_types=1);

/*
 * Router for PHP's built-in server, emulating .htaccess (without www/HTTPS):
 *   php -S localhost:8000 tools/dev-router.php
 */

$root = dirname(__DIR__);
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$qs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';

$serve = static function (string $file) use ($root): void {
    chdir($root);
    require $root . '/' . $file;
    exit;
};
$redirect = static function (string $to) use ($qs): void {
    header('Location: ' . $to . $qs, true, 301);
    exit;
};

if (preg_match('#^/(src|templates|lang|config|data|uploads|tools|docs)(/|$)|^/(VERSION|\.maintenance)$#', $path)) {
    $serve('404.php');
}
if ($path === '/sitemap.xml') {
    $serve('sitemap.php');
}
if ($path === '/robots.txt') {
    $serve('robots.php');
}
if (preg_match('#^/(sitemap|robots)/?$#', $path)) {
    $serve('404.php');
}
if (preg_match('#^(.*/)?index\.php$#', $path, $m)) {
    $redirect($m[1] ?? '/');
}
if (preg_match('#^/(.+)\.php$#', $path, $m)) {
    $redirect('/' . $m[1]);
}
if ($path !== '/' && is_file($root . $path)) {
    return false;
}
if ($path === '/') {
    $serve('index.php');
}
if (preg_match('#^/([a-z]{2})$#', $path, $m)) {
    $redirect('/' . $m[1] . '/');
}
if (preg_match('#^/([a-z]{2})/index/?$#', $path, $m)) {
    $redirect('/' . $m[1] . '/');
}
if (preg_match('#^/([a-z]{2})/([a-z][a-z0-9-]*)/$#', $path, $m)) {
    $redirect('/' . $m[1] . '/' . $m[2]);
}
if (preg_match('#^/[a-z]{2}/$#', $path)) {
    $serve('index.php');
}
if (preg_match('#^/[a-z]{2}/survey/([a-z0-9-]+)$#', $path, $m)) {
    $_GET['slug'] = $m[1];
    $serve('survey.php');
}
if (preg_match('#^/(?:[a-z]{2}/)?([a-z][a-z0-9-]*)/?$#', $path, $m) && is_file($root . '/' . $m[1] . '.php')) {
    $serve($m[1] . '.php');
}
$serve('404.php');
