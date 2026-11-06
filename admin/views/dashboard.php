<?php defined('CLAPET_ADMIN') || exit; ?><?php
$rep = analytics_report(14);
$k = $rep['kpi'] ?? ['visitors' => 0, 'views' => 0, 'forms' => 0, 'live' => 0, 'today' => 0, 'viewsDelta' => null, 'visDelta' => null, 'sessions' => 0];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$customPhotos = count(array_filter((array)(jread("$root/settings.json")['images'] ?? []), fn($i) => !empty($i['file'])));
$verified = count(array_filter($S['credentials'], fn($c) => !empty($c['verified'])));
$published = 0; foreach (array_keys($schemas) as $c) $published += count(coll_published($c));
$delta = fn($d) => $d === null ? '<span class="dl flat">new</span>' : ($d >= 0 ? '<span class="dl up">▲ ' . $d . '%</span>' : '<span class="dl down">▼ ' . abs($d) . '%</span>');
$checks = [
    ['Contact details confirmed', $S['phone'] !== '+256 700 000 000' && $S['email'] !== '', '?p=settings'],
    ['At least one credential verified', $verified > 0, '?p=settings#credentials'],
    ['Replaced stock photos with your own', $customPhotos > 0, '?p=images'],
    ['Edited website text', $editedCount > 0, '?p=pages'],
    ['Published a listing, project or testimonial', $published > 0, '?p=coll&c=projects'],
    ['Received a test enquiry', count($enq) > 0, '?p=enquiries'],
];
$done = count(array_filter($checks, fn($c) => $c[1]));
?>
<section class="welcome">
  <div><h2><?= $greet ?>, <?= h(ucfirst(explode('@', (string)($admin['user'] ?? 'there'))[0])) ?>.</h2>
  <p><?= date('l, j F Y') ?> · <?= $newCount ? '<b>' . $newCount . ' new enquir' . ($newCount > 1 ? 'ies' : 'y') . '</b> waiting for a reply' : 'No new enquiries right now' ?>.</p></div>
  <div class="wbtn"><a class="btn gold" href="?p=content&amp;pg=index"><?= ico('edit') ?>Edit home page</a><a class="btn ghostw" href="?p=visitors"><?= ico('chart') ?>Open analytics</a></div>
</section>

<div class="stats">
  <a class="stat" href="?p=visitors"><i><?= ico('users') ?></i><div><b><?= number_format($k['visitors']) ?></b><span>Visitors · 14 days <?= $delta($k['visDelta']) ?></span></div></a>
  <a class="stat" href="?p=visitors"><i><?= ico('eye') ?></i><div><b><?= number_format($k['views']) ?></b><span>Page views <?= $delta($k['viewsDelta']) ?></span></div></a>
  <a class="stat" href="?p=forms"><i><?= ico('mail') ?></i><div><b><?= count($enq) ?></b><span>Form submissions · <?= $newCount ?> new</span></div></a>
  <a class="stat live" href="?p=visitors"><i><?= ico('live') ?></i><div><b><?= (int)$k['live'] ?></b><span>On the site now</span></div></a>
</div>

<div class="cols">
<div>
  <div class="card"><div class="ch"><h2>Traffic, last 14 days</h2><a class="more" href="?p=visitors">Full analytics →</a></div>
    <?php if (!($rep['ok'] ?? false) || !$k['views']): ?><p class="empty-s">No visits recorded yet. Open the website in another tab and refresh this page to see data appear.</p>
    <?php else: ?><div class="chart" id="miniChart" data-series='<?= h(json_encode($rep['series'])) ?>'></div><?php endif; ?></div>

  <div class="card"><h2>Quick links</h2>
    <div class="qgrp"><h3>Edit the website</h3><div class="quick">
      <a href="?p=content&amp;pg=index"><?= ico('edit') ?>Home page</a><a href="?p=content&amp;pg=about"><?= ico('edit') ?>About Us</a><a href="?p=content&amp;pg=services"><?= ico('edit') ?>Services</a>
      <a href="?p=content&amp;pg=contact"><?= ico('edit') ?>Contact page</a><a href="?p=content&amp;pg=global"><?= ico('edit') ?>Menu &amp; footer</a><a href="?p=images"><?= ico('img') ?>Photos</a>
      <a href="?p=settings"><?= ico('phone') ?>Phone &amp; email</a><a href="?p=settings#credentials"><?= ico('shield') ?>Credentials</a><a href="?p=pages"><?= ico('doc') ?>All pages</a></div></div>
    <div class="qgrp"><h3>Manage content</h3><div class="quick">
      <?php foreach ($schemas as $ck => $sc): ?><a href="?p=coll&amp;c=<?= $ck ?>&amp;id=new"><?= ico('plus') ?>Add <?= h($sc['single']) ?></a><?php endforeach; ?></div></div>
    <div class="qgrp"><h3>Inbox &amp; reports</h3><div class="quick">
      <a href="?p=enquiries"><?= ico('mail') ?>Enquiries<?= $newCount ? ' <em>' . $newCount . '</em>' : '' ?></a><a href="?p=forms"><?= ico('down') ?>Download forms</a>
      <a href="download.php?type=forms&amp;form=all&amp;fmt=xlsx"><?= ico('down') ?>All forms (Excel)</a><a href="download.php?type=analytics&amp;days=30&amp;fmt=csv"><?= ico('down') ?>Visits (CSV)</a>
      <a href="?p=visitors"><?= ico('chart') ?>Visitor analytics</a><a href="../index.php" target="_blank"><?= ico('eye') ?>View website</a></div></div>
  </div>
</div>

<div>
  <div class="card"><div class="ch"><h2>Launch checklist</h2><span class="pillc"><?= $done ?>/<?= count($checks) ?></span></div>
    <div class="bar"><i style="width:<?= (int)round(100 * $done / count($checks)) ?>%"></i></div>
    <ul class="checklist"><?php foreach ($checks as $c): ?><li class="<?= $c[1] ? 'on' : '' ?>"><a href="<?= h($c[2]) ?>"><span class="tick"><?= $c[1] ? '✓' : '' ?></span><?= h($c[0]) ?></a></li><?php endforeach; ?></ul></div>

  <div class="card"><div class="ch"><h2>Top pages</h2><a class="more" href="?p=visitors">More →</a></div>
    <?php if (empty($rep['pages'])): ?><p class="empty-s">Page rankings appear after the first visits.</p><?php else: $max = max(array_map(fn($p) => (int)$p['v'], $rep['pages'])); foreach (array_slice($rep['pages'], 0, 5) as $p): ?>
      <div class="rowbar"><span><?= h($p['page']) ?></span><div><i style="width:<?= max(4, (int)round(100 * $p['v'] / $max)) ?>%"></i></div><b><?= (int)$p['v'] ?></b></div>
    <?php endforeach; endif; ?></div>

  <div class="card"><div class="ch"><h2>Latest enquiries</h2><a class="more" href="?p=enquiries">All →</a></div>
    <?php if (!$enq): ?><p class="empty-s">Brief, consultation and partner forms land here.</p><?php else: foreach (array_slice($enq, 0, 4) as $r): ?>
      <a class="enq" href="?p=enquiries#<?= h($r['reference']) ?>"><b><?= h($r['data']['full_name'] ?? '') ?></b><span><?= h(form_types()[$r['service']] ?? $r['service']) ?></span><em class="chip<?= ($r['status'] ?? '') === 'New enquiry' ? ' new' : '' ?>"><?= h($r['status']) ?></em></a>
    <?php endforeach; endif; ?></div>
</div>
</div>
