<?php defined('CLAPET_ADMIN') || exit; ?><?php
$sc = $schemas[$cn];
$items = coll_all($cn);
$id = (string)($_GET['id'] ?? '');
$edit = null;
if ($id === 'new') $edit = ['id' => '', 'published' => true];
elseif ($id !== '') foreach ($items as $it) if (($it['id'] ?? '') === $id) $edit = $it;
?>
<?php if ($edit !== null): ?>
<div class="crumb"><a href="?p=coll&amp;c=<?= h($cn) ?>"><?= h($sc['label']) ?></a><span>/</span><b><?= $edit['id'] === '' ? 'New ' . h($sc['single']) : 'Edit ' . h($sc['single']) ?></b></div>
<form method="post" enctype="multipart/form-data" class="card">
<input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="coll_save"><input type="hidden" name="c" value="<?= h($cn) ?>"><input type="hidden" name="id" value="<?= h($edit['id']) ?>">
<div class="g2">
<?php foreach ($sc['fields'] as $f): [$key, $label, $ftype] = $f; $val = (string)($edit[$key] ?? ''); $wide = $ftype === 'textarea' || $ftype === 'image'; ?>
<label class="<?= $wide ? 'full' : '' ?>"><?= h($label) ?><?= !empty($f[3]) && $ftype !== 'select' ? ' <small>(required)</small>' : '' ?>
<?php if ($ftype === 'textarea'): ?><textarea name="f_<?= h($key) ?>" rows="4"><?= h($val) ?></textarea>
<?php elseif ($ftype === 'select'): ?><select name="f_<?= h($key) ?>"><?php foreach ($f[3] as $o): ?><option<?= $o === $val ? ' selected' : '' ?>><?= h($o) ?></option><?php endforeach; ?></select>
<?php elseif ($ftype === 'date'): ?><input type="date" name="f_<?= h($key) ?>" value="<?= h($val) ?>">
<?php elseif ($ftype === 'image'): ?><span class="imgfield"><?php if ($val !== '' && is_file("$site/$val")): ?><img src="../<?= h($val) ?>" alt=""><label class="inl"><input type="checkbox" name="rm_<?= h($key) ?>"> Remove photo</label><?php endif; ?><input type="file" name="f_<?= h($key) ?>" accept="image/jpeg,image/png,image/webp"></span>
<?php else: ?><input type="text" name="f_<?= h($key) ?>" value="<?= h($val) ?>"<?= !empty($f[3]) ? ' required' : '' ?>><?php endif; ?></label>
<?php endforeach; ?></div>
<label class="pub"><input type="checkbox" name="published"<?= !empty($edit['published']) ? ' checked' : '' ?>> <b>Published</b> <small>Untick to keep it as a draft that visitors cannot see.</small></label>
<div class="btnrow"><button class="btn gold" type="submit"><?= ico('shield') ?>Save <?= h($sc['single']) ?></button><a class="btn ghost" href="?p=coll&amp;c=<?= h($cn) ?>">Cancel</a></div>
</form>
<?php else: ?>
<div class="toolbar"><a class="btn gold" href="?p=coll&amp;c=<?= h($cn) ?>&amp;id=new"><?= ico('plus') ?>Add <?= h($sc['single']) ?></a>
<a class="btn sm ghost" href="../<?= h($sc['page']) ?>.php" target="_blank"><?= ico('eye') ?>View on website</a>
<div class="dlmenu"><button type="button" class="btn sm ghost" data-dl><?= ico('down') ?>Download list</button><div class="dlpop" hidden><?php foreach (EXPORT_FORMATS as $f => $m): ?><a href="download.php?type=collection&amp;c=<?= h($cn) ?>&amp;fmt=<?= $f ?>"><?= h($m[0]) ?></a><?php endforeach; ?></div></div></div>
<div class="card">
<?php if (!$items): ?><div class="empty-big flat"><?= ico($sc['icon']) ?><h2>No <?= h(strtolower($sc['label'])) ?> yet</h2><p>Add your first <?= h($sc['single']) ?>. Published items appear on the website straight away; drafts stay hidden.</p><a class="btn gold" href="?p=coll&amp;c=<?= h($cn) ?>&amp;id=new"><?= ico('plus') ?>Add <?= h($sc['single']) ?></a></div>
<?php else: ?><div class="tbl"><table><tr><?php foreach ($sc['cols'] as $col): ?><th><?= h(ucfirst(str_replace('_', ' ', $col))) ?></th><?php endforeach; ?><th>Visible</th><th></th></tr>
<?php foreach ($items as $it): ?><tr><?php foreach ($sc['cols'] as $col): ?><td><?= $col === $sc['title'] ? '<b>' . h($it[$col] ?? '') . '</b>' : h(mb_strimwidth((string)($it[$col] ?? ''), 0, 70, '…')) ?></td><?php endforeach; ?>
<td><form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="coll_toggle"><input type="hidden" name="c" value="<?= h($cn) ?>"><input type="hidden" name="id" value="<?= h($it['id']) ?>"><button class="switch<?= !empty($it['published']) ? ' on' : '' ?>" title="Toggle visibility"><?= !empty($it['published']) ? 'Published' : 'Draft' ?></button></form></td>
<td class="acts"><a class="btn sm ghost" href="?p=coll&amp;c=<?= h($cn) ?>&amp;id=<?= h($it['id']) ?>"><?= ico('edit') ?>Edit</a>
<form method="post" onsubmit="return confirm('Delete this <?= h($sc['single']) ?>?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="coll_delete"><input type="hidden" name="c" value="<?= h($cn) ?>"><input type="hidden" name="id" value="<?= h($it['id']) ?>"><button class="btn sm danger" aria-label="Delete"><?= ico('trash') ?></button></form></td></tr><?php endforeach; ?></table></div><?php endif; ?></div>
<?php endif; ?>
