<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
$base = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /storage/\nDisallow: /inc/\nDisallow: /tools/\nDisallow: /submit.php\nDisallow: /track.php\n\nSitemap: $base/sitemap.xml\n";
