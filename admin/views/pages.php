<?php defined('CLAPET_ADMIN') || exit; ?><p class="hint" style="margin-top:0">Choose a page to edit. Every heading, paragraph, button, menu item and search-engine description on the public website can be changed here. Lists such as projects, listings, tenders, testimonials and FAQs have their own editors in the sidebar.</p>
<?= page_cards($PAGES, $cmap, $ovAll) ?>
<div class="dlbar"><span>Back up all website text</span>
<?php foreach (['json', 'xlsx', 'csv', 'docx'] as $f): ?><a class="btn sm ghost" href="download.php?type=text&amp;fmt=<?= $f ?>"><?= ico('down') ?><?= h(EXPORT_FORMATS[$f][0]) ?></a><?php endforeach; ?></div>
