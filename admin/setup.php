<?php
/** CLI only:  php admin/setup.php <username> <password>  — creates/resets the admin login. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 3 || strlen($argv[2]) < 10) { fwrite(STDERR, "Usage: php admin/setup.php <username> <password (10+ chars)>\n"); exit(1); }
$f = dirname(__DIR__) . '/storage/admin.json';
file_put_contents($f, json_encode(['user' => $argv[1], 'hash' => password_hash($argv[2], PASSWORD_DEFAULT)]));
echo "Admin account saved.\n";
