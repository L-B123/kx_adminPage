<?php
if (PHP_SAPI !== 'cli') { exit(1); }
foreach (array('pdo_sqlite', 'sqlite3', 'gd', 'mbstring', 'openssl', 'curl', 'fileinfo', 'zip') as $extension) {
    if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); }
}
if (version_compare(PHP_VERSION, '8.3', '<')) { fwrite(STDERR, "PHP 8.3+ required\n"); exit(1); }
echo 'PHP ' . PHP_VERSION . " extensions OK\n";
