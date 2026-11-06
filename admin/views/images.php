<?php defined('CLAPET_ADMIN') || exit; ?><div class="card"><p style="margin:0;color:var(--muted)">Replace any website photo with your own (JPG, PNG or WebP, up to 6 MB). Clear the credit when you upload your own photography. Default photos are Creative Commons images from Wikimedia Commons and show their credit on the page.</p></div>
<?php foreach ($slots as $slot => $label): $im = slot_image($slot); ?>
<form method="post" enctype="multipart/form-data" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="image_save"><input type="hidden" name="slot" value="<?= h($slot) ?>">
<h2><?= h($label) ?></h2><div class="slot"><div><?php if ($im): ?><img src="../<?= h($im['file']) ?>" alt=""><?php else: ?><div class="ph"></div><?php endif; ?></div>
<div><label>Upload a new photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
<label>Description (alt text)<input type="text" name="alt" value="<?= h($im['alt'] ?? '') ?>"></label>
<label>Photo credit (shown on image)<input type="text" name="credit" value="<?= h($im['credit'] ?? '') ?>"></label>
<button class="btn sm gold">Save</button> <button class="btn sm ghost" name="action" value="image_reset" formnovalidate>Restore default</button></div></div></form>
<?php endforeach; ?>
