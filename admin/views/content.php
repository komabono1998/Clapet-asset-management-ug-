<?php defined('CLAPET_ADMIN') || exit; ?><?php
$blocks = $cmap[$pg]; uasort($blocks, fn($a, $b) => $a['n'] <=> $b['n']);
$ov = $ovAll[$pg] ?? [];
$groups = []; foreach ($blocks as $id => $b) $groups[$b['s']][$id] = $b;
$linked = ['properties' => 'listings', 'projects' => 'projects', 'resources' => 'faqs', 'supplies-tendering' => 'tenders', 'index' => 'testimonials', 'about' => 'faqs'];
?>
<div class="crumb"><a href="?p=pages">All pages</a><span>/</span><b><?= h($PAGES[$pg] ?? $pg) ?></b>
<select aria-label="Switch page" onchange="location='?p=content&pg='+this.value"><?php foreach ($PAGES as $k => $lbl): if (!isset($cmap[$k])) continue; ?><option value="<?= h($k) ?>"<?= $k === $pg ? ' selected' : '' ?>><?= h($lbl) ?></option><?php endforeach; ?></select></div>
<?php if (isset($linked[$pg])): $lc = $linked[$pg]; ?><div class="linked"><?= ico($schemas[$lc]['icon']) ?><span>This page also shows <b><?= h($schemas[$lc]['label']) ?></b>, managed as a list.</span><a class="btn sm" href="?p=coll&amp;c=<?= $lc ?>">Manage <?= h(strtolower($schemas[$lc]['label'])) ?> →</a></div><?php endif; ?>
<form method="post" id="cform">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="content_save"><input type="hidden" name="pg" value="<?= h($pg) ?>">
<div class="toolbar"><div class="search"><?= ico('search') ?><input type="search" id="q" placeholder="Quick find text on this page…" aria-label="Quick find"></div>
<a class="btn sm ghost" href="../<?= $pg === 'global' ? 'index' : h($pg) ?>.php" target="_blank"><?= ico('eye') ?>View page</a></div>
<p class="hint">Edit any text below, then Save. Clear a field to restore the original. You can use <code>&lt;b&gt;</code>, <code>&lt;i&gt;</code>, <code>&lt;br&gt;</code> and <code>&lt;span class="gold"&gt;</code>. Contact details and credentials live under “Contact &amp; credentials”.</p>
<div class="jump"><?php $i = 0; foreach ($groups as $gt => $_): ?><a href="#s<?= $i++ ?>"><?= h($gt) ?></a><?php endforeach; ?></div>
<?php $i = 0; foreach ($groups as $gt => $items): ?>
<div class="sec" id="s<?= $i++ ?>"><h3><?= h($gt) ?></h3>
<?php foreach ($items as $id => $b): $val = $ov[$id] ?? $b['d']; $long = strlen($b['d']) > 80 || str_contains($b['d'], '<br'); ?>
<div class="blk<?= isset($ov[$id]) ? ' edited' : '' ?>" data-t="<?= h(mb_strtolower($b['l'])) ?>"><div class="meta"><span><?= h($b['t']) ?></span><?= isset($ov[$id]) ? '<b>Edited</b>' : '' ?></div>
<label><?php if ($long): ?><textarea name="c[<?= h($id) ?>]" rows="<?= max(2, min(7, (int)ceil(strlen($b['d']) / 95))) ?>"><?= h($val) ?></textarea><?php else: ?><input type="text" name="c[<?= h($id) ?>]" value="<?= h($val) ?>"><?php endif; ?></label></div>
<?php endforeach; ?></div>
<?php endforeach; ?>
<div class="savebar" id="sbar"><button class="btn gold" type="submit"><?= ico('shield') ?>Save changes</button>
<button class="btn ghost" type="submit" name="action" value="content_reset" onclick="return confirm('Restore the original text on this whole page?')">Restore page to original</button><span class="dirty">● Unsaved changes</span></div>
</form>
