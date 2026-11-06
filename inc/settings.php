<?php
/** Site settings: defaults merged with storage/settings.json (edited in /admin). */
declare(strict_types=1);
@ini_set('display_errors', '0');   // never show PHP errors to visitors; they go to the server log
@ini_set('log_errors', '1');

function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function site_defaults(): array {
    return [
        'phone'   => '+256 785 274 288',
        'email'   => 'Okunamatthew72@gmail.com',
        'address' => 'Kampala, Uganda',
        'wa'      => '256751299076',
        'hours'   => 'Mon – Fri · 8:00 – 17:00 (EAT)',
        'md'      => 'Okuna Mathew Elvis',
        'notify'  => true,
        // Shown in the home-page ticker only when "verified" is true.
        'credentials' => [
            ['label' => 'URSB Registered',                   'verified' => false],
            ['label' => 'URA Tax Compliant & TIN Certified', 'verified' => false],
            ['label' => 'PPDA Bidding Eligible',             'verified' => false],
            ['label' => 'Member of AREA',                    'verified' => false],
        ],
        'ticker_extra' => ['Zero-Fraud Asset Management', 'Documented. Time-stamped. Accountable.'],
    ];
}

function site_settings(): array {
    $s = site_defaults();
    $f = dirname(__DIR__) . '/storage/settings.json';
    if (is_file($f)) {
        $j = json_decode((string)file_get_contents($f), true);
        if (is_array($j)) $s = array_replace($s, $j);
    }
    $ticker = [];
    foreach ($s['credentials'] as $c) if (!empty($c['verified'])) $ticker[] = $c['label'];
    $s['ticker'] = array_merge($ticker, $s['ticker_extra']);
    return $s;
}

/** Image slots shown on the website; replace any of them in /admin → Images. */
function site_image_slots(): array {
    return [
        'hero' => 'Home page – hero background', 'home' => 'Home page – introduction', 'about' => 'About Us', 'property' => 'Property Management',
        'supervision' => 'Construction Supervision', 'consultancy' => 'Construction Contracts & Consultancy',
        'land' => 'Land Verification & Brokerage', 'farm' => 'Farm Management', 'supply' => 'General Supplies & Tendering',
        'listing' => 'Sample land listing card',
    ];
}

/** Returns ['file','alt','credit'] for a slot (admin override first, then bundled photo), or null. */
function slot_image(string $slot): ?array {
    $root = dirname(__DIR__);
    $raw = is_file("$root/storage/settings.json") ? (json_decode((string)file_get_contents("$root/storage/settings.json"), true) ?: []) : [];
    $o = $raw['images'][$slot] ?? null;
    if ($o && !empty($o['file']) && is_file("$root/" . $o['file'])) {
        return ['file' => $o['file'], 'alt' => $o['alt'] ?? '', 'credit' => $o['credit'] ?? ''];
    }
    $cr = is_file("$root/assets/img/credits.json") ? (json_decode((string)file_get_contents("$root/assets/img/credits.json"), true) ?: []) : [];
    if (is_file("$root/assets/img/$slot.jpg")) {
        return ['file' => "assets/img/$slot.jpg",
                'alt'    => ($o['alt'] ?? '') !== '' ? $o['alt'] : ($cr[$slot]['alt'] ?? ''),
                'credit' => $o !== null && array_key_exists('credit', $o) ? $o['credit'] : ($cr[$slot]['credit'] ?? '')];
    }
    return null;
}

/** Website text: admin edit (storage/content.json) wins over the original (inc/content-map.json). */
function t(string $page, string $id): string {
    static $map = null, $ov = null;
    if ($map === null) {
        $root = dirname(__DIR__);
        $map = json_decode((string)@file_get_contents("$root/inc/content-map.json"), true) ?: [];
        $ov  = json_decode((string)@file_get_contents("$root/storage/content.json"), true) ?: [];
    }
    return $ov[$page][$id] ?? ($map[$page][$id]['d'] ?? '');
}

require_once __DIR__ . '/collections.php';
function sel(string $k, string $v): string { return (($_GET[$k] ?? '') === $v) ? ' selected' : ''; }

$S = site_settings();

/* Visitor analytics: public pages only (files in the site root, not submit/track). */
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(dirname($_SERVER['SCRIPT_FILENAME'])) === realpath(dirname(__DIR__))) {
    $__sn = basename($_SERVER['SCRIPT_FILENAME'], '.php');
    if (!in_array($__sn, ['submit', 'track'], true)) {
        require_once __DIR__ . '/analytics.php';
        track_view($__sn);
    }
}
