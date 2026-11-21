<?php
// PHP development server router. Bind the server to 127.0.0.1 only.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root = realpath(__DIR__ . '/../.local-test/wordpress');
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (!$root || strpos($path, "\0") !== false || strpos($path, '\\') !== false || preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) {
    http_response_code(400); exit('Invalid path');
}
if (preg_match('~(?:^|/)(?:\.[^/]*|wp-config\.php|db\.php|readme\.html|license\.txt)(?:/|$)|\.(?:sqlite|db|log|ini|bak|sql)(?:$|/)~i', $path)) {
    http_response_code(404); exit;
}
$target = realpath($root . $path);
if ($target && strpos(str_replace('\\', '/', $target), str_replace('\\', '/', $root) . '/') === 0 && is_file($target)) {
    // Uploaded files must never be executed as PHP by the development server.
    if (strpos($path, '/wp-content/uploads/') === 0 && preg_match('/\.(?:php[0-9]?|phtml|phar)$/i', $path)) {
        http_response_code(403); exit;
    }
    return false;
}
if ($target && is_dir($target) && ($target === $root || strpos(str_replace('\\', '/', $target), str_replace('\\', '/', $root) . '/') === 0) && is_file($target . '/index.php')) {
    return false;
}
require $root . '/index.php';
