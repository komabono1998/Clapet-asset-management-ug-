<?php
declare(strict_types=1);
if (!defined('CLAPET_ADMIN')) { http_response_code(404); exit; }

function jread(string $f): array { return is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : []; }
function jwrite(string $f, array $d): void {
    @mkdir(dirname($f), 0755, true);
    file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ico(string $n): string {
    static $p = [
        'home' => '<path d="M3 11l9-8 9 8M5 10v10h14V10"/>', 'edit' => '<path d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/>',
        'img' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M21 17l-5-5-8 7"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>', 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.3c3 .2 5 2.3 5 5.7"/>',
        'out' => '<path d="M9 4H5v16h4M16 8l4 4-4 4M20 12H9"/>', 'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'bolt' => '<path d="M13 2L4 14h7l-1 8 9-12h-7z"/>', 'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'chart' => '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>', 'down' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
        'map' => '<path d="M12 22s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.6"/>',
        'crane' => '<path d="M3 21h18M6 21V8l12-4M6 8l-3 4M18 4v6M18 10v4"/>', 'box' => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/>',
        'doc' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>', 'plus' => '<path d="M12 5v14M5 12h14"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>', 'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
        'live' => '<circle cx="12" cy="12" r="3"/><path d="M6 6a8.5 8.5 0 000 12M18 6a8.5 8.5 0 010 12"/>', 'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
        'cmd' => '<path d="M9 9V6a3 3 0 10-3 3h3zm0 0h6m-6 0v6m6-6V6a3 3 0 113 3h-3zm0 0v6m0 0h3a3 3 0 11-3 3v-3zm0 0H9m0 0H6a3 3 0 103 3v-3z"/>',
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

/** Saves an uploaded image and returns its site-relative path, or null / error message via $err. */
function save_upload(array $up, string $prefix, string $site, ?string &$err): ?string {
    if (($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($up['error'] !== UPLOAD_ERR_OK) { $err = 'The upload failed. Please try again.'; return null; }
    $info = @getimagesize($up['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext || $up['size'] > 6 * 1024 * 1024) { $err = 'Please upload a JPG, PNG or WebP image up to 6 MB.'; return null; }
    $dir = "$site/assets/img/uploads";
    @mkdir($dir, 0755, true);
    $name = $prefix . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    return move_uploaded_file($up['tmp_name'], "$dir/$name") ? 'assets/img/uploads/' . $name : null;
}

function form_types(): array {
    return [
        'property-management' => 'Property management brief', 'construction-supervision' => 'Construction supervision brief',
        'construction-contracts' => 'Construction contract brief', 'land-verification' => 'Land verification brief',
        'farm-management' => 'Farm management brief', 'general-supplies' => 'Supplies & tender brief',
        'consultation' => 'Executive consultation request', 'partner' => 'Partner / supplier application',
        'listing' => 'Property listing submission', 'general' => 'General enquiry',
    ];
}

function load_enquiries(string $root): array {
    $out = [];
    foreach (glob("$root/enquiries/*.json") ?: [] as $f) { $r = jread($f); if ($r) $out[] = $r; }
    usort($out, fn($a, $b) => strcmp($b['received'] ?? '', $a['received'] ?? ''));
    return $out;
}

/** Flattens enquiry records into export rows. */
function enquiry_rows(array $records): array {
    $types = form_types();
    $rows = [];
    foreach ($records as $r) {
        $row = ['reference' => $r['reference'] ?? '', 'received' => str_replace('T', ' ', substr((string)($r['received'] ?? ''), 0, 19)),
                'form' => $types[$r['service'] ?? ''] ?? ($r['service'] ?? ''), 'status' => $r['status'] ?? ''];
        foreach (($r['data'] ?? []) as $k => $v) if (!in_array($k, ['service'], true)) $row[$k] = (string)$v;
        $row['attachments'] = implode('; ', array_map(fn($f) => $f['original'] ?? '', $r['files'] ?? []));
        $rows[] = $row;
    }
    return $rows;
}
function rows_cols(array $rows): array {
    $first = ['reference', 'received', 'form', 'status', 'full_name', 'organisation', 'email', 'phone'];
    $cols = [];
    foreach ($rows as $r) foreach (array_keys($r) as $k) $cols[$k] = true;
    $cols = array_keys($cols);
    $ordered = array_values(array_filter($first, fn($c) => in_array($c, $cols, true)));
    $rest = array_values(array_filter($cols, fn($c) => !in_array($c, $first, true) && $c !== 'attachments'));
    if (in_array('attachments', $cols, true)) $rest[] = 'attachments';
    return array_merge($ordered, $rest);
}
