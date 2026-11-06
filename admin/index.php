<?php
declare(strict_types=1);
define('CLAPET_ADMIN', true);
require dirname(__DIR__) . '/inc/settings.php';
require dirname(__DIR__) . '/inc/analytics.php';
require dirname(__DIR__) . '/inc/export.php';
require __DIR__ . '/lib.php';

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

$PAGES = ['global' => 'Menu & footer', 'index' => 'Home', 'about' => 'About Us', 'services' => 'Services overview',
    'property-management' => 'Property Management', 'construction-supervision' => 'Construction Supervision',
    'construction-consultancy' => 'Construction & Consultancy', 'land-verification' => 'Land Verification',
    'farm-management' => 'Farm Management', 'supplies-tendering' => 'Supplies & Tendering', 'projects' => 'Projects',
    'properties' => 'Land & Properties', 'partners' => 'Partners', 'resources' => 'Resources & FAQs', 'contact' => 'Contact',
    'portal' => 'Client Portal', 'legal' => 'Legal'];
$cmap     = jread("$site/inc/content-map.json");
$statuses = ['New enquiry', 'Under review', 'Contacted', 'Assessment / quotation', 'Proposal sent', 'Won / contract signed', 'Closed / unsuccessful'];
$slots    = site_image_slots();
$schemas  = coll_schemas();

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
        jwrite("$root/settings.json", array_replace(jread("$root/settings.json"), ['phone' => $phone, 'email' => $email, 'address' => $c('address'), 'wa' => $wa, 'hours' => $c('hours'), 'md' => $c('md', 120), 'notify' => isset($_POST['notify']), 'credentials' => $creds, 'ticker_extra' => $extra]));
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
        $new = save_upload($_FILES['photo'] ?? [], $slot, $site, $err);
        if ($new) {
            if (!empty($cur['file']) && str_starts_with($cur['file'], 'assets/img/uploads/')) @unlink("$site/" . $cur['file']);
            $entry['file'] = $new;
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
/* collections: save / delete / publish toggle */
$cn = (string)($_REQUEST['c'] ?? '');
if ($in && $action === 'coll_save' && isset($schemas[$cn])) {
    $items = coll_all($cn);
    $id = preg_replace('/[^a-z0-9]/', '', (string)($_POST['id'] ?? ''));
    $idx = null;
    foreach ($items as $k => $it) if (($it['id'] ?? '') === $id) $idx = $k;
    $rec = $idx !== null ? $items[$idx] : ['id' => bin2hex(random_bytes(5))];
    foreach ($schemas[$cn]['fields'] as $f) {
        [$key, $label, $ftype] = $f;
        $req = !empty($f[3]);
        if ($ftype === 'image') {
            $new = save_upload($_FILES['f_' . $key] ?? [], $cn, $site, $err);
            if ($new) { if (!empty($rec[$key]) && str_starts_with((string)$rec[$key], 'assets/img/uploads/')) @unlink("$site/" . $rec[$key]); $rec[$key] = $new; }
            if (!empty($_POST['rm_' . $key]) && !empty($rec[$key])) { if (str_starts_with((string)$rec[$key], 'assets/img/uploads/')) @unlink("$site/" . $rec[$key]); $rec[$key] = ''; }
            continue;
        }
        $v = trim(strip_tags((string)($_POST['f_' . $key] ?? '')));
        if ($ftype === 'select' && !in_array($v, $f[3], true)) $v = $f[3][0];
        if ($req && $v === '') $err = $label . ' is required.';
        $rec[$key] = mb_substr($v, 0, $ftype === 'textarea' ? 2000 : 300);
    }
    if ($cn === 'listings' && ($rec['ref'] ?? '') === '') $rec['ref'] = 'CAM-L-' . str_pad((string)(count($items) + 1), 4, '0', STR_PAD_LEFT);
    $rec['published'] = isset($_POST['published']);
    if (!$err) {
        if ($idx !== null) $items[$idx] = $rec; else $items[] = $rec;
        coll_save($cn, $items);
        $msg = ucfirst($schemas[$cn]['single']) . ' saved' . ($rec['published'] ? ' and published on the website.' : ' as a draft (not shown on the website).');
        $_GET['id'] = $rec['id'];
    } else { $_GET['id'] = $id !== '' ? $id : 'new'; }
}
if ($in && $action === 'coll_delete' && isset($schemas[$cn])) {
    $id = (string)($_POST['id'] ?? '');
    $items = coll_all($cn);
    foreach ($items as $k => $it) if (($it['id'] ?? '') === $id) {
        if (!empty($it['image']) && str_starts_with((string)$it['image'], 'assets/img/uploads/')) @unlink("$site/" . $it['image']);
        unset($items[$k]);
    }
    coll_save($cn, $items);
    $msg = ucfirst($schemas[$cn]['single']) . ' deleted.';
}
if ($in && $action === 'coll_toggle' && isset($schemas[$cn])) {
    $id = (string)($_POST['id'] ?? '');
    $items = coll_all($cn);
    foreach ($items as $k => $it) if (($it['id'] ?? '') === $id) $items[$k]['published'] = empty($it['published']);
    coll_save($cn, $items);
    $msg = 'Visibility updated.';
}

/* ------------------------------------------------------------------ shared view data */
$S = site_settings();
$page = (string)($_GET['p'] ?? 'dashboard');
$views = ['dashboard', 'visitors', 'pages', 'content', 'coll', 'images', 'settings', 'enquiries', 'forms', 'system', 'account'];
if (!in_array($page, $views, true)) $page = 'dashboard';
$pg = (string)($_GET['pg'] ?? 'index');
if (!isset($cmap[$pg])) $pg = 'index';
$cn = (string)($_GET['c'] ?? $cn);
if ($page === 'coll' && !isset($schemas[$cn])) $cn = 'listings';

$enq = $in ? load_enquiries($root) : [];
$newCount = count(array_filter($enq, fn($r) => ($r['status'] ?? '') === 'New enquiry'));
$ovAll = $in ? jread("$root/content.json") : [];
$editedCount = array_sum(array_map('count', $ovAll));

function nav_item(string $href, string $label, string $icon, bool $on, ?int $badge = null): string {
    return '<a href="' . $href . '"' . ($on ? ' aria-current="page"' : '') . '>' . ico($icon) . '<span>' . h($label) . '</span>' . ($badge ? '<em>' . $badge . '</em>' : '') . '</a>';
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

$titles = ['dashboard' => 'Dashboard', 'visitors' => 'Visitor analytics', 'pages' => 'Website text', 'content' => 'Website text', 'coll' => $schemas[$cn]['label'] ?? 'Content',
           'images' => 'Photos', 'settings' => 'Contact & credentials', 'enquiries' => 'Enquiries', 'forms' => 'Forms & downloads', 'system' => 'System check', 'account' => 'Account'];

/* command-palette destinations */
$jump = [['Dashboard', '?p=dashboard'], ['Visitor analytics', '?p=visitors'], ['Photos', '?p=images'], ['Contact & credentials', '?p=settings'],
         ['Enquiries', '?p=enquiries'], ['Forms & downloads', '?p=forms'], ['System check', '?p=system'], ['Account & password', '?p=account']];
foreach ($PAGES as $k => $l) if (isset($cmap[$k])) $jump[] = ['Edit text: ' . $l, '?p=content&pg=' . $k];
foreach ($schemas as $k => $sc) { $jump[] = [$sc['label'], '?p=coll&c=' . $k]; $jump[] = ['Add ' . $sc['single'], '?p=coll&c=' . $k . '&id=new']; }

require __DIR__ . '/views/layout.php';
