<?php defined('CLAPET_ADMIN') || exit; ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= $in ? h($titles[$page]) . ' | ' : '' ?>Clapet Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="icon" href="../assets/img/logo-icon-64.png" type="image/png">
<link rel="stylesheet" href="admin.css?v=<?= (int)@filemtime(__DIR__ . '/../admin.css') ?>">
</head><body>
<?php if (!$in): ?>
<main class="auth">
  <section class="auth-brand" aria-hidden="true">
    <svg class="topo" viewBox="0 0 800 600" fill="none" stroke="#D4AF37" stroke-width="1"><?php for ($i = 0; $i < 9; $i++): $r = 60 + $i * 38; ?><ellipse cx="400" cy="300" rx="<?= $r * 1.35 ?>" ry="<?= $r ?>" transform="rotate(<?= $i * 7 ?> 400 300)"/><?php endfor; ?></svg>
    <div class="logo"><img class="mark" src="../assets/img/logo-icon-64.png" width="38" height="38" alt=""><div><b>CLAPET ASSET MANAGEMENT</b><small>UG - SMC · THE STANDARD OF TRUST.</small></div></div>
    <h2>Run your website from one secure place.</h2>
    <ul>
      <li><?= ico('edit') ?><span><b>Edit every word</b> on every page, instantly live</span></li>
      <li><?= ico('chart') ?><span><b>Track visitors</b> and see which pages bring enquiries</span></li>
      <li><?= ico('down') ?><span><b>Download</b> any form in CSV, Excel, PDF, Word and more</span></li>
      <li><?= ico('shield') ?><span><b>Private by design</b>, with login protection and no cookies for visitors</span></li>
    </ul>
    <p class="fine">© <?= date('Y') ?> CLAPET ASSET MANAGEMENT UG - SMC</p>
  </section>
  <section class="auth-form">
    <form method="post" class="authcard" autocomplete="on">
      <div class="lock"><?= ico('shield') ?></div>
      <h1>Welcome back</h1>
      <p class="sub">Sign in to manage the Clapet website.</p>
      <?php if ($err): ?><div class="er" role="alert"><?= h($err) ?></div><?php endif; ?>
      <?php if (!$admin): ?><div class="er">No admin account yet. Run <code>php admin/setup.php &lt;email&gt; &lt;password&gt;</code> on the server.</div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="login">
      <label for="u">Email address<input id="u" type="text" name="user" required autocomplete="username" autofocus placeholder="you@company.com"></label>
      <label for="pw">Password<span class="pwrap"><input id="pw" type="password" name="pass" required autocomplete="current-password" placeholder="Enter your password"><button type="button" class="eye" aria-label="Show password" onclick="var i=document.getElementById('pw');i.type=i.type==='password'?'text':'password'"><?= ico('eye') ?></button></span></label>
      <button class="btn gold block" type="submit">Sign in</button>
      <p class="links"><a href="../index.php"><?= ico('eye') ?>View the website</a></p>
      <p class="fine2">Protected area. Failed sign-ins are limited and logged.</p>
    </form>
  </section>
</main>
<?php else: ?>
<div class="shell">
<aside class="side" id="side">
<div class="logo"><img class="mark" src="../assets/img/logo-icon-64.png" width="38" height="38" alt=""><div><b>CLAPET ADMIN</b><small>THE STANDARD OF TRUST.</small></div></div>
<nav aria-label="Admin">
<div class="grp-t">Overview</div>
<?= nav_item('?p=dashboard', 'Dashboard', 'home', $page === 'dashboard') ?>
<?= nav_item('?p=visitors', 'Visitor analytics', 'chart', $page === 'visitors') ?>
<div class="grp-t">Website content</div>
<details class="grp"<?= in_array($page, ['pages', 'content'], true) ? ' open' : '' ?>><summary<?= $page === 'pages' ? ' aria-current="page"' : '' ?>><?= ico('edit') ?><span>Website text</span><em class="chev">▾</em></summary>
<div class="sub"><a href="?p=pages"<?= $page === 'pages' ? ' aria-current="page"' : '' ?>>All pages</a>
<?php foreach ($PAGES as $k => $lbl): if (!isset($cmap[$k])) continue; ?><a href="?p=content&amp;pg=<?= h($k) ?>"<?= ($page === 'content' && $pg === $k) ? ' aria-current="page"' : '' ?>><?= h($lbl) ?><?= !empty($ovAll[$k]) ? '<i>' . count($ovAll[$k]) . '</i>' : '' ?></a><?php endforeach; ?></div></details>
<?= nav_item('?p=images', 'Photos', 'img', $page === 'images') ?>
<?php foreach ($schemas as $k => $sc): $cnt = count(coll_all($k)); ?><?= nav_item('?p=coll&c=' . $k, $sc['label'], $sc['icon'], $page === 'coll' && $cn === $k, $cnt ?: null) ?><?php endforeach; ?>
<div class="grp-t">Inbox</div>
<?= nav_item('?p=enquiries', 'Enquiries', 'mail', $page === 'enquiries', $newCount) ?>
<?= nav_item('?p=forms', 'Forms & downloads', 'down', $page === 'forms') ?>
<div class="grp-t">Settings</div>
<?= nav_item('?p=settings', 'Contact & credentials', 'cog', $page === 'settings') ?>
<?= nav_item('?p=account', 'Account', 'user', $page === 'account') ?>
</nav>
<div class="foot"><a href="../index.php" target="_blank"><?= ico('eye') ?>View website</a>
<form method="post"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="logout"><button><?= ico('out') ?>Sign out</button></form></div>
</aside>
<div class="main">
<div class="top"><div><h1><?= h($titles[$page]) ?></h1></div>
<div class="who"><button type="button" class="kbd" id="cmdk" aria-label="Quick jump"><?= ico('search') ?><span>Quick jump</span><kbd>Ctrl K</kbd></button>
<a class="btn sm ghost" href="../index.php" target="_blank"><?= ico('eye') ?>View site</a><span class="av" title="<?= h($admin['user'] ?? '') ?>"><?= h(strtoupper(substr((string)($admin['user'] ?? 'A'), 0, 1))) ?></span></div></div>
<div class="work">
<?php if ($msg): ?><div class="ok" role="status"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="er" role="alert"><?= h($err) ?></div><?php endif; ?>
<?php require __DIR__ . '/' . $page . '.php'; ?>
</div></div></div>
<div class="palette" id="palette" hidden><div class="pbox" role="dialog" aria-label="Quick jump"><input id="pq" type="search" placeholder="Jump to a page, list or setting…" autocomplete="off"><ul id="pl"></ul></div></div>
<script>window.JUMP=<?= json_encode($jump, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="admin.js?v=<?= (int)@filemtime(__DIR__ . '/../admin.js') ?>"></script>
<?php endif; ?>
</body></html>
