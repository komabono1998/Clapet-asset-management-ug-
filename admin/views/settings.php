<?php defined('CLAPET_ADMIN') || exit; ?><form method="post" class="card"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save_settings">
<h2>Contact details</h2><p class="hint">These appear in the top bar, footer, contact page, WhatsApp button and enquiry notifications.</p>
<div class="g2">
<label>Telephone<input type="text" name="phone" value="<?= h($S['phone']) ?>" required></label>
<label>Email <small>(also receives enquiry notifications)</small><input type="email" name="email" value="<?= h($S['email']) ?>" required></label>
<label>WhatsApp number <small>(with country code)</small><input type="text" name="wa" value="<?= h($S['wa']) ?>" required></label>
<label>Office address<input type="text" name="address" value="<?= h($S['address']) ?>"></label>
<label>Business hours<input type="text" name="hours" value="<?= h($S['hours']) ?>"></label>
<label>Managing Director name<input type="text" name="md" value="<?= h($S['md']) ?>"></label></div>
<label class="chk"><input type="checkbox" name="notify"<?= !empty($S['notify']) ? ' checked' : '' ?>> Email me when a form is submitted <small>(needs mail configured on the server; submissions are always saved in Enquiries)</small></label>
<h2 id="credentials" style="margin-top:10px">Credentials ticker</h2>
<p class="hint">Tick a credential only when it is verified and certificates are available. Unticked items are never shown on the website.</p>
<?php foreach ($S['credentials'] as $i => $c): ?><div class="cred"><input type="text" name="cred_label[<?= $i ?>]" value="<?= h($c['label']) ?>"><label><input type="checkbox" name="cred_ok[<?= $i ?>]" <?= !empty($c['verified']) ? 'checked' : '' ?>> Verified</label></div><?php endforeach; ?>
<div class="cred"><input type="text" name="cred_label[90]" placeholder="Add another credential (optional)"><label><input type="checkbox" name="cred_ok[90]"> Verified</label></div>
<label>Always-shown ticker lines <small>(one per line)</small><textarea name="ticker_extra" rows="3"><?= h(implode("\n", $S['ticker_extra'])) ?></textarea></label>
<button class="btn gold"><?= ico('shield') ?>Save details</button></form>
