<?php
/**
 * Creates the admin account.
 *
 * CLI:      php admin/setup.php <email> <password>      (also resets an existing login)
 * Browser:  for hosts without SSH. Upload a file named storage/setup.key containing a long random
 *           string, open /admin/setup.php, enter that key plus the new email and password.
 *           Only works while no admin account exists; the key file is deleted afterwards.
 */
declare(strict_types=1);
$root = dirname(__DIR__) . '/storage';
$file = "$root/admin.json";

if (PHP_SAPI === 'cli') {
    if ($argc < 3 || strlen($argv[2]) < 10) { fwrite(STDERR, "Usage: php admin/setup.php <email> <password (10+ chars)>\n"); exit(1); }
    file_put_contents($file, json_encode(['user' => $argv[1], 'hash' => password_hash($argv[2], PASSWORD_DEFAULT)]));
    echo "Admin account saved.\n";
    exit;
}

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
$keyFile = "$root/setup.key";
if (is_file($file) || !is_file($keyFile)) { http_response_code(404); exit; }   // invisible unless setup is armed

$msg = '';
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = trim((string)file_get_contents($keyFile));
    $email = trim((string)($_POST['email'] ?? ''));
    $pw = (string)($_POST['pw'] ?? '');
    usleep(400000);   // slow down guessing
    if (strlen($key) < 16 || !hash_equals($key, trim((string)($_POST['key'] ?? '')))) $msg = 'The setup key is not correct.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $msg = 'Enter a valid email address.';
    elseif (strlen($pw) < 10) $msg = 'Use a password of at least 10 characters.';
    else {
        file_put_contents($file, json_encode(['user' => $email, 'hash' => password_hash($pw, PASSWORD_DEFAULT)]));
        @unlink($keyFile);
        $done = true;
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin setup</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0A192F;font:15px/1.6 system-ui,sans-serif;color:#1b2a41}
.c{background:#fff;border-radius:18px;padding:34px;width:min(420px,92vw)}h1{margin:0 0 6px;font-size:1.4rem;color:#0A192F}p{color:#5d6c82;margin:0 0 18px}
label{display:block;font-weight:600;font-size:.82rem;margin-bottom:14px}input{display:block;width:100%;margin-top:6px;padding:11px 13px;border:1px solid #cdd5e1;border-radius:9px;font:inherit;box-sizing:border-box}
button,a.b{display:block;text-align:center;width:100%;background:#D4AF37;color:#0A192F;border:0;border-radius:999px;padding:13px;font-weight:700;cursor:pointer;text-decoration:none;box-sizing:border-box}
.e{background:#fff1f1;border:1px solid #f0b4b4;color:#7a1f1f;padding:10px 14px;border-radius:10px;margin-bottom:14px}</style></head><body><div class="c">
<?php if ($done): ?><h1>Admin account created</h1><p>The setup key has been deleted. You can now sign in.</p><a class="b" href="index.php">Go to sign in</a>
<?php else: ?><h1>Create the admin account</h1><p>One-time setup for this website.</p>
<?php if ($msg): ?><div class="e"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<form method="post" autocomplete="off"><label>Setup key<input name="key" required></label><label>Admin email<input type="email" name="email" required></label>
<label>Password (10+ characters)<input type="password" name="pw" required minlength="10"></label><button>Create account</button></form><?php endif; ?></div></body></html>
