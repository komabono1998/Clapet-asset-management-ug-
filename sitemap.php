<?php
declare(strict_types=1);
header('Content-Type: application/xml; charset=utf-8');
$base = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$pages = ['index' => 1.0, 'about' => .8, 'services' => .9, 'property-management' => .8, 'construction-supervision' => .8, 'construction-consultancy' => .8,
          'land-verification' => .8, 'farm-management' => .8, 'supplies-tendering' => .8, 'projects' => .7, 'properties' => .7, 'partners' => .6,
          'resources' => .6, 'contact' => .9, 'legal' => .3];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $p => $prio) {
    $f = __DIR__ . "/$p.php";
    echo "  <url><loc>$base/" . ($p === 'index' ? '' : "$p.php") . "</loc><lastmod>" . date('Y-m-d', (int)@filemtime($f)) . "</lastmod><priority>$prio</priority></url>\n";
}
echo "</urlset>\n";
