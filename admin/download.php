<?php
/** Authenticated downloads: forms, analytics, collections and website text in csv/xlsx/json/xml/pdf/docx/html/txt. */
declare(strict_types=1);
define('CLAPET_ADMIN', true);
require dirname(__DIR__) . '/inc/settings.php';
require dirname(__DIR__) . '/inc/analytics.php';
require dirname(__DIR__) . '/inc/export.php';
require __DIR__ . '/lib.php';

session_name('clapet_admin');
session_start();
if (empty($_SESSION['in'])) { http_response_code(403); exit('Sign in to the admin first.'); }
session_write_close();

$site = dirname(__DIR__);
$root = "$site/storage";
$fmt  = (string)($_GET['fmt'] ?? 'csv');
$type = (string)($_GET['type'] ?? 'forms');
$date = fn(string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET[$k] ?? '')) ? (string)$_GET[$k] : '';

if ($type === 'forms') {
    $recs = load_enquiries($root);
    $form = (string)($_GET['form'] ?? 'all');
    $ref  = preg_replace('/[^A-Z0-9\-]/', '', (string)($_GET['ref'] ?? ''));
    $from = $date('from'); $to = $date('to');
    $recs = array_values(array_filter($recs, function ($r) use ($form, $ref, $from, $to) {
        if ($ref !== '') return ($r['reference'] ?? '') === $ref;
        if ($form !== 'all' && ($r['service'] ?? '') !== $form) return false;
        $d = substr((string)($r['received'] ?? ''), 0, 10);
        return ($from === '' || $d >= $from) && ($to === '' || $d <= $to);
    }));
    $rows = enquiry_rows($recs);
    $title = $ref !== '' ? "Enquiry $ref" : ($form === 'all' ? 'All form submissions' : (form_types()[$form] ?? $form));
    export_send(rows_cols($rows) ?: ['reference'], $rows, $fmt, $title, $ref !== '' ? $ref : 'forms-' . $form);
}

if ($type === 'analytics') {
    $days = max(1, min(365, (int)($_GET['days'] ?? 30)));
    $db = adb();
    $rows = [];
    if ($db) {
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $st = $db->prepare('SELECT ts,page,src,refhost,dev,br,os,reg,utm FROM views WHERE day>=? ORDER BY id DESC');
        $st->execute([$from]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $rows[] = ['time' => date('Y-m-d H:i:s', (int)$v['ts']), 'page' => $v['page'], 'source' => $v['src'], 'referrer' => $v['refhost'], 'device' => $v['dev'],
                       'browser' => $v['br'], 'os' => $v['os'], 'region' => $v['reg'], 'campaign' => $v['utm']];
        }
    }
    export_send(['time', 'page', 'source', 'referrer', 'device', 'browser', 'os', 'region', 'campaign'], $rows, $fmt, "Website visits, last $days days", "visits-$days-days");
}

if ($type === 'collection') {
    $schemas = coll_schemas();
    $c = (string)($_GET['c'] ?? '');
    if (!isset($schemas[$c])) { http_response_code(404); exit('Unknown collection'); }
    $cols = array_merge(array_map(fn($f) => $f[0], $schemas[$c]['fields']), ['published']);
    $rows = array_map(fn($i) => array_map(fn($v) => is_bool($v) ? ($v ? 'Yes' : 'No') : (string)$v, array_merge(['published' => ''], $i)), coll_all($c));
    export_send($cols, $rows, $fmt, $schemas[$c]['label'], $c);
}

if ($type === 'text') {
    $map = jread("$site/inc/content-map.json");
    $ov = jread("$root/content.json");
    $rows = [];
    foreach ($map as $pg => $blocks) foreach ($blocks as $id => $b) {
        $rows[] = ['page' => $pg, 'block' => $id, 'type' => $b['t'], 'section' => $b['s'], 'original' => strip_tags((string)$b['d']), 'current' => strip_tags((string)($ov[$pg][$id] ?? $b['d'])), 'edited' => isset($ov[$pg][$id]) ? 'Yes' : 'No'];
    }
    export_send(['page', 'block', 'type', 'section', 'original', 'current', 'edited'], $rows, $fmt, 'Website text', 'website-text');
}

http_response_code(400);
echo 'Unknown download.';
