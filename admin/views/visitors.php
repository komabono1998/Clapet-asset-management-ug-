<?php defined('CLAPET_ADMIN') || exit; ?><?php
$days = (int)($_GET['d'] ?? 30);
if (!in_array($days, [7, 14, 30, 90, 365], true)) $days = 30;
$rep = analytics_report($days);
$k = $rep['kpi'] ?? null;
$delta = fn($d) => $d === null ? '' : ($d >= 0 ? '<span class="dl up">▲ ' . $d . '%</span>' : '<span class="dl down">▼ ' . abs($d) . '%</span>');
$dur = fn(int $s) => $s >= 60 ? intdiv($s, 60) . 'm ' . ($s % 60) . 's' : $s . 's';
$dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>
<div class="toolbar"><div class="seg" role="tablist" aria-label="Date range">
<?php foreach ([7 => '7 days', 14 => '14 days', 30 => '30 days', 90 => '90 days', 365 => '1 year'] as $d => $l): ?><a href="?p=visitors&amp;d=<?= $d ?>"<?= $d === $days ? ' aria-current="page"' : '' ?>><?= $l ?></a><?php endforeach; ?></div>
<div class="dlmenu"><button type="button" class="btn sm ghost" data-dl><?= ico('down') ?>Export visits</button><div class="dlpop" hidden>
<?php foreach (EXPORT_FORMATS as $f => $m): ?><a href="download.php?type=analytics&amp;days=<?= $days ?>&amp;fmt=<?= $f ?>"><?= h($m[0]) ?></a><?php endforeach; ?></div></div></div>

<?php if (!$rep['ok']): ?><div class="er">Analytics storage is unavailable (SQLite is not enabled on this server).</div><?php elseif (!$k['views']): ?>
<div class="card empty-big"><?= ico('chart') ?><h2>No visits recorded yet</h2><p>Tracking is switched on. Every public page view, click on WhatsApp / call / email and form submission is counted automatically, without cookies or personal data. Open the website and refresh this page to see the first numbers.</p><a class="btn gold" href="../index.php" target="_blank">Open the website</a></div>
<?php else: ?>
<div class="kpis">
  <div class="kpi"><span>Visitors</span><b><?= number_format($k['visitors']) ?></b><?= $delta($k['visDelta']) ?></div>
  <div class="kpi"><span>Page views</span><b><?= number_format($k['views']) ?></b><?= $delta($k['viewsDelta']) ?></div>
  <div class="kpi"><span>Sessions</span><b><?= number_format($k['sessions']) ?></b><small><?= $k['pps'] ?> pages / session</small></div>
  <div class="kpi"><span>Bounce rate</span><b><?= $k['bounce'] ?>%</b><small>single-page sessions</small></div>
  <div class="kpi"><span>Time on page</span><b><?= $dur($k['dwell']) ?></b><small>average</small></div>
  <div class="kpi"><span>Enquiries</span><b><?= $k['forms'] ?></b><small><?= $k['conv'] ?>% conversion</small></div>
  <div class="kpi live"><span>On the site now</span><b><?= $k['live'] ?></b><small>last 30 minutes</small></div>
  <div class="kpi"><span>Views today</span><b><?= $k['today'] ?></b><small><?= date('D j M') ?></small></div>
</div>

<div class="card"><div class="ch"><h2>Visitors and page views</h2><div class="legend"><span class="lg a">Page views</span><span class="lg b">Visitors</span><span class="lg c">Enquiries</span></div></div>
<div class="chart tall" id="mainChart" data-series='<?= h(json_encode($rep['series'])) ?>'></div></div>

<div class="cols2">
<div class="card"><h2>Most visited pages</h2>
<?php $mx = max(1, ...array_map(fn($p) => (int)$p['v'], $rep['pages'])); foreach ($rep['pages'] as $p): ?>
<div class="rowbar"><span><?= h($p['page']) ?></span><div><i style="width:<?= max(3, (int)round(100 * $p['v'] / $mx)) ?>%"></i></div><b><?= (int)$p['v'] ?></b><small><?= (int)$p['u'] ?> visitors</small></div><?php endforeach; ?></div>

<div class="card"><h2>Where visitors come from</h2>
<?php $tot = max(1, array_sum(array_map(fn($r) => (int)$r['n'], $rep['sources']))); foreach ($rep['sources'] as $r): ?>
<div class="rowbar"><span><?= h($r['k']) ?></span><div><i style="width:<?= max(3, (int)round(100 * $r['n'] / $tot)) ?>%"></i></div><b><?= round(100 * $r['n'] / $tot) ?>%</b><small><?= (int)$r['n'] ?> sessions</small></div><?php endforeach; ?>
<?php if ($rep['refs']): ?><h3 class="sub3">Top referring sites</h3><?php foreach ($rep['refs'] as $r): ?><div class="mini"><span><?= h($r['k']) ?></span><b><?= (int)$r['n'] ?></b></div><?php endforeach; endif; ?></div>
</div>

<div class="cols3">
<?php foreach (['devices' => 'Devices', 'browsers' => 'Browsers', 'os' => 'Operating systems'] as $key => $lbl): $t2 = max(1, array_sum(array_map(fn($r) => (int)$r['n'], $rep[$key]))); ?>
<div class="card"><h2><?= $lbl ?></h2><?php foreach ($rep[$key] as $r): ?><div class="rowbar sm"><span><?= h($r['k']) ?></span><div><i style="width:<?= max(3, (int)round(100 * $r['n'] / $t2)) ?>%"></i></div><b><?= round(100 * $r['n'] / $t2) ?>%</b></div><?php endforeach; ?></div>
<?php endforeach; ?>
</div>

<div class="cols2">
<div class="card"><h2>Busiest hours <small>(server time)</small></h2><div class="chart bars" id="hoursChart" data-v='<?= h(json_encode($rep['hours'])) ?>' data-l='<?= h(json_encode(array_map(fn($i) => sprintf('%02d', $i), range(0, 23)))) ?>'></div></div>
<div class="card"><h2>Busiest days</h2><div class="chart bars" id="daysChart" data-v='<?= h(json_encode($rep['weekdays'])) ?>' data-l='<?= h(json_encode($dayNames)) ?>'></div></div>
</div>

<div class="cols2">
<div class="card"><h2>Actions visitors take</h2>
<?php if (!$rep['events'] && !$rep['formsBy']): ?><p class="empty-s">Clicks on WhatsApp, call, email and the brief buttons appear here.</p><?php endif;
foreach ($rep['events'] as $r): ?><div class="mini"><span><?= h($r['k']) ?> clicks</span><b><?= (int)$r['n'] ?></b></div><?php endforeach;
foreach ($rep['formsBy'] as $r): ?><div class="mini"><span>Form: <?= h(form_types()[$r['k']] ?? $r['k']) ?></span><b><?= (int)$r['n'] ?></b></div><?php endforeach; ?></div>
<div class="card"><h2>Visitor regions <small>(browser language)</small></h2>
<?php if (!$rep['regions']): ?><p class="empty-s">Regions appear when browsers report a country code.</p><?php endif; foreach ($rep['regions'] as $r): ?><div class="mini"><span><?= h($r['k']) ?></span><b><?= (int)$r['n'] ?></b></div><?php endforeach; ?></div>
</div>

<div class="card"><h2>Latest visits</h2><div class="tbl"><table><tr><th>Time</th><th>Page</th><th>Source</th><th>Device</th><th>Browser</th><th>OS</th><th>Region</th></tr>
<?php foreach ($rep['recent'] as $v): ?><tr><td><?= date('M j, H:i', (int)$v['ts']) ?></td><td><b><?= h($v['page']) ?></b></td><td><?= h($v['src']) ?><?= $v['refhost'] ? ' · ' . h($v['refhost']) : '' ?></td><td><?= h($v['dev']) ?></td><td><?= h($v['br']) ?></td><td><?= h($v['os']) ?></td><td><?= h($v['reg'] ?: '·') ?></td></tr><?php endforeach; ?></table></div>
<p class="hint">Privacy: no cookies and no IP addresses are stored. Visitors are counted with a one-way monthly hash, and browsers that send “Do Not Track”, bots and admin sessions are ignored. Unique visitors across a range are counted per month.</p></div>
<?php endif; ?>
