<?php
declare(strict_types=1);
require dirname(__DIR__) . '/inc/settings.php';

session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS'])]);
session_name('clapet_admin');
session_start();
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$site  = dirname(__DIR__);
$root  = "$site/storage";
$admin = is_file("$root/admin.json") ? json_decode((string)file_get_contents("$root/admin.json"), true) : null;
$msg = $err = '';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid request.'); }
$action = $post ? (string)($_POST['action'] ?? '') : '';

/* ------------------------------------------------------------------ helpers */
function jread(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function jwrite(string $f, array $d): void { file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }
function ico(string $n): string {
    static $p = [
        'home' => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>', 'edit' => '<path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/>',
        'img' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-8 7"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>', 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'out' => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>', 'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>', 'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$n] ?? '') . '</svg>';
}
/** Keeps basic inline formatting only. */
function clean_html(string $s): string {
    $s = strip_tags($s, '<b><i><em><strong><span><br><a><small><sup>');
    return (string)preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function ($m) {
        $tag = strtolower($m[2]);
        if ($m[1] === '/') return "</$tag>";
        $attr = '';
        if ($tag === 'a' && preg_match('/href\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $m[3], $h)) {
            $u = trim($h[1] !== '' ? $h[1] : ($h[2] ?? ''));
            if (preg_match('~^(https?://|mailto:|tel:|#|/|[a-z0-9._-]+\.php)~i', $u)) $attr .= ' href="' . htmlspecialchars($u, ENT_QUOTES) . '"';
        }
        if (($tag === 'a' || $tag === 'span') && preg_match('/class\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $m[3], $c)) {
            $cls = preg_replace('/[^a-z0-9_ -]/i', '', $c[1] !== '' ? $c[1] : ($c[2] ?? ''));
            if (trim($cls) !== '') $attr .= ' class="' . trim($cls) . '"';
        }
        return "<$tag$attr>";
    }, $s);
}
$PAGES = ['global' => 'Menu & footer', 'index' => 'Home', 'about' => 'About Us', 'services' => 'Services overview',
    'property-management' => 'Property Management', 'construction-supervision' => 'Construction Supervision',
    'construction-consultancy' => 'Construction & Consultancy', 'land-verification' => 'Land Verification',
    'farm-management' => 'Farm Management', 'supplies-tendering' => 'Supplies & Tendering', 'projects' => 'Projects',
    'properties' => 'Land & Properties', 'partners' => 'Partners', 'resources' => 'Resources & FAQs', 'contact' => 'Contact',
    'portal' => 'Client Portal', 'legal' => 'Legal'];
$cmap = jread("$site/inc/content-map.json");
$statuses = ['New enquiry', 'Under review', 'Contacted', 'Assessment / quotation', 'Proposal sent', 'Won / contract signed', 'Closed / unsuccessful'];
$slots = site_image_slots();

/* ------------------------------------------------------------------ auth */
if ($action === 'login') {
    $rl = "$root/login_" . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.json';
    $fails = array_filter(jread($rl), fn($t) => $t > time() - 900);
    if (count($fails) >= 5) $err = 'Too many attempts. Try again in 15 minutes.';
    elseif ($admin && hash_equals($admin['user'], (string)$_POST['user']) && password_verify((string)$_POST['pass'], $admin['hash'])) {
        session_regenerate_id(true);
        $_SESSION['in'] = true;
        @unlink($rl);
        header('Location: index.php');
        exit;
    } else { $fails[] = time(); jwrite($rl, array_values($fails)); $err = 'Incorrect email or password.'; }
}
if ($action === 'logout') { session_destroy(); header('Location: index.php'); exit; }
$in = !empty($_SESSION['in']);

/* ------------------------------------------------------------------ actions */
if ($in && $action === 'save_settings') {
    $c = static fn(string $k, int $m = 200): string => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $m);
    $email = $c('email'); $phone = $c('phone', 40); $wa = preg_replace('/\D/', '', $c('wa', 30));
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
        jwrite("$root/settings.json", array_replace(jread("$root/settings.json"), ['phone' => $phone, 'email' => $email, 'address' => $c('address'), 'wa' => $wa, 'hours' => $c('hours'), 'md' => $c('md', 120), 'credentials' => $creds, 'ticker_extra' => $extra]));
        $msg = 'Contact details and credentials saved. The live site now uses them.';
    }
}
if ($in && $action === 'password') {
    if (!password_verify((string)$_POST['old'], $admin['hash'])) $err = 'Current password is incorrect.';
    elseif (strlen((string)$_POST['new']) < 10) $err = 'New password must be at least 10 characters.';
    else { $admin['hash'] = password_hash((string)$_POST['new'], PASSWORD_DEFAULT); jwrite("$root/admin.json", $admin); $msg = 'Password changed.'; }
}
if ($in && $action === 'status') {
    $ref = preg_replace('/[^A-Z0-9\-]/', '', (string)$_POST['ref']);
    $f = "$root/enquiries/$ref.json";
    if (is_file($f) && in_array($_POST['status'], $statuses, true)) { $r = jread($f); $r['status'] = $_POST['status']; jwrite($f, $r); $msg = "$ref updated."; }
}
if ($in && $action === 'content_save') {
    $pg = (string)($_POST['pg'] ?? '');
    if (!isset($cmap[$pg])) $err = 'Unknown page.';
    else {
        $ov = jread("$root/content.json");
        unset($ov[$pg]);
        $n = 0;
        foreach ($cmap[$pg] as $id => $blk) {
            $v = str_replace("\r\n", "\n", trim((string)($_POST['c'][$id] ?? '')));
            $d = trim((string)$blk['d']);
            if ($v === '' || $v === $d) continue;
            $v = $blk['t'] === 'Search engine' ? trim(strip_tags($v)) : clean_html($v);
            if ($v !== '' && $v !== $d) { $ov[$pg][$id] = $v; $n++; }
        }
        if (!isset($ov[$pg])) unset($ov[$pg]);
        jwrite("$root/content.json", $ov);
        $msg = $n ? "$n edited text block" . ($n > 1 ? 's' : '') . ' saved on ' . $PAGES[$pg] . '. Live now.' : 'No changes from the original text on ' . $PAGES[$pg] . '.';
    }
}
if ($in && $action === 'content_reset') {
    $pg = (string)($_POST['pg'] ?? '');
    $ov = jread("$root/content.json");
    if (isset($ov[$pg])) { unset($ov[$pg]); jwrite("$root/content.json", $ov); }
    $msg = 'Restored the original text on ' . ($PAGES[$pg] ?? 'that page') . '.';
}
if ($in && $action === 'image_save') {
    $slot = (string)($_POST['slot'] ?? '');
    if (!isset($slots[$slot])) $err = 'Unknown image slot.';
    else {
        $cfg = jread("$root/settings.json");
        $cur = $cfg['images'][$slot] ?? [];
        $entry = ['file' => $cur['file'] ?? null, 'alt' => mb_substr(trim(strip_tags((string)$_POST['alt'])), 0, 200), 'credit' => mb_substr(trim(strip_tags((string)$_POST['credit'])), 0, 200)];
        $up = $_FILES['photo'] ?? null;
        if ($up && $up['error'] === UPLOAD_ERR_OK) {
            $info = @getimagesize($up['tmp_name']);
            $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
            if (!$ext || $up['size'] > 6 * 1024 * 1024) $err = 'Please upload a JPG, PNG or WebP image up to 6 MB.';
            else {
                $dir = "$site/assets/img/uploads"; @mkdir($dir, 0755, true);
                $name = $slot . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($up['tmp_name'], "$dir/$name")) {
                    if (!empty($cur['file']) && str_starts_with($cur['file'], 'assets/img/uploads/')) @unlink("$site/" . $cur['file']);
                    $entry['file'] = 'assets/img/uploads/' . $name;
                }
            }
        }
        if (!$err) { $cfg['images'][$slot] = $entry; jwrite("$root/settings.json", $cfg); $msg = 'Image saved.'; }
    }
}
if ($in && $action === 'image_reset') {
    $slot = (string)($_POST['slot'] ?? '');
    $cfg = jread("$root/settings.json");
    if (isset($slots[$slot], $cfg['images'][$slot])) {
        $f = $cfg['images'][$slot]['file'] ?? '';
        if ($f && str_starts_with($f, 'assets/img/uploads/')) @unlink("$site/$f");
        unset($cfg['images'][$slot]); jwrite("$root/settings.json", $cfg); $msg = 'Restored the default photo.';
    }
}

$S = site_settings();
$page = (string)($_GET['p'] ?? 'dashboard');
if (!in_array($page, ['dashboard', 'pages', 'content', 'images', 'settings', 'enquiries', 'account'], true)) $page = 'dashboard';
$pg = (string)($_GET['pg'] ?? 'index');
if (!isset($cmap[$pg])) $pg = 'index';

function nav_item(string $p, string $label, string $icon, string $cur, ?int $badge = null): string {
    return '<a href="?p=' . $p . '"' . ($cur === $p ? ' aria-current="page"' : '') . '>' . ico($icon) . '<span>' . h($label) . '</span>' . ($badge ? '<em>' . $badge . '</em>' : '') . '</a>';
}
function page_cards(array $PAGES, array $cmap, array $ovAll): string {
    $o = '<div class="pgrid">';
    foreach ($PAGES as $k => $label) {
        if (!isset($cmap[$k])) continue;
        $n = count($cmap[$k]); $e = count($ovAll[$k] ?? []);
        $o .= '<a class="pcard" href="?p=content&amp;pg=' . h($k) . '"><span class="pi">' . ico($k === 'global' ? 'cog' : 'edit') . '</span><span class="pt"><b>' . h($label) . '</b><small>' . $n . ' text blocks' . ($e ? ' · <i>' . $e . ' edited</i>' : '') . '</small></span><span class="go">Edit →</span></a>';
    }
    return $o . '</div>';
}
$enq = [];
if ($in) foreach (glob("$root/enquiries/*.json") ?: [] as $f) { $r = jread($f); if ($r) $enq[] = $r; }
usort($enq, fn($a, $b) => strcmp($b['received'], $a['received']));
$newCount = count(array_filter($enq, fn($r) => ($r['status'] ?? '') === 'New enquiry'));
$ovAll = $in ? jread("$root/content.json") : [];
$editedCount = array_sum(array_map('count', $ovAll));
$titles = ['dashboard' => 'Dashboard', 'pages' => 'Website text', 'content' => 'Website text', 'images' => 'Photos', 'settings' => 'Contact & credentials', 'enquiries' => 'Enquiries', 'account' => 'Account'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Admin | Clapet Asset Management</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{--navy:#0A192F;--navy2:#112240;--navy3:#1c3358;--gold:#D4AF37;--goldd:#8a6d12;--bg:#F4F6FA;--card:#fff;--ink:#1b2a41;--muted:#5d6c82;--line:#e3e7ee;
--fh:'Montserrat',system-ui,sans-serif;--fb:'Open Sans',system-ui,sans-serif}
*{box-sizing:border-box}
body{margin:0;font:15px/1.6 var(--fb);background:var(--bg);color:var(--ink)}
h1,h2,h3{font-family:var(--fh);color:var(--navy);margin:0 0 .5em;line-height:1.25}
a{color:inherit}
.shell{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}
.side{background:linear-gradient(180deg,var(--navy),var(--navy2));color:#aebcd3;padding:22px 14px;position:sticky;top:0;height:100vh;overflow:auto;display:flex;flex-direction:column}
.logo{display:flex;gap:12px;align-items:center;padding:4px 10px 22px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:16px}
.logo i{width:38px;height:38px;border-radius:10px;background:var(--gold);color:var(--navy);display:grid;place-items:center;font:800 1rem var(--fh);font-style:normal}
.logo b{display:block;color:#fff;font:800 .82rem var(--fh);letter-spacing:.08em;line-height:1.2}
.logo small{color:var(--gold);font:600 .56rem var(--fh);letter-spacing:.22em}
.side nav{display:flex;flex-direction:column;gap:3px}
.side nav a{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:10px;text-decoration:none;font:600 .86rem var(--fb);color:#aebcd3}
.side nav a svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.side nav a:hover{background:rgba(255,255,255,.06);color:#fff}
.side nav a[aria-current=page]{background:rgba(212,175,55,.14);color:var(--gold)}
.side nav em{margin-left:auto;background:var(--gold);color:var(--navy);font:800 .65rem var(--fh);padding:2px 8px;border-radius:999px;font-style:normal}
.side .foot{margin-top:auto;padding-top:18px;display:flex;flex-direction:column;gap:6px}
.side .foot a,.side .foot button{display:flex;gap:12px;align-items:center;padding:10px 12px;border-radius:10px;font:600 .84rem var(--fb);color:#aebcd3;text-decoration:none;background:none;border:0;cursor:pointer;width:100%;text-align:left}
.side .foot svg{width:18px;height:18px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round}
.side .foot a:hover,.side .foot button:hover{background:rgba(255,255,255,.06);color:#fff}
.top{background:#fff;border-bottom:1px solid var(--line);padding:16px 32px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;position:sticky;top:0;z-index:5}
.top h1{margin:0;font-size:1.25rem}
.top .who{display:flex;align-items:center;gap:10px;font-size:.84rem;color:var(--muted)}
.btn{display:inline-flex;align-items:center;gap:8px;background:var(--navy3);color:#fff;border:0;padding:11px 22px;border-radius:999px;font:700 .8rem var(--fh);cursor:pointer;text-decoration:none}
.btn:hover{background:var(--gold);color:var(--navy)}
.btn.gold{background:var(--gold);color:var(--navy)}.btn.gold:hover{background:#e6c458}
.btn.ghost{background:#fff;color:var(--navy);border:1.5px solid var(--line)}.btn.ghost:hover{border-color:var(--gold);background:#fff}
.btn.sm{padding:8px 16px;font-size:.74rem}
.btn svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.work{padding:28px 32px 90px;max-width:1100px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:24px;margin-bottom:20px}
.card h2{font-size:1.05rem}
.ok{background:#effaf3;border:1px solid #9fd8b2;color:#1c5a33;padding:12px 16px;border-radius:10px;margin-bottom:18px}
.er{background:#fff1f1;border:1px solid #f0b4b4;color:#7a1f1f;padding:12px 16px;border-radius:10px;margin-bottom:18px}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}
.stat{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;gap:14px;align-items:center}
.stat i{width:44px;height:44px;border-radius:12px;background:var(--navy);display:grid;place-items:center;flex:none}
.stat i svg{width:22px;height:22px;stroke:var(--gold);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.stat b{display:block;font:800 1.5rem var(--fh);color:var(--navy);line-height:1.1}
.stat span{font-size:.76rem;color:var(--muted)}
.quick{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
.quick a{display:flex;gap:12px;align-items:center;padding:14px 16px;border:1px solid var(--line);border-radius:12px;text-decoration:none;font:700 .84rem var(--fh);color:var(--navy);background:#fff;transition:.2s}
.quick a:hover{border-color:var(--gold);box-shadow:0 8px 22px -14px rgba(10,25,47,.5);transform:translateY(-2px)}
.quick svg{width:20px;height:20px;stroke:var(--goldd);fill:none;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round;flex:none}
label{display:block;font:600 .8rem var(--fb);color:var(--ink);margin-bottom:14px}
label small{color:var(--muted);font-weight:400}
input[type=text],input[type=email],input[type=password],input[type=search],textarea,select{display:block;width:100%;margin-top:6px;padding:10px 13px;border:1px solid #cdd5e1;border-radius:9px;font:inherit;background:#fff;color:var(--ink)}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(212,175,55,.22)}
textarea{resize:vertical;line-height:1.5}
.g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 18px}
.cred{display:flex;gap:12px;align-items:center;margin-bottom:10px;flex-wrap:wrap}
.cred input[type=text]{margin:0;flex:1 1 220px;min-width:0}
.cred label{margin:0;white-space:nowrap;display:flex;gap:7px;align-items:center}
.cred input[type=checkbox]{accent-color:var(--gold);width:18px;height:18px}
.pagetabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px}
.pagetabs a{font:700 .74rem var(--fh);padding:8px 14px;border-radius:999px;background:#fff;border:1px solid var(--line);text-decoration:none;color:var(--navy)}
.pagetabs a:hover{border-color:var(--gold)}
.pagetabs a[aria-current=page]{background:var(--navy);color:#fff;border-color:var(--navy)}
.toolbar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.toolbar .search{flex:1 1 260px;position:relative}
.toolbar .search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:17px;height:17px;stroke:var(--muted);fill:none;stroke-width:2}
.toolbar .search input{margin:0;padding-left:38px}
.jump{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px}
.jump a{font-size:.72rem;padding:4px 10px;border-radius:999px;background:#e9eef7;color:var(--navy);text-decoration:none;font-weight:600}
.sec{margin-bottom:18px;scroll-margin-top:90px}
.sec>h3{font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:var(--goldd);margin:0 0 10px;padding-left:2px}
.blk{background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 14px 4px;margin-bottom:8px}
.blk.edited{border-left:4px solid var(--gold)}
.blk .meta{display:flex;gap:8px;align-items:center;margin-bottom:2px}
.blk .meta span{font:700 .6rem var(--fh);letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
.blk .meta b{font:800 .58rem var(--fh);letter-spacing:.1em;text-transform:uppercase;background:var(--gold);color:var(--navy);padding:2px 8px;border-radius:999px}
.blk label{margin:0 0 8px}
.blk textarea,.blk input{margin-top:2px}
.hint{font-size:.78rem;color:var(--muted);margin:0 0 14px}
.side details.grp summary{list-style:none;display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:10px;cursor:pointer;font:600 .86rem var(--fb);color:#aebcd3}
.side details.grp summary::-webkit-details-marker{display:none}
.side details.grp summary svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.side details.grp summary:hover{background:rgba(255,255,255,.06);color:#fff}
.side details.grp summary[aria-current=page]{background:rgba(212,175,55,.14);color:var(--gold)}
.side details.grp .chev{margin-left:auto;font-style:normal;font-size:.7rem;transition:.2s}
.side details.grp[open] .chev{transform:rotate(180deg)}
.side .sub{display:flex;flex-direction:column;gap:1px;margin:2px 0 8px 22px;padding-left:12px;border-left:1px solid rgba(255,255,255,.1)}
.side .sub a{display:flex;align-items:center;padding:7px 10px;border-radius:8px;font:600 .8rem var(--fb);color:#93a4c0;text-decoration:none}
.side .sub a:hover{color:#fff;background:rgba(255,255,255,.05)}
.side .sub a[aria-current=page]{color:var(--gold);background:rgba(212,175,55,.1)}
.side .sub a i{margin-left:auto;background:var(--gold);color:var(--navy);font:800 .58rem var(--fh);padding:1px 7px;border-radius:999px;font-style:normal}
.pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.pcard{display:flex;align-items:center;gap:14px;padding:14px 16px;background:#fff;border:1px solid var(--line);border-radius:12px;text-decoration:none;transition:.2s}
.pcard:hover{border-color:var(--gold);box-shadow:0 10px 24px -16px rgba(10,25,47,.5);transform:translateY(-2px)}
.pcard .pi{width:40px;height:40px;border-radius:10px;background:var(--navy);display:grid;place-items:center;flex:none}
.pcard .pi svg{width:19px;height:19px;stroke:var(--gold);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.pcard .pt{display:flex;flex-direction:column;min-width:0}
.pcard .pt b{font:700 .88rem var(--fh);color:var(--navy);line-height:1.25}
.pcard .pt small{font-size:.72rem;color:var(--muted)}.pcard .pt small i{font-style:normal;color:var(--goldd);font-weight:700}
.pcard .go{margin-left:auto;font:700 .72rem var(--fh);color:var(--goldd);white-space:nowrap}
.crumb{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;font:600 .84rem var(--fb)}
.crumb a{color:var(--goldd);text-decoration:none}.crumb span{color:var(--muted)}.crumb b{font-family:var(--fh);color:var(--navy)}
.crumb select{margin:0 0 0 auto;width:auto;min-width:220px;padding:8px 12px}
.savebar{position:fixed;left:248px;right:0;bottom:0;background:rgba(255,255,255,.96);border-top:1px solid var(--line);padding:12px 32px;display:flex;gap:12px;align-items:center;z-index:6;backdrop-filter:blur(8px)}
.savebar .dirty{font-size:.8rem;color:var(--goldd);font-weight:700;margin-left:auto;display:none}
.savebar.changed .dirty{display:block}
.slot{display:grid;grid-template-columns:200px minmax(0,1fr);gap:20px}
.slot img,.slot .ph{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:10px;background:var(--line);display:block}
.tbl{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:.86rem;min-width:600px}
th,td{padding:11px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}
th{font:700 .66rem var(--fh);letter-spacing:.1em;text-transform:uppercase;color:var(--muted)}
.chip{display:inline-block;font:700 .62rem var(--fh);letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border-radius:999px;background:#e9eef7;color:var(--navy)}
.chip.new{background:var(--gold)}
details summary{cursor:pointer;color:var(--goldd);font-weight:600;font-size:.8rem}
.loginwrap{min-height:100vh;display:grid;place-items:center;padding:24px;background:radial-gradient(900px 500px at 80% 0,rgba(212,175,55,.18),transparent 60%),var(--navy)}
.login{background:#fff;border-radius:20px;padding:36px;width:100%;max-width:410px;box-shadow:0 30px 70px -25px rgba(0,0,0,.6)}
.login .logo{border:0;padding:0 0 18px}.login .logo b{color:var(--navy)}
.login p.s{text-align:center;margin:20px 0 0}.login p.s a{color:var(--navy);font-weight:700}
@media (max-width:900px){
  .shell{grid-template-columns:1fr}.side{position:static;height:auto;flex-direction:column;padding:14px}
  .side nav{flex-direction:row;overflow-x:auto;gap:4px}.side nav a span{white-space:nowrap}.side .foot{flex-direction:row;padding-top:8px}
  .logo{padding-bottom:12px;margin-bottom:10px}
  .side details.grp .sub{display:none}
  .top,.work{padding-left:18px;padding-right:18px}.savebar{left:0;padding:12px 18px}
  .stats{grid-template-columns:repeat(2,minmax(0,1fr))}.g2,.slot{grid-template-columns:1fr}
}
</style></head><body>
<?php if (!$in): ?>
<div class="loginwrap"><div class="login">
<div class="logo"><i>C</i><div><b>CLAPET ADMIN</b><small>THE STANDARD OF TRUST.</small></div></div>
<h1 style="font-size:1.4rem">Sign in</h1>
<?php if ($err): ?><div class="er"><?= h($err) ?></div><?php endif; ?>
<?php if (!$admin): ?><div class="er">No admin account yet. Run <code>php admin/setup.php &lt;email&gt; &lt;password&gt;</code> on the server.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="login">
<label>Email<input type="text" name="user" required autocomplete="username"></label>
<label>Password<input type="password" name="pass" required autocomplete="current-password"></label>
<button class="btn gold" style="width:100%;justify-content:center">Sign in</button></form>
<p class="s"><a href="../index.php">← View the website</a></p></div></div>
<?php else: ?>
<div class="shell">
<aside class="side">
<div class="logo"><i>C</i><div><b>CLAPET ADMIN</b><small>THE STANDARD OF TRUST.</small></div></div>
<nav aria-label="Admin">
<?= nav_item('dashboard', 'Dashboard', 'home', $page) ?>
<details class="grp"<?= in_array($page, ['pages', 'content'], true) ? ' open' : '' ?>><summary<?= $page === 'pages' ? ' aria-current="page"' : '' ?>><?= ico('edit') ?><span>Website text</span><em class="chev">▾</em></summary>
<div class="sub"><a href="?p=pages"<?= $page === 'pages' ? ' aria-current="page"' : '' ?>>All pages</a>
<?php foreach ($PAGES as $k => $lbl): if (!isset($cmap[$k])) continue; ?><a href="?p=content&amp;pg=<?= h($k) ?>"<?= ($page === 'content' && $pg === $k) ? ' aria-current="page"' : '' ?>><?= h($lbl) ?><?= !empty($ovAll[$k]) ? '<i>' . count($ovAll[$k]) . '</i>' : '' ?></a><?php endforeach; ?></div></details>
<?= nav_item('images', 'Photos', 'img', $page) ?>
<?= nav_item('settings', 'Contact & credentials', 'cog', $page) ?>
<?= nav_item('enquiries', 'Enquiries', 'mail', $page, $newCount) ?>
<?= nav_item('account', 'Account', 'user', $page) ?>
</nav>
<div class="foot"><a href="../index.php" target="_blank"><?= ico('eye') ?>View website</a>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button><?= ico('out') ?>Sign out</button></form></div>
</aside>
<div>
<div class="top"><h1><?= h($titles[$page]) ?></h1><div class="who"><span><?= h($admin['user'] ?? '') ?></span><a class="btn sm ghost" href="../index.php" target="_blank"><?= ico('eye') ?>View site</a></div></div>
<div class="work">
<?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="er"><?= h($err) ?></div><?php endif; ?>

<?php if ($page === 'dashboard'): ?>
<div class="stats">
<div class="stat"><i><?= ico('mail') ?></i><div><b><?= $newCount ?></b><span>New enquiries</span></div></div>
<div class="stat"><i><?= ico('edit') ?></i><div><b><?= $editedCount ?></b><span>Edited text blocks</span></div></div>
<div class="stat"><i><?= ico('img') ?></i><div><b><?= count(array_filter((array)(jread("$root/settings.json")['images'] ?? []), fn($i) => !empty($i['file']))) ?></b><span>Custom photos</span></div></div>
<div class="stat"><i><?= ico('cog') ?></i><div><b><?= count(array_filter($S['credentials'], fn($c) => !empty($c['verified']))) ?></b><span>Verified credentials</span></div></div>
</div>
<div class="card"><h2>Quick actions</h2><div class="quick">
<a href="?p=content&pg=index"><?= ico('edit') ?>Edit home page</a>
<a href="?p=content&pg=global"><?= ico('edit') ?>Edit menu &amp; footer</a>
<a href="?p=images"><?= ico('img') ?>Replace photos</a>
<a href="?p=settings"><?= ico('cog') ?>Update phone &amp; email</a>
<a href="?p=settings#credentials"><?= ico('bolt') ?>Verify credentials</a>
<a href="?p=content&pg=services"><?= ico('edit') ?>Edit services</a>
<a href="?p=content&pg=contact"><?= ico('edit') ?>Edit contact page</a>
<a href="?p=enquiries"><?= ico('mail') ?>View enquiries</a>
</div></div>
<div class="card"><h2>Edit website pages</h2><p class="hint">Each page has its own text editor. Pick a page to change any heading, paragraph, button or menu text.</p><?= page_cards($PAGES, $cmap, $ovAll) ?></div>
<div class="card"><h2>Latest enquiries</h2>
<?php if (!$enq): ?><p style="color:var(--muted);margin:0">No enquiries yet. Submissions from the brief form appear here.</p>
<?php else: ?><div class="tbl"><table><tr><th>Reference</th><th>Service</th><th>From</th><th>Status</th></tr>
<?php foreach (array_slice($enq, 0, 5) as $r): ?><tr><td><b><?= h($r['reference']) ?></b></td><td><?= h($r['service']) ?></td><td><?= h($r['data']['full_name'] ?? '') ?></td><td><span class="chip<?= $r['status'] === 'New enquiry' ? ' new' : '' ?>"><?= h($r['status']) ?></span></td></tr><?php endforeach; ?>
</table></div><?php endif; ?></div>

<?php elseif ($page === 'pages'): ?>
<p class="hint" style="margin-top:0">Choose a page to edit. Every heading, paragraph, button, menu item and search-engine description on the public website can be changed here.</p>
<?= page_cards($PAGES, $cmap, $ovAll) ?>

<?php elseif ($page === 'content'):
    $blocks = $cmap[$pg]; uasort($blocks, fn($a, $b) => $a['n'] <=> $b['n']);
    $ov = $ovAll[$pg] ?? [];
    $groups = []; foreach ($blocks as $id => $b) $groups[$b['s']][$id] = $b; ?>
<div class="crumb"><a href="?p=pages">All pages</a><span>/</span><b><?= h($PAGES[$pg] ?? $pg) ?></b>
<select aria-label="Switch page" onchange="location='?p=content&pg='+this.value"><?php foreach ($PAGES as $k => $lbl): if (!isset($cmap[$k])) continue; ?><option value="<?= h($k) ?>"<?= $k === $pg ? ' selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></div>
<form method="post" id="cform">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="content_save"><input type="hidden" name="pg" value="<?= h($pg) ?>">
<div class="toolbar"><div class="search"><?= ico('search') ?><input type="search" id="q" placeholder="Quick find text on this page…" aria-label="Quick find"></div>
<a class="btn sm ghost" href="../<?= $pg === 'global' ? 'index' : h($pg) ?>.php" target="_blank"><?= ico('eye') ?>View page</a></div>
<p class="hint">Edit any text below, then Save. Clear a field to restore the original. You can use <code>&lt;b&gt;</code>, <code>&lt;i&gt;</code>, <code>&lt;br&gt;</code> and <code>&lt;span class="gold"&gt;</code>. Contact details and credentials live under “Contact &amp; credentials”.</p>
<div class="jump"><?php $i = 0; foreach ($groups as $gt => $_): ?><a href="#s<?= $i++ ?>"><?= h($gt) ?></a><?php endforeach; ?></div>
<?php $i = 0; foreach ($groups as $gt => $items): ?>
<div class="sec" id="s<?= $i++ ?>"><h3><?= h($gt) ?></h3>
<?php foreach ($items as $id => $b): $val = $ov[$id] ?? $b['d']; $long = strlen($b['d']) > 80 || str_contains($b['d'], '<br'); ?>
<div class="blk<?= isset($ov[$id]) ? ' edited' : '' ?>" data-t="<?= h(mb_strtolower($b['l'])) ?>"><div class="meta"><span><?= h($b['t']) ?></span><?= isset($ov[$id]) ? '<b>Edited</b>' : '' ?></div>
<label class="sr"><?php if ($long): ?><textarea name="c[<?= h($id) ?>]" rows="<?= max(2, min(7, (int)ceil(strlen($b['d']) / 95))) ?>"><?= h($val) ?></textarea><?php else: ?><input type="text" name="c[<?= h($id) ?>]" value="<?= h($val) ?>"><?php endif; ?></label></div>
<?php endforeach; ?></div>
<?php endforeach; ?>
<div class="savebar" id="sbar"><button class="btn gold" type="submit">Save changes</button>
<button class="btn ghost" type="submit" name="action" value="content_reset" onclick="return confirm('Restore the original text on this whole page?')">Restore page to original</button><span class="dirty">● Unsaved changes</span></div>
</form>
<script>
(function(){var q=document.getElementById('q'),f=document.getElementById('cform'),bar=document.getElementById('sbar');
q.addEventListener('input',function(){var v=q.value.toLowerCase();document.querySelectorAll('.blk').forEach(function(b){b.style.display=(!v||b.getAttribute('data-t').indexOf(v)>-1||b.textContent.toLowerCase().indexOf(v)>-1||(b.querySelector('textarea,input')||{value:''}).value.toLowerCase().indexOf(v)>-1)?'':'none'});
document.querySelectorAll('.sec').forEach(function(s){s.style.display=s.querySelector('.blk:not([style*="none"])')?'':'none'})});
f.addEventListener('input',function(e){if(e.target!==q)bar.classList.add('changed')});})();
</script>

<?php elseif ($page === 'images'): ?>
<div class="card"><p style="margin:0;color:var(--muted)">Replace any website photo with your own (JPG, PNG or WebP, up to 6 MB). Clear the credit when you upload your own photography. Default photos are Creative Commons images from Wikimedia Commons.</p></div>
<?php foreach ($slots as $slot => $label): $im = slot_image($slot); ?>
<form method="post" enctype="multipart/form-data" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="image_save"><input type="hidden" name="slot" value="<?= h($slot) ?>">
<h2><?= h($label) ?></h2><div class="slot"><div><?php if ($im): ?><img src="../<?= h($im['file']) ?>" alt=""><?php else: ?><div class="ph"></div><?php endif; ?></div>
<div><label>Upload a new photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
<label>Description (alt text)<input type="text" name="alt" value="<?= h($im['alt'] ?? '') ?>"></label>
<label>Photo credit (shown on image)<input type="text" name="credit" value="<?= h($im['credit'] ?? '') ?>"></label>
<button class="btn sm">Save</button> <button class="btn sm ghost" name="action" value="image_reset" formnovalidate>Restore default</button></div></div></form>
<?php endforeach; ?>

<?php elseif ($page === 'settings'): ?>
<form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save_settings">
<h2>Contact details</h2><div class="g2">
<label>Telephone<input type="text" name="phone" value="<?= h($S['phone']) ?>" required></label>
<label>Email <small>(also receives enquiry notifications)</small><input type="email" name="email" value="<?= h($S['email']) ?>" required></label>
<label>WhatsApp number <small>(with country code)</small><input type="text" name="wa" value="<?= h($S['wa']) ?>" required></label>
<label>Office address<input type="text" name="address" value="<?= h($S['address']) ?>"></label>
<label>Business hours<input type="text" name="hours" value="<?= h($S['hours']) ?>"></label>
<label>Managing Director name<input type="text" name="md" value="<?= h($S['md']) ?>"></label></div>
<h2 id="credentials" style="margin-top:10px">Credentials ticker</h2>
<p class="hint">Tick a credential only when it is verified and certificates are available. Unticked items are never shown on the website.</p>
<?php foreach ($S['credentials'] as $i => $c): ?><div class="cred"><input type="text" name="cred_label[<?= $i ?>]" value="<?= h($c['label']) ?>"><label><input type="checkbox" name="cred_ok[<?= $i ?>]" <?= !empty($c['verified']) ? 'checked' : '' ?>> Verified</label></div><?php endforeach; ?>
<div class="cred"><input type="text" name="cred_label[90]" placeholder="Add another credential (optional)"><label><input type="checkbox" name="cred_ok[90]"> Verified</label></div>
<label>Always-shown ticker lines <small>(one per line)</small><textarea name="ticker_extra" rows="3"><?= h(implode("\n", $S['ticker_extra'])) ?></textarea></label>
<button class="btn gold">Save details</button></form>

<?php elseif ($page === 'enquiries'): ?>
<div class="card"><h2>All enquiries (<?= count($enq) ?>)</h2>
<?php if (!$enq): ?><p style="color:var(--muted);margin:0">No enquiries yet.</p><?php else: ?><div class="tbl"><table><tr><th>Reference</th><th>Service</th><th>From</th><th>Status</th></tr>
<?php foreach ($enq as $r): $d = $r['data']; ?><tr><td><b><?= h($r['reference']) ?></b><br><small style="color:var(--muted)"><?= h(substr($r['received'], 0, 16)) ?></small></td><td><?= h($r['service']) ?></td>
<td><?= h($d['full_name'] ?? '') ?><br><a href="mailto:<?= h($d['email'] ?? '') ?>"><?= h($d['email'] ?? '') ?></a><br><?= h($d['phone'] ?? '') ?>
<details><summary>Details</summary><?php foreach ($d as $k => $v): if (in_array($k, ['full_name', 'email', 'phone'], true)) continue; ?><div><b><?= h(str_replace('_', ' ', $k)) ?>:</b> <?= h($v) ?></div><?php endforeach; ?>
<?php if ($r['files']): ?><div><b>Files:</b> <?= count($r['files']) ?> stored privately in storage/uploads/<?= h($r['reference']) ?></div><?php endif; ?></details></td>
<td><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="ref" value="<?= h($r['reference']) ?>">
<select name="status" onchange="this.form.submit()"><?php foreach ($statuses as $s): ?><option <?= $s === $r['status'] ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></form></td></tr><?php endforeach; ?>
</table></div><?php endif; ?></div>

<?php else: ?>
<form method="post" class="card" style="max-width:520px"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="password"><h2>Change password</h2>
<label>Current password<input type="password" name="old" required autocomplete="current-password"></label>
<label>New password <small>(10+ characters)</small><input type="password" name="new" required minlength="10" autocomplete="new-password"></label><button class="btn gold">Update password</button></form>
<?php endif; ?>
</div></div></div>
<?php endif; ?>
</body></html>
