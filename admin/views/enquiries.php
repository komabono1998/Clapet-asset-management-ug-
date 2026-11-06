<?php defined('CLAPET_ADMIN') || exit; ?><?php
$ft = form_types();
$fform = (string)($_GET['form'] ?? ''); $fstat = (string)($_GET['status'] ?? ''); $fq = trim((string)($_GET['q'] ?? ''));
$list = array_values(array_filter($enq, function ($r) use ($fform, $fstat, $fq) {
    if ($fform !== '' && ($r['service'] ?? '') !== $fform) return false;
    if ($fstat !== '' && ($r['status'] ?? '') !== $fstat) return false;
    if ($fq !== '' && stripos(json_encode($r['data'] ?? [], JSON_UNESCAPED_UNICODE) . ($r['reference'] ?? ''), $fq) === false) return false;
    return true;
}));
?>
<form method="get" class="filterbar"><input type="hidden" name="p" value="enquiries">
<div class="search"><?= ico('search') ?><input type="search" name="q" value="<?= h($fq) ?>" placeholder="Search name, email, reference…"></div>
<select name="form" aria-label="Form type"><option value="">All forms</option><?php foreach ($ft as $k => $l): ?><option value="<?= h($k) ?>"<?= $k === $fform ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select>
<select name="status" aria-label="Status"><option value="">Any status</option><?php foreach ($statuses as $s): ?><option<?= $s === $fstat ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select>
<button class="btn sm">Filter</button>
<div class="dlmenu"><button type="button" class="btn sm ghost" data-dl><?= ico('down') ?>Download all</button><div class="dlpop" hidden><?php foreach (EXPORT_FORMATS as $f => $m): ?><a href="download.php?type=forms&amp;form=<?= h($fform ?: 'all') ?>&amp;fmt=<?= $f ?>"><?= h($m[0]) ?></a><?php endforeach; ?></div></div></form>

<?php if (!$list): ?><div class="card empty-big"><?= ico('mail') ?><h2>No enquiries <?= $enq ? 'match your filters' : 'yet' ?></h2><p>Submissions from the brief, consultation, partner and listing forms appear here with a reference number, and can be downloaded in any format.</p></div>
<?php else: foreach ($list as $r): $d = $r['data'] ?? []; ?>
<article class="card enqc" id="<?= h($r['reference']) ?>">
<div class="eh"><div><b class="ref"><?= h($r['reference']) ?></b><span class="chip"><?= h($ft[$r['service']] ?? $r['service']) ?></span><small><?= h(str_replace('T', ' ', substr((string)$r['received'], 0, 16))) ?></small></div>
<div class="eact"><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="ref" value="<?= h($r['reference']) ?>">
<select name="status" onchange="this.form.submit()" aria-label="Status"><?php foreach ($statuses as $s): ?><option<?= $s === ($r['status'] ?? '') ? ' selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></form>
<div class="dlmenu"><button type="button" class="btn sm ghost" data-dl><?= ico('down') ?>Download</button><div class="dlpop" hidden><?php foreach (EXPORT_FORMATS as $f => $m): ?><a href="download.php?type=forms&amp;ref=<?= h($r['reference']) ?>&amp;fmt=<?= $f ?>"><?= h($m[0]) ?></a><?php endforeach; ?></div></div></div></div>
<div class="who2"><b><?= h($d['full_name'] ?? '') ?></b><?= !empty($d['organisation']) ? ' · ' . h($d['organisation']) : '' ?><br>
<a href="mailto:<?= h($d['email'] ?? '') ?>"><?= h($d['email'] ?? '') ?></a><?= !empty($d['phone']) ? ' · ' . h($d['phone']) : '' ?></div>
<details><summary>View all details</summary><dl class="kv"><?php foreach ($d as $k => $v): if (in_array($k, ['full_name', 'email', 'phone', 'organisation', 'service'], true)) continue; ?><dt><?= h(ucfirst(str_replace('_', ' ', $k))) ?></dt><dd><?= h($v) ?></dd><?php endforeach; ?>
<?php if (!empty($r['files'])): ?><dt>Attachments</dt><dd><?= count($r['files']) ?> file(s) stored privately in storage/uploads/<?= h($r['reference']) ?></dd><?php endif; ?></dl></details>
</article>
<?php endforeach; endif; ?>
