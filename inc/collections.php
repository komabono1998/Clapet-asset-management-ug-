<?php
/**
 * Editable content collections (listings, projects, testimonials, tenders, FAQs):
 * schema for the admin editor, storage helpers and the public-site renderers.
 */
declare(strict_types=1);

function coll_schemas(): array {
    $verif = ['Documents Received', 'Checks in Progress', 'Checks Completed Within Scope', 'Further Verification Required'];
    return [
        'listings' => [
            'label' => 'Land & property listings', 'single' => 'listing', 'icon' => 'map', 'page' => 'properties',
            'title' => 'title', 'cols' => ['ref', 'title', 'location', 'verification', 'availability'],
            'fields' => [
                ['title', 'Title', 'text', 1], ['ref', 'Listing reference', 'text'], ['type', 'Property type', 'select', ['Land', 'Residential', 'Commercial', 'Agricultural']],
                ['location', 'General location', 'text', 1], ['size', 'Size / specifications', 'text'], ['price', 'Asking price (if authorised)', 'text'],
                ['use', 'Intended use', 'select', ['Residential', 'Commercial', 'Farming']], ['access', 'Access & utilities', 'text'],
                ['verification', 'Verification status', 'select', $verif], ['verified_date', 'Verification date', 'date'],
                ['availability', 'Availability', 'select', ['Available', 'Under offer', 'Sold']],
                ['description', 'Description', 'textarea'], ['image', 'Photograph', 'image'],
            ],
        ],
        'projects' => [
            'label' => 'Projects & portfolio', 'single' => 'project', 'icon' => 'crane', 'page' => 'projects',
            'title' => 'title', 'cols' => ['title', 'category', 'location', 'status', 'year'],
            'fields' => [
                ['title', 'Project title', 'text', 1],
                ['category', 'Category', 'select', ['Property management', 'Construction and renovation', 'Site supervision', 'Land verification and brokerage', 'Farm management', 'General supplies and procurement']],
                ['location', 'General location', 'text'], ['status', 'Status', 'select', ['Planned', 'Ongoing', 'Completed', 'On Hold']], ['year', 'Year', 'text'],
                ['scope', 'Scope of work', 'textarea'], ['outcome', 'Verified outcome', 'textarea'], ['image', 'Approved photograph', 'image'],
            ],
        ],
        'testimonials' => [
            'label' => 'Client testimonials', 'single' => 'testimonial', 'icon' => 'users', 'page' => 'index',
            'title' => 'name', 'cols' => ['name', 'organisation', 'service'],
            'fields' => [
                ['name', 'Client name (approved)', 'text', 1], ['organisation', 'Organisation', 'text'], ['service', 'Service received', 'text'],
                ['quote', 'Testimonial (with client permission)', 'textarea', 1], ['image', 'Photograph or logo', 'image'],
            ],
        ],
        'tenders' => [
            'label' => 'Tender opportunities', 'single' => 'tender', 'icon' => 'box', 'page' => 'supplies-tendering',
            'title' => 'title', 'cols' => ['reference', 'title', 'entity', 'deadline', 'status'],
            'fields' => [
                ['title', 'Tender title', 'text', 1], ['reference', 'Tender reference', 'text'], ['entity', 'Procuring entity', 'text'], ['category', 'Category', 'text'],
                ['deadline', 'Submission deadline', 'date'], ['status', 'Status', 'select', ['Open', 'Upcoming', 'Closed', 'Awarded']], ['notes', 'Requirements / notes', 'textarea'],
            ],
        ],
        'faqs' => [
            'label' => 'FAQs', 'single' => 'FAQ', 'icon' => 'doc', 'page' => 'resources',
            'title' => 'question', 'cols' => ['question', 'order'],
            'fields' => [['question', 'Question', 'text', 1], ['answer', 'Answer', 'textarea', 1], ['order', 'Display order', 'text']],
        ],
    ];
}

function coll_file(string $name): string { return dirname(__DIR__) . '/storage/collections/' . preg_replace('/[^a-z]/', '', $name) . '.json'; }

function coll_all(string $name): array {
    $f = coll_file($name);
    if (is_file($f)) return json_decode((string)file_get_contents($f), true) ?: [];
    if ($name === 'faqs') {                                  // seed with the original FAQs from the build
        $d = json_decode((string)@file_get_contents(dirname(__DIR__) . '/inc/faq-defaults.json'), true) ?: [];
        foreach ($d as $i => &$x) { $x['id'] = 'f' . ($i + 1); $x['order'] = (string)($i + 1); $x['published'] = true; }
        return $d;
    }
    return [];
}

function coll_save(string $name, array $items): void {
    @mkdir(dirname(coll_file($name)), 0755, true);
    file_put_contents(coll_file($name), json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function coll_published(string $name): array {
    $items = array_values(array_filter(coll_all($name), fn($i) => !empty($i['published'])));
    if ($name === 'faqs') usort($items, fn($a, $b) => (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));
    if ($name === 'tenders') usort($items, fn($a, $b) => strcmp((string)($a['deadline'] ?? ''), (string)($b['deadline'] ?? '')));
    return $items;
}

/* ================================================================ public renderers */
function r_img(array $i, string $fallback = ''): string {
    if (!empty($i['image']) && is_file(dirname(__DIR__) . '/' . $i['image'])) {
        return '<img class="photo" src="' . h($i['image']) . '" alt="' . h($i['title'] ?? $i['name'] ?? '') . '" loading="lazy">';
    }
    return $fallback;
}

function render_listings(): string {
    $items = coll_published('listings');
    $g = fn(string $k): string => trim((string)($_GET[$k] ?? ''));
    $items = array_values(array_filter($items, function ($i) use ($g) {
        if ($g('loc') !== '' && stripos((string)($i['location'] ?? ''), $g('loc')) === false) return false;
        if ($g('type') !== '' && ($i['type'] ?? '') !== $g('type')) return false;
        if ($g('use') !== '' && ($i['use'] ?? '') !== $g('use')) return false;
        if ($g('ver') !== '' && ($i['verification'] ?? '') !== $g('ver')) return false;
        if ($g('avail') !== '' && ($i['availability'] ?? '') !== $g('avail')) return false;
        return true;
    }));
    if (!$items) {
        return '<div class="empty"><h3>' . (count(coll_published('listings')) ? 'No listings match your search.' : 'Verified listings are published after review.') . '</h3><p>Listings remain unpublished until an authorised staff member confirms the right to market the property. Tell us what you are looking for and we will search on your behalf.</p><div class="btn-row" style="justify-content:center"><a class="btn btn-gold" href="contact.php?service=land-verification#brief">Tell us what you need →</a></div></div>';
    }
    $o = '<div class="grid g3">';
    foreach ($items as $i) {
        $img = r_img($i, '<div style="position:absolute;inset:0;background:linear-gradient(135deg,#1c3358,#0A192F)"></div>');
        $o .= '<article class="listing rv in"><div class="ph">' . $img . '</div><div class="body"><span class="chip gold">' . h($i['verification'] ?? '') . '</span>'
            . '<h3 style="margin-top:12px">' . h($i['title'] ?? '') . '</h3>'
            . '<dl><dt>Reference</dt><dd>' . h($i['ref'] ?? '') . '</dd><dt>Location</dt><dd>' . h($i['location'] ?? '') . '</dd><dt>Size</dt><dd>' . h($i['size'] ?? '') . '</dd>'
            . (($i['price'] ?? '') !== '' ? '<dt>Price</dt><dd>' . h($i['price']) . '</dd>' : '')
            . '<dt>Use</dt><dd>' . h($i['use'] ?? '') . '</dd><dt>Status</dt><dd>' . h($i['availability'] ?? '') . '</dd>'
            . (($i['verified_date'] ?? '') !== '' ? '<dt>Verified</dt><dd>' . h($i['verified_date']) . '</dd>' : '') . '</dl>'
            . (($i['description'] ?? '') !== '' ? '<p style="font-size:.88rem;color:var(--muted)">' . h($i['description']) . '</p>' : '')
            . '<a class="btn btn-navy btn-sm" href="contact.php?service=land-verification&amp;location=' . urlencode((string)($i['location'] ?? '')) . '#brief">Request a viewing</a></div></article>';
    }
    return $o . '</div>';
}

function render_projects(): string {
    $all = coll_published('projects');
    $g = fn(string $k): string => trim((string)($_GET[$k] ?? ''));
    $items = array_values(array_filter($all, function ($i) use ($g) {
        if ($g('cat') !== '' && ($i['category'] ?? '') !== $g('cat')) return false;
        if ($g('status') !== '' && ($i['status'] ?? '') !== $g('status')) return false;
        if ($g('loc') !== '' && stripos((string)($i['location'] ?? ''), $g('loc')) === false) return false;
        if ($g('year') !== '' && ($i['year'] ?? '') !== $g('year')) return false;
        return true;
    }));
    if (!$items) {
        return '<div class="empty"><h3>' . ($all ? 'No projects match your filters.' : 'Our first portfolio entries are being prepared.') . '</h3><p>Each entry shows the title, category, general location, status, scope, approved photographs, timeline and documented results, and is approved before publication.</p><div class="btn-row" style="justify-content:center"><a class="btn btn-gold" href="contact.php#brief">Discuss your project →</a></div></div>';
    }
    $o = '<div class="grid g3">';
    foreach ($items as $i) {
        $img = r_img($i, '<div style="position:absolute;inset:0;background:linear-gradient(135deg,#1c3358,#0A192F)"></div>');
        $o .= '<article class="pcard rv in"><div class="pimg">' . $img . '<span class="badge">' . h($i['status'] ?? '') . '</span></div><div class="pbody"><h3>' . h($i['title'] ?? '') . '</h3>'
            . '<div class="pmeta"><span>' . h($i['category'] ?? '') . '</span><span>' . h($i['location'] ?? '') . '</span></div>'
            . (($i['scope'] ?? '') !== '' ? '<p>' . h($i['scope']) . '</p>' : '')
            . (($i['outcome'] ?? '') !== '' ? '<p><b>Outcome:</b> ' . h($i['outcome']) . '</p>' : '')
            . '<div class="pfoot"><span class="from">' . h($i['year'] ?? '') . '</span><a class="pill" href="contact.php#brief">Similar project →</a></div></div></article>';
    }
    return $o . '</div>';
}

function render_testimonials(): string {
    $items = coll_published('testimonials');
    if (!$items) return '';
    $o = '<section class="alt"><div class="wrap"><div class="sec-head rv center"><span class="eyebrow">Client feedback</span><h2>What Our Clients Say</h2></div><div class="grid g3">';
    foreach ($items as $i) {
        $o .= '<figure class="card rv in" style="margin:0"><blockquote style="margin:0 0 16px;font-family:var(--font-primary);font-weight:600;color:var(--clapet-navy)">“' . h($i['quote'] ?? '') . '”</blockquote><figcaption style="font-size:.88rem;color:var(--muted)"><b style="color:var(--clapet-navy)">' . h($i['name'] ?? '') . '</b>'
            . (($i['organisation'] ?? '') !== '' ? ' · ' . h($i['organisation']) : '') . (($i['service'] ?? '') !== '' ? '<br>' . h($i['service']) : '') . '</figcaption></figure>';
    }
    return $o . '</div></div></section>';
}

function render_tenders(): string {
    $items = coll_published('tenders');
    if (!$items) return '<p>Tender opportunities and upcoming deadlines are published here as they are verified and approved internally. No bid is submitted without internal approval.</p>';
    $o = '<div class="tbl" style="overflow-x:auto"><table class="tbl"><tr><th>Reference</th><th>Tender</th><th>Entity</th><th>Deadline</th><th>Status</th></tr>';
    foreach ($items as $i) {
        $o .= '<tr><td>' . h($i['reference'] ?? '') . '</td><td>' . h($i['title'] ?? '') . (($i['notes'] ?? '') !== '' ? '<br><small style="color:var(--muted)">' . h($i['notes']) . '</small>' : '') . '</td><td>' . h($i['entity'] ?? '') . '</td><td>' . h($i['deadline'] ?? '') . '</td><td><span class="chip">' . h($i['status'] ?? '') . '</span></td></tr>';
    }
    return $o . '</table></div>';
}

function render_faqs(int $limit = 0): string {
    $items = coll_published('faqs');
    if ($limit > 0) $items = array_slice($items, 0, $limit);
    $o = '<div class="faq">';
    foreach ($items as $i) $o .= '<details><summary>' . h($i['question'] ?? '') . '</summary><p>' . h($i['answer'] ?? '') . '</p></details>';
    return $o . '</div>';
}
