<?php
/**
 * Privacy-friendly visitor analytics (no cookies, no raw IPs stored).
 * Visitor id = hash(ip + user agent + month + secret salt); session id = visitor + 30-minute window.
 * Data lives in storage/analytics.sqlite (denied over HTTP).
 */
declare(strict_types=1);
require_once __DIR__ . '/env.php';

function adb(): ?PDO {
    static $pdo = false;
    if ($pdo !== false) return $pdo;
    try {
        $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/storage/analytics.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS views(id INTEGER PRIMARY KEY, ts INT, day TEXT, hr INT, wd INT, page TEXT, vid TEXT, sid TEXT, refhost TEXT, src TEXT, dev TEXT, br TEXT, os TEXT, reg TEXT, utm TEXT)');
        $pdo->exec('CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY, ts INT, day TEXT, page TEXT, vid TEXT, sid TEXT, type TEXT, label TEXT, ms INT)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS v_day ON views(day)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS e_day ON events(day, type)');
    } catch (Throwable $e) { $pdo = null; }
    return $pdo;
}

function track_ids(): array {
    $f = dirname(__DIR__) . '/storage/.salt';
    if (!is_file($f)) @file_put_contents($f, bin2hex(random_bytes(16)));
    $salt = (string)@file_get_contents($f);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $vid = substr(hash('sha256', $salt . $ip . $ua . date('Y-m')), 0, 16);
    $sid = substr(hash('sha256', $vid . intdiv(time(), 1800)), 0, 16);
    return [$vid, $sid];
}

function track_is_bot(string $ua): bool {
    return $ua === '' || (bool)preg_match('/bot|crawl|spider|slurp|curl|wget|python|httpclient|headless|lighthouse|preview|monitor|facebookexternalhit|whatsapp\/|go-http|java\//i', $ua);
}

function track_ua(string $ua): array {
    $dev = preg_match('/ipad|tablet|kindle|silk/i', $ua) ? 'Tablet' : (preg_match('/mobi|iphone|android/i', $ua) ? 'Mobile' : 'Desktop');
    $br = 'Other';
    foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'SamsungBrowser' => 'Samsung', 'Chrome' => 'Chrome', 'Safari' => 'Safari'] as $k => $v) {
        if (stripos($ua, $k) !== false) { $br = $v; break; }
    }
    $os = 'Other';
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iOS', 'iPad' => 'iOS', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $k => $v) {
        if (stripos($ua, $k) !== false) { $os = $v; break; }
    }
    return [$dev, $br, $os];
}

function track_source(string $host, string $self): string {
    if ($host === '' || $host === $self) return 'Direct';
    if (preg_match('/google|bing|duckduckgo|yahoo|yandex|baidu|ecosia/i', $host)) return 'Search';
    if (preg_match('/facebook|fb\.|instagram|twitter|t\.co|x\.com|linkedin|whatsapp|wa\.me|tiktok|youtube|telegram|pinterest/i', $host)) return 'Social';
    return 'Referral';
}

/** Records one page view. Called automatically from inc/settings.php on public pages. */
function track_view(string $page): void {
    if (!env_bool('ANALYTICS_ENABLED', true)) return;
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    if (($_SERVER['HTTP_DNT'] ?? '') === '1' || isset($_COOKIE['clapet_admin'])) return;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (track_is_bot($ua) || ($_SERVER['HTTP_PURPOSE'] ?? '') === 'prefetch') return;
    $db = adb();
    if (!$db) return;
    [$vid, $sid] = track_ids();
    [$dev, $br, $os] = track_ua($ua);
    $self = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    $ref = (string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
    $ref = strtolower(preg_replace('/^www\./', '', $ref));
    $reg = '';
    if (preg_match('/[a-z]{2}-([A-Z]{2})/', $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', $m)) $reg = $m[1];
    $utm = substr(preg_replace('/[^\w .-]/', '', (string)($_GET['utm_source'] ?? '')), 0, 40);
    $ts = time();
    try {
        $db->prepare('INSERT INTO views(ts,day,hr,wd,page,vid,sid,refhost,src,dev,br,os,reg,utm) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
           ->execute([$ts, date('Y-m-d', $ts), (int)date('G', $ts), (int)date('w', $ts), $page, $vid, $sid, $ref, track_source($ref, $self), $dev, $br, $os, $reg, $utm]);
    } catch (Throwable $e) { /* never break the page */ }
}

function track_event(string $type, string $label = '', string $page = '', int $ms = 0): void {
    $db = adb();
    if (!$db) return;
    [$vid, $sid] = track_ids();
    $ts = time();
    try {
        $db->prepare('INSERT INTO events(ts,day,page,vid,sid,type,label,ms) VALUES(?,?,?,?,?,?,?,?)')
           ->execute([$ts, date('Y-m-d', $ts), substr($page, 0, 60), $vid, $sid, substr($type, 0, 30), substr($label, 0, 80), $ms]);
    } catch (Throwable $e) {}
}

/** Everything the admin analytics dashboard shows, for the last $days days. */
function analytics_report(int $days): array {
    $db = adb();
    $out = ['ok' => (bool)$db, 'days' => $days];
    if (!$db) return $out;
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $q = function (string $sql, array $p = []) use ($db): array { $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); };
    $one = function (string $sql, array $p = []) use ($q) { $r = $q($sql, $p); return $r ? array_values($r[0])[0] : 0; };

    $views = (int)$one('SELECT COUNT(*) FROM views WHERE day>=?', [$from]);
    $visitors = (int)$one('SELECT COUNT(DISTINCT vid) FROM views WHERE day>=?', [$from]);
    $sessions = (int)$one('SELECT COUNT(DISTINCT sid) FROM views WHERE day>=?', [$from]);
    $bounced = (int)$one('SELECT COUNT(*) FROM (SELECT sid FROM views WHERE day>=? GROUP BY sid HAVING COUNT(*)=1)', [$from]);
    $dwell = (float)$one("SELECT AVG(ms) FROM events WHERE type='dwell' AND day>=? AND ms BETWEEN 1000 AND 1800000", [$from]);
    $forms = (int)$one("SELECT COUNT(*) FROM events WHERE type='form_submit' AND day>=?", [$from]);
    $prevFrom = date('Y-m-d', strtotime('-' . (2 * $days - 1) . ' days'));
    $prevViews = (int)$one('SELECT COUNT(*) FROM views WHERE day>=? AND day<?', [$prevFrom, $from]);
    $prevVisitors = (int)$one('SELECT COUNT(DISTINCT vid) FROM views WHERE day>=? AND day<?', [$prevFrom, $from]);
    $out['kpi'] = [
        'visitors' => $visitors, 'views' => $views, 'sessions' => $sessions,
        'pps' => $sessions ? round($views / $sessions, 2) : 0,
        'bounce' => $sessions ? round(100 * $bounced / $sessions, 1) : 0,
        'dwell' => (int)round($dwell / 1000), 'forms' => $forms,
        'conv' => $sessions ? round(100 * $forms / $sessions, 2) : 0,
        'live' => (int)$one('SELECT COUNT(DISTINCT vid) FROM views WHERE ts>?', [time() - 1800]),
        'today' => (int)$one('SELECT COUNT(*) FROM views WHERE day=?', [date('Y-m-d')]),
        'viewsDelta' => $prevViews ? round(100 * ($views - $prevViews) / $prevViews) : null,
        'visDelta' => $prevVisitors ? round(100 * ($visitors - $prevVisitors) / $prevVisitors) : null,
    ];
    $byDay = [];
    foreach ($q('SELECT day, COUNT(*) v, COUNT(DISTINCT vid) u FROM views WHERE day>=? GROUP BY day', [$from]) as $r) $byDay[$r['day']] = $r;
    $forms7 = [];
    foreach ($q("SELECT day, COUNT(*) c FROM events WHERE type='form_submit' AND day>=? GROUP BY day", [$from]) as $r) $forms7[$r['day']] = (int)$r['c'];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $out['series'][] = ['d' => $d, 'v' => (int)($byDay[$d]['v'] ?? 0), 'u' => (int)($byDay[$d]['u'] ?? 0), 'f' => $forms7[$d] ?? 0];
    }
    $out['pages'] = $q('SELECT page, COUNT(*) v, COUNT(DISTINCT vid) u FROM views WHERE day>=? GROUP BY page ORDER BY v DESC LIMIT 12', [$from]);
    $out['sources'] = $q('SELECT src k, COUNT(DISTINCT sid) n FROM views WHERE day>=? GROUP BY src ORDER BY n DESC', [$from]);
    $out['refs'] = $q("SELECT refhost k, COUNT(DISTINCT sid) n FROM views WHERE day>=? AND refhost<>'' GROUP BY refhost ORDER BY n DESC LIMIT 8", [$from]);
    $out['devices'] = $q('SELECT dev k, COUNT(DISTINCT vid) n FROM views WHERE day>=? GROUP BY dev ORDER BY n DESC', [$from]);
    $out['browsers'] = $q('SELECT br k, COUNT(DISTINCT vid) n FROM views WHERE day>=? GROUP BY br ORDER BY n DESC LIMIT 6', [$from]);
    $out['os'] = $q('SELECT os k, COUNT(DISTINCT vid) n FROM views WHERE day>=? GROUP BY os ORDER BY n DESC LIMIT 6', [$from]);
    $out['regions'] = $q("SELECT reg k, COUNT(DISTINCT vid) n FROM views WHERE day>=? AND reg<>'' GROUP BY reg ORDER BY n DESC LIMIT 8", [$from]);
    $hours = array_fill(0, 24, 0);
    foreach ($q('SELECT hr, COUNT(*) n FROM views WHERE day>=? GROUP BY hr', [$from]) as $r) $hours[(int)$r['hr']] = (int)$r['n'];
    $out['hours'] = $hours;
    $wd = array_fill(0, 7, 0);
    foreach ($q('SELECT wd, COUNT(*) n FROM views WHERE day>=? GROUP BY wd', [$from]) as $r) $wd[(int)$r['wd']] = (int)$r['n'];
    $out['weekdays'] = $wd;
    $out['events'] = $q("SELECT label k, COUNT(*) n FROM events WHERE type='click' AND day>=? GROUP BY label ORDER BY n DESC LIMIT 8", [$from]);
    $out['formsBy'] = $q("SELECT label k, COUNT(*) n FROM events WHERE type='form_submit' AND day>=? GROUP BY label ORDER BY n DESC", [$from]);
    $out['recent'] = $q('SELECT ts,page,src,refhost,dev,br,os,reg FROM views ORDER BY id DESC LIMIT 15');
    return $out;
}
