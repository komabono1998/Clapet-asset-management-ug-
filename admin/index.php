<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/settings.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_name('clapet_admin');
session_start();
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$root  = dirname(__DIR__) . '/storage';
$admin = is_file("$root/admin.json") ? json_decode((string)file_get_contents("$root/admin.json"), true) : null;
$msg = $err = '';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid request.'); }
$action = $post ? (string)($_POST['action'] ?? '') : '';

// ---- login / logout
if ($action === 'login') {
    $rl = "$root/login_" . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
    $fails = is_file($rl) ? array_filter((array)json_decode((string)file_get_contents($rl), true), fn($t) => $t > time() - 900) : [];
    if (count($fails) >= 5) {
        $err = 'Too many attempts. Try again in 15 minutes.';
    } elseif ($admin && hash_equals($admin['user'], (string)$_POST['user']) && password_verify((string)$_POST['pass'], $admin['hash'])) {
        session_regenerate_id(true);
        $_SESSION['in'] = true;
        @unlink($rl);
        header('Location: index.php');
        exit;
    } else {
        $fails[] = time();
        file_put_contents($rl, json_encode(array_values($fails)));
        $err = 'Incorrect username or password.';
    }
}
if ($action === 'logout') { session_destroy(); header('Location: index.php'); exit; }
$in = !empty($_SESSION['in']);

// ---- authenticated actions
if ($in && $action === 'save_settings') {
    $c = static fn(string $k, int $m = 200): string => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $m);
    $email = $c('email');
    $phone = $c('phone', 40);
    $wa = preg_replace('/\D/', '', $c('wa', 30));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Please enter a valid email address.';
    elseif (!preg_match('/^[0-9+()\-\s]{7,40}$/', $phone)) $err = 'Please enter a valid telephone number.';
    elseif (strlen($wa) < 9) $err = 'WhatsApp number must include the country code.';
    else {
        $creds = [];
        foreach ((array)($_POST['cred_label'] ?? []) as $i => $label) {
            $label = mb_substr(trim(strip_tags((string)$label)), 0, 80);
            if ($label !== '') $creds[] = ['label' => $label, 'verified' => isset($_POST['cred_ok'][$i])];
        }
        $extra = array_values(array_filter(array_map(fn($l) => mb_substr(trim(strip_tags($l)), 0, 80), explode("\n", (string)($_POST['ticker_extra'] ?? '')))));
        $raw = is_file("$root/settings.json") ? (json_decode((string)file_get_contents("$root/settings.json"), true) ?: []) : [];
        $new = array_replace($raw, ['phone' => $phone, 'email' => $email, 'address' => $c('address'), 'wa' => $wa, 'hours' => $c('hours'), 'md' => $c('md', 120), 'credentials' => $creds, 'ticker_extra' => $extra]);
        file_put_contents("$root/settings.json", json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = 'Settings saved. The live site now uses them.';
    }
}
if ($in && $action === 'password') {
    if (!password_verify((string)$_POST['old'], $admin['hash'])) $err = 'Current password is incorrect.';
    elseif (strlen((string)$_POST['new']) < 10) $err = 'New password must be at least 10 characters.';
    else {
        $admin['hash'] = password_hash((string)$_POST['new'], PASSWORD_DEFAULT);
        file_put_contents("$root/admin.json", json_encode($admin));
        $msg = 'Password changed.';
    }
}
$statuses = ['New enquiry', 'Under review', 'Contacted', 'Assessment / quotation', 'Proposal sent', 'Won / contract signed', 'Closed / unsuccessful'];
if ($in && $action === 'status') {
    $ref = preg_replace('/[^A-Z0-9\-]/', '', (string)$_POST['ref']);
    $f = "$root/enquiries/$ref.json";
    if (is_file($f) && in_array($_POST['status'], $statuses, true)) {
        $r = json_decode((string)file_get_contents($f), true);
        $r['status'] = $_POST['status'];
        file_put_contents($f, json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = "$ref updated.";
    }
}

$slots = site_image_slots();
$rawSettings = static fn() => is_file("$root/settings.json") ? (json_decode((string)file_get_contents("$root/settings.json"), true) ?: []) : [];
if ($in && $action === 'image_save') {
    $slot = (string)($_POST['slot'] ?? '');
    if (!isset($slots[$slot])) { $err = 'Unknown image slot.'; }
    else {
        $cfg = $rawSettings();
        $cur = $cfg['images'][$slot] ?? [];
        $entry = ['file' => $cur['file'] ?? null, 'alt' => mb_substr(trim(strip_tags((string)$_POST['alt'])), 0, 200), 'credit' => mb_substr(trim(strip_tags((string)$_POST['credit'])), 0, 200)];
        $up = $_FILES['photo'] ?? null;
        if ($up && $up['error'] === UPLOAD_ERR_OK) {
            $info = @getimagesize($up['tmp_name']);
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
            if (!$ext || $up['size'] > 6 * 1024 * 1024) { $err = 'Please upload a JPG, PNG or WebP image up to 6 MB.'; }
            else {
                $dir = dirname(__DIR__) . '/assets/img/uploads';
                @mkdir($dir, 0755, true);
                $name = $slot . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($up['tmp_name'], "$dir/$name")) {
                    if (!empty($cur['file']) && str_starts_with($cur['file'], 'assets/img/uploads/')) @unlink(dirname(__DIR__) . '/' . $cur['file']);
                    $entry['file'] = 'assets/img/uploads/' . $name;
                }
            }
        }
        if (!$err) { $cfg['images'][$slot] = $entry; file_put_contents("$root/settings.json", json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); $msg = 'Image saved.'; }
    }
}
if ($in && $action === 'image_reset') {
    $slot = (string)($_POST['slot'] ?? '');
    $cfg = $rawSettings();
    if (isset($slots[$slot], $cfg['images'][$slot])) {
        $f = $cfg['images'][$slot]['file'] ?? '';
        if ($f && str_starts_with($f, 'assets/img/uploads/')) @unlink(dirname(__DIR__) . '/' . $f);
        unset($cfg['images'][$slot]);
        file_put_contents("$root/settings.json", json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $msg = 'Restored the default photo.';
    }
}

$S = site_settings();
$page = $_GET['p'] ?? 'settings';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Admin | Clapet Asset Management</title>
<style>
:root{--navy:#0A192F;--gold:#D4AF37;--line:#e3e7ee}*{box-sizing:border-box}
body{margin:0;font:15px/1.6 'Open Sans',system-ui,sans-serif;background:#F8F9FA;color:#1b2a41}
header{background:var(--navy);color:#fff;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
header b{letter-spacing:.08em}header b small{color:var(--gold);margin-left:10px;font-size:.7rem;letter-spacing:.2em}
nav a{color:#c9d3e3;text-decoration:none;margin-left:18px;font-weight:600}nav a.on{color:var(--gold)}
main{max-width:900px;margin:30px auto;padding:0 20px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:28px;margin-bottom:24px}
h1,h2{color:var(--navy);margin-top:0}label{display:block;font-weight:600;font-size:.85rem;margin-bottom:14px}
input[type=text],input[type=email],input[type=password],textarea,select{display:block;width:100%;margin-top:6px;padding:11px 13px;border:1px solid #cdd5e1;border-radius:9px;font:inherit}
button{background:var(--navy);color:#fff;border:0;padding:12px 24px;border-radius:999px;font-weight:700;cursor:pointer}button:hover{background:#1c3358}
.ok{background:#effaf3;border:1px solid #9fd8b2;color:#1c5a33;padding:12px 16px;border-radius:10px;margin-bottom:18px}
.er{background:#fff1f1;border:1px solid #f0b4b4;color:#7a1f1f;padding:12px 16px;border-radius:10px;margin-bottom:18px}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:0 18px}.cred{display:flex;gap:12px;align-items:center;margin-bottom:10px}.cred input[type=text]{margin:0;flex:1}.cred label{margin:0;white-space:nowrap}
table{width:100%;border-collapse:collapse;font-size:.88rem}td,th{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
small.h{color:#5d6c82;font-weight:400}.login{max-width:380px;margin:90px auto}
@media(max-width:640px){.g2{grid-template-columns:1fr}}
</style></head><body>
<?php if (!$in): ?>
<main class="login"><div class="card"><h1>Admin sign in</h1>
<?php if ($err): ?><div class="er"><?= h($err) ?></div><?php endif; ?>
<?php if (!$admin): ?><div class="er">No admin account yet. Run <code>php admin/setup.php &lt;username&gt; &lt;password&gt;</code> on the server.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="login">
<label>Email<input type="text" name="user" required autocomplete="username"></label>
<label>Password<input type="password" name="pass" required autocomplete="current-password"></label><button>Sign in</button></form><p style="margin:22px 0 0;text-align:center"><a href="../index.php" style="color:#0A192F;font-weight:700">← View the website</a></p></div></main>
<?php else: ?>
<header><b>CLAPET ADMIN<small>THE STANDARD OF TRUST.</small></b><nav>
<a class="<?= $page === 'settings' ? 'on' : '' ?>" href="?p=settings">Site settings</a>
<a class="<?= $page === 'images' ? 'on' : '' ?>" href="?p=images">Images</a>
<a class="<?= $page === 'enquiries' ? 'on' : '' ?>" href="?p=enquiries">Enquiries</a>
<a class="<?= $page === 'account' ? 'on' : '' ?>" href="?p=account">Account</a>
<a href="../index.php" target="_blank">View site ↗</a>
<form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button style="background:none;padding:0;margin-left:18px;color:#c9d3e3">Sign out</button></form></nav></header>
<main>
<?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="er"><?= h($err) ?></div><?php endif; ?>

<?php if ($page === 'settings'): ?>
<form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save_settings">
<h2>Contact details</h2><div class="g2">
<label>Telephone<input type="text" name="phone" value="<?= h($S['phone']) ?>" required></label>
<label>Email (also receives enquiry notifications)<input type="email" name="email" value="<?= h($S['email']) ?>" required></label>
<label>WhatsApp number <small class="h">(with country code)</small><input type="text" name="wa" value="<?= h($S['wa']) ?>" required></label>
<label>Office address<input type="text" name="address" value="<?= h($S['address']) ?>"></label>
<label>Business hours<input type="text" name="hours" value="<?= h($S['hours']) ?>"></label>
<label>Managing Director name<input type="text" name="md" value="<?= h($S['md']) ?>"></label></div>
<h2 style="margin-top:12px">Credentials ticker</h2>
<p><small class="h">Tick a credential only when it is verified and certificates are available. Unticked items are never shown on the website.</small></p>
<?php foreach ($S['credentials'] as $i => $c): ?>
<div class="cred"><input type="text" name="cred_label[<?= $i ?>]" value="<?= h($c['label']) ?>"><label><input type="checkbox" name="cred_ok[<?= $i ?>]" <?= !empty($c['verified']) ? 'checked' : '' ?>> Verified</label></div>
<?php endforeach; ?>
<div class="cred"><input type="text" name="cred_label[90]" placeholder="Add another credential (optional)"><label><input type="checkbox" name="cred_ok[90]"> Verified</label></div>
<label>Always-shown ticker lines <small class="h">(one per line)</small><textarea name="ticker_extra" rows="3"><?= h(implode("\n", $S['ticker_extra'])) ?></textarea></label>
<button>Save settings</button></form>

<?php elseif ($page === 'images'): ?>
<div class="card"><h2>Website images</h2><p><small class="h">Replace any photo with your own (JPG, PNG or WebP, max 6 MB). Photo credits are shown on the image — clear the credit when you upload your own photography. The default photos are Creative Commons images from Wikimedia Commons.</small></p></div>
<?php foreach ($slots as $slot => $label): $im = slot_image($slot); ?>
<form method="post" enctype="multipart/form-data" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="image_save"><input type="hidden" name="slot" value="<?= h($slot) ?>">
<h2 style="font-size:1.05rem"><?= h($label) ?></h2><div style="display:grid;grid-template-columns:220px 1fr;gap:20px">
<div><?php if ($im): ?><img src="../<?= h($im['file']) ?>" alt="" style="width:100%;border-radius:10px;aspect-ratio:4/3;object-fit:cover"><?php else: ?><div style="aspect-ratio:4/3;background:#e3e7ee;border-radius:10px;display:grid;place-items:center;color:#5d6c82">Illustration</div><?php endif; ?></div>
<div><label>Upload a new photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
<label>Description (alt text)<input type="text" name="alt" value="<?= h($im['alt'] ?? '') ?>"></label>
<label>Photo credit (shown on image)<input type="text" name="credit" value="<?= h($im['credit'] ?? '') ?>"></label>
<button>Save</button> <button name="action" value="image_reset" style="background:#5d6c82" formnovalidate>Restore default</button></div></div></form>
<?php endforeach; ?>

<?php elseif ($page === 'enquiries'):
    $rows = [];
    foreach (glob("$root/enquiries/*.json") ?: [] as $f) { $r = json_decode((string)file_get_contents($f), true); if ($r) $rows[] = $r; }
    usort($rows, fn($a, $b) => strcmp($b['received'], $a['received'])); ?>
<div class="card"><h2>Enquiries (<?= count($rows) ?>)</h2>
<?php if (!$rows): ?><p>No enquiries yet.</p><?php else: ?>
<table><tr><th>Reference</th><th>Service</th><th>From</th><th>Status</th></tr>
<?php foreach ($rows as $r): $d = $r['data']; ?>
<tr><td><b><?= h($r['reference']) ?></b><br><small class="h"><?= h(substr($r['received'], 0, 16)) ?></small></td><td><?= h($r['service']) ?></td>
<td><?= h($d['full_name'] ?? '') ?><br><a href="mailto:<?= h($d['email'] ?? '') ?>"><?= h($d['email'] ?? '') ?></a><br><?= h($d['phone'] ?? '') ?>
<details><summary>Details</summary>
<?php foreach ($d as $k => $v): if (in_array($k, ['full_name', 'email', 'phone'], true)) continue; ?><div><b><?= h(str_replace('_', ' ', $k)) ?>:</b> <?= h($v) ?></div><?php endforeach; ?>
<?php if ($r['files']): ?><div><b>Files:</b> <?= count($r['files']) ?> stored privately in storage/uploads/<?= h($r['reference']) ?></div><?php endif; ?></details></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="ref" value="<?= h($r['reference']) ?>">
<select name="status" onchange="this.form.submit()"><?php foreach ($statuses as $s): ?><option <?= $s === $r['status'] ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></form></td></tr>
<?php endforeach; ?></table><?php endif; ?></div>

<?php else: ?>
<form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="password"><h2>Change password</h2>
<label>Current password<input type="password" name="old" required autocomplete="current-password"></label>
<label>New password (10+ characters)<input type="password" name="new" required minlength="10" autocomplete="new-password"></label><button>Update password</button></form>
<?php endif; ?>
</main><?php endif; ?></body></html>
