<?php defined('CLAPET_ADMIN') || exit; ?><?php
$ft = form_types();
$stat = [];
foreach ($enq as $r) {
    $k = $r['service'] ?? 'general';
    $stat[$k]['n'] = ($stat[$k]['n'] ?? 0) + 1;
    $stat[$k]['new'] = ($stat[$k]['new'] ?? 0) + (($r['status'] ?? '') === 'New enquiry' ? 1 : 0);
    $stat[$k]['last'] = max($stat[$k]['last'] ?? '', substr((string)($r['received'] ?? ''), 0, 10));
}
$fmtShort = ['csv' => 'CSV', 'xlsx' => 'Excel', 'json' => 'JSON', 'xml' => 'XML', 'pdf' => 'PDF', 'docx' => 'Word', 'html' => 'HTML', 'txt' => 'Text'];
?>
<div class="card"><div class="ch"><h2>Download every form on the website</h2></div>
<p class="hint">Each form on the public site is listed below. Pick a date range (optional), then choose a format. Files include every field the visitor filled in, plus the reference number, date, status and attachment names.</p>
<div class="rangebar"><label>From<input type="date" id="rf"></label><label>To<input type="date" id="rt"></label><button type="button" class="btn sm ghost" id="rclear">Clear dates</button></div></div>

<div class="card"><div class="tbl"><table class="ftable"><tr><th>Form</th><th>Submissions</th><th>New</th><th>Last</th><th>Download</th></tr>
<tr class="all"><td><b>All forms combined</b></td><td><?= count($enq) ?></td><td><?= $newCount ?></td><td><?= h($enq ? substr((string)$enq[0]['received'], 0, 10) : '·') ?></td>
<td class="fm"><?php foreach ($fmtShort as $f => $l): ?><a class="fmt" data-dlr href="download.php?type=forms&amp;form=all&amp;fmt=<?= $f ?>"><?= $l ?></a><?php endforeach; ?></td></tr>
<?php foreach ($ft as $key => $label): $s = $stat[$key] ?? ['n' => 0, 'new' => 0, 'last' => '']; ?>
<tr><td><b><?= h($label) ?></b><br><small><?= h($key) ?></small></td><td><?= $s['n'] ?></td><td><?= $s['new'] ?></td><td><?= h($s['last'] ?: '·') ?></td>
<td class="fm"><?php foreach ($fmtShort as $f => $l): ?><a class="fmt" data-dlr href="download.php?type=forms&amp;form=<?= h($key) ?>&amp;fmt=<?= $f ?>"><?= $l ?></a><?php endforeach; ?></td></tr>
<?php endforeach; ?></table></div></div>

<div class="card"><h2>Other downloads</h2><p class="hint">Reports and content, in every format.</p>
<div class="dgrid">
<div><h3>Website visits <small>(last 30 days)</small></h3><div class="fm"><?php foreach ($fmtShort as $f => $l): ?><a class="fmt" href="download.php?type=analytics&amp;days=30&amp;fmt=<?= $f ?>"><?= $l ?></a><?php endforeach; ?></div></div>
<?php foreach ($schemas as $ck => $sc): ?><div><h3><?= h($sc['label']) ?> <small>(<?= count(coll_all($ck)) ?>)</small></h3><div class="fm"><?php foreach ($fmtShort as $f => $l): ?><a class="fmt" href="download.php?type=collection&amp;c=<?= $ck ?>&amp;fmt=<?= $f ?>"><?= $l ?></a><?php endforeach; ?></div></div><?php endforeach; ?>
<div><h3>Website text backup</h3><div class="fm"><?php foreach ($fmtShort as $f => $l): ?><a class="fmt" href="download.php?type=text&amp;fmt=<?= $f ?>"><?= $l ?></a><?php endforeach; ?></div></div>
</div></div>
