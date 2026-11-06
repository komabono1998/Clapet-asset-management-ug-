<?php defined('CLAPET_ADMIN') || exit; ?><?php
$checks = [];
$add = function (string $group, string $label, string $state, string $detail) use (&$checks) { $checks[$group][] = [$label, $state, $detail]; };
$https = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$local = preg_match('/^(localhost|127\.|\[::1\])/', $_SERVER['HTTP_HOST'] ?? '') === 1;

// server
$add('Server', 'PHP version', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'fail', PHP_VERSION . ' (8.1 or newer required)');
foreach (['pdo_sqlite' => 'Visitor analytics', 'mbstring' => 'Text handling', 'iconv' => 'PDF export', 'fileinfo' => 'Safe file uploads', 'json' => 'Data storage'] as $ext => $why)
    $add('Server', "Extension: $ext", extension_loaded($ext) ? 'ok' : 'fail', extension_loaded($ext) ? "Loaded ($why)" : "Missing. Enable it in your hosting PHP settings ($why).");
$add('Server', 'HTTPS', $https ? 'ok' : ($local ? 'warn' : 'fail'), $https ? 'This page is served over HTTPS.' : 'Not using HTTPS. Turn on the free SSL certificate in your hosting panel before launch.');
$add('Server', 'PHP errors hidden from visitors', ini_get('display_errors') ? 'warn' : 'ok', ini_get('display_errors') ? 'display_errors is on for this request.' : 'Errors go to the server log, not the page.');

// storage
$root = dirname(__DIR__, 2);
foreach (['storage', 'storage/enquiries', 'storage/uploads', 'storage/collections', 'assets/img/uploads'] as $d) {
    $p = "$root/$d";
    if (!is_dir($p)) @mkdir($p, 0755, true);
    $add('Storage', "Folder: $d", is_dir($p) && is_writable($p) ? 'ok' : 'fail', is_writable($p) ? 'Exists and is writable.' : 'Not writable. Set permissions to 755 (or 775) for the web user.');
}
$db = adb();
$add('Storage', 'Analytics database', $db ? 'ok' : 'fail', $db ? 'Ready.' : 'Could not open storage/analytics.sqlite.');

// private files really are private
$probe = function (string $path) use ($base): ?int {
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true, 'method' => 'GET', 'header' => "User-Agent: SystemCheck\r\n"], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $r = @file_get_contents($base . '/' . $path, false, $ctx);
    foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) return (int)$m[1];
    return $r === false ? null : 200;
};
foreach (['storage/admin.json' => 'Admin login file', 'inc/settings.php' => 'Internal code', 'tools/build.py' => 'Build tools', '.git/config' => 'Git data', 'admin/lib.php' => 'Admin internals'] as $path => $label) {
    $code = $probe($path);
    $add('Privacy', "Not public: $label", $code === null ? 'warn' : (in_array($code, [403, 404], true) ? 'ok' : 'fail'),
        $code === null ? "Could not test $path from the server itself." : ($code === 200 ? "$path is reachable from the internet. Make sure .htaccess files are uploaded and enabled." : "Blocked ($code)."));
}
$add('Privacy', 'Setup key removed', is_file("$root/storage/setup.key") ? 'fail' : 'ok', is_file("$root/storage/setup.key") ? 'storage/setup.key still exists. Delete it.' : 'No setup key lying around.');

// content readiness
$add('Content', 'Contact details confirmed', ($S['phone'] ?? '') !== '+256 700 000 000' && ($S['email'] ?? '') !== '' ? 'ok' : 'warn', $S['phone'] . ' · ' . $S['email']);
$ver = count(array_filter($S['credentials'], fn($c) => !empty($c['verified'])));
$add('Content', 'Credentials ticker', $ver ? 'ok' : 'warn', $ver ? "$ver verified credential(s) shown." : 'No credential is marked verified yet, so none are shown.');
$mailOk = (bool)($S['notify'] ?? false);
$add('Content', 'Enquiry email notifications', $mailOk ? 'ok' : 'warn', $mailOk ? 'On. Sent to ' . $S['email'] . ' (your host must allow PHP mail).' : 'Off. Enquiries are still saved in the Enquiries page.');
$add('Content', 'Photos', 'ok', 'Default photos are Creative Commons images with credits shown. Replace them under Photos when you have your own.');

$icon = ['ok' => '✓', 'warn' => '!', 'fail' => '✕'];
$tot = ['ok' => 0, 'warn' => 0, 'fail' => 0];
foreach ($checks as $g) foreach ($g as $c) $tot[$c[1]]++;
?>
<div class="welcome" style="margin-bottom:18px"><div><h2>System check</h2><p>Run this after every deployment. <b><?= $tot['fail'] ?> to fix</b> · <?= $tot['warn'] ?> to review · <?= $tot['ok'] ?> passing.</p></div>
<div class="wbtn"><a class="btn gold" href="?p=system"><?= ico('bolt') ?>Run again</a></div></div>
<?php foreach ($checks as $group => $rows): ?>
<div class="card"><h2><?= h($group) ?></h2>
<?php foreach ($rows as [$label, $state, $detail]): ?>
<div class="sys <?= $state ?>"><span class="si"><?= $icon[$state] ?></span><div><b><?= h($label) ?></b><small><?= h($detail) ?></small></div></div>
<?php endforeach; ?></div>
<?php endforeach; ?>
