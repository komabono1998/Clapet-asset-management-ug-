#!/usr/bin/env python3
"""Static site builder for CLAPET ASSET MANAGEMENT UG - SMC.
Run:  python tools/build.py   (writes the .html pages into the project root)
Edit CONFIG below to set the confirmed company contact details.
"""
import os, html, math, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# ---------------------------------------------------------------- CONFIG
def P(k):
    return '<?=h($S["%s"])?>' % k

# Contact details are NOT hard-coded: they come from inc/settings.php + storage/settings.json,
# editable in /admin. Pages are emitted as .php so changes go live instantly.
CONFIG = dict(
    name="CLAPET ASSET MANAGEMENT",
    suffix="UG - SMC",
    full="CLAPET ASSET MANAGEMENT UG - SMC",
    motto="The Standard of Trust.",
    phone=P("phone"),
    phone_raw='<?=h(preg_replace("/[^0-9+]/","",$S["phone"]))?>',
    email=P("email"),
    wa_number='<?=h(preg_replace("/[^0-9]/","",$S["wa"]))?>',
    address=P("address"),
    hours=P("hours"),
    md=P("md"),
)
WA_URL = "https://wa.me/%s?text=Hello%%20Clapet%%20Team%%2C%%20I%%20would%%20like%%20to%%20discuss%%20a%%20project." % CONFIG["wa_number"]

# ---------------------------------------------------------------- ICONS
ICONS = {
 "building": '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5M8 9h2M14 9h2M8 13h2M14 13h2"/>',
 "eye": '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
 "crane": '<path d="M3 21h18M6 21V8l12-4M6 8l-3 4M18 4v6M18 10v4"/><path d="M16 14h4v3h-4z"/>',
 "map": '<path d="M12 22s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.6"/>',
 "leaf": '<path d="M5 19C5 9 11 4 20 4c0 9-5 15-15 15z"/><path d="M5 19c2-5 5-8 9-10"/>',
 "box": '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/>',
 "shield": '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/>',
 "doc": '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/>',
 "chart": '<path d="M4 20V4M4 20h16"/><path d="M8 16v-4M12 16V8M16 16v-6"/>',
 "users": '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M16 14.3c3 .2 5 2.3 5 5.7"/>',
 "key": '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3M14 9l2 2"/>',
 "lock": '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
 "phone": '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
 "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 "clock": '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 "road": '<path d="M8 3L4 21M16 3l4 18M12 4v3M12 10v4M12 17v3"/>',
 "book": '<path d="M4 5a2 2 0 012-2h13v16H6a2 2 0 00-2 2V5z"/><path d="M4 19a2 2 0 012-2h13"/>',
 "laptop": '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 20h20"/>',
 "wheat": '<path d="M12 21V8M12 8c-2-1-3-3-3-5 2 1 3 3 3 5zM12 8c2-1 3-3 3-5-2 1-3 3-3 5zM12 14c-2-1-3-3-3-5 2 1 3 3 3 5zM12 14c2-1 3-3 3-5-2 1-3 3-3 5z"/>',
 "handshake": '<path d="M2 12l5-5 4 2 4-2 5 5-5 5-3-2-3 2-4-2-3-3z"/><path d="M9 14l3 2"/>',
}

def icon(name):
    return '<span class="icon"><svg viewBox="0 0 24 24" aria-hidden="true">%s</svg></span>' % ICONS[name]

# ---------------------------------------------------------------- ART (no stock photos: vector scenes)
def topo(n=9, seed=0):
    paths = []
    for i in range(n):
        r = 60 + i * 38
        pts = []
        for a in range(0, 361, 12):
            rad = math.radians(a)
            w = 1 + 0.12 * math.sin(rad * 3 + seed + i * .5) + 0.07 * math.cos(rad * 5 + i)
            pts.append((400 + r * 1.35 * w * math.cos(rad), 300 + r * w * math.sin(rad)))
        d = "M" + " L".join("%.1f %.1f" % p for p in pts) + "Z"
        paths.append('<path d="%s"/>' % d)
    return ('<svg class="topo" viewBox="0 0 800 600" fill="none" stroke="#D4AF37" stroke-width="1" aria-hidden="true">%s</svg>'
            % "".join(paths))

def logo_mark():
    return ('<svg class="brand-mark" viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="11" fill="#0A192F"/>'
            '<path d="M31.5 16.5A10.5 10.5 0 1031.5 31.5" fill="none" stroke="#D4AF37" stroke-width="4.2" stroke-linecap="round"/>'
            '<path d="M24 14.5v-3M24 36.5v-3M15 24h-3" stroke="#D4AF37" stroke-width="1.6" stroke-linecap="round" opacity=".55"/>'
            '<rect x="22" y="21" width="4.2" height="6" fill="#fff"/></svg>')

def scene(kind="building", state="after", label=""):
    """Illustrative vector scenes in brand colours (placeholders for authentic site photography)."""
    sky = '<defs><linearGradient id="sk%s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0A192F"/><stop offset="1" stop-color="#1f3b66"/></linearGradient></defs>' % (kind + state)
    base = '<rect width="800" height="640" fill="url(#sk%s)"/><circle cx="640" cy="150" r="54" fill="#D4AF37" opacity=".9"/><circle cx="640" cy="150" r="86" fill="none" stroke="#D4AF37" opacity=".25"/>' % (kind + state)
    hills = '<path d="M0 470 C160 400 280 430 400 410 S640 380 800 430 V640 H0Z" fill="#12294a"/><path d="M0 520 C200 470 360 520 520 490 S720 480 800 500 V640 H0Z" fill="#0d213c"/>'
    g = ""
    if kind == "building":
        if state == "before":
            g = ('<rect x="230" y="270" width="330" height="250" fill="#2c3b52"/><path d="M230 270l50-30 100 18 90-25 90 22v15z" fill="#1d2a3d"/>'
                 '<path d="M270 320h40v50h-40zM420 330h50v40h-50zM330 420h60v100h-60z" fill="#0c1627"/>'
                 '<path d="M240 300l60 40M500 280l-40 60M360 280l20 50" stroke="#0c1627" stroke-width="3"/>'
                 '<path d="M200 520h400" stroke="#0c1627" stroke-width="6"/><path d="M215 330l16 60M560 350l10 80" stroke="#546178" stroke-width="3"/>')
        else:
            g = ('<rect x="220" y="230" width="360" height="290" fill="#e7ecf4"/><rect x="200" y="214" width="400" height="26" fill="#D4AF37"/>'
                 '<g fill="#0A192F"><rect x="250" y="270" width="52" height="64"/><rect x="374" y="270" width="52" height="64"/><rect x="498" y="270" width="52" height="64"/>'
                 '<rect x="250" y="370" width="52" height="64"/><rect x="498" y="370" width="52" height="64"/></g>'
                 '<g fill="#8fb4e6" opacity=".55"><rect x="256" y="276" width="40" height="52"/><rect x="380" y="276" width="40" height="52"/><rect x="504" y="276" width="40" height="52"/></g>'
                 '<rect x="360" y="410" width="80" height="110" fill="#0A192F"/><rect x="368" y="418" width="64" height="102" fill="#D4AF37" opacity=".85"/>'
                 '<path d="M180 520h440" stroke="#D4AF37" stroke-width="6"/><circle cx="170" cy="470" r="24" fill="#2f6b4f"/><circle cx="640" cy="478" r="30" fill="#2f6b4f"/>')
    elif kind == "construction":
        g = ('<path d="M120 520h560" stroke="#D4AF37" stroke-width="5"/><g stroke="#D4AF37" stroke-width="4" fill="none"><path d="M180 520V150M180 150h330M180 190l70-40M180 210h70M180 260h70M250 150v70"/>'
             '<path d="M420 150v110"/></g><rect x="398" y="260" width="44" height="30" fill="#D4AF37"/>'
             '<rect x="380" y="360" width="230" height="160" fill="#2c3b52"/><g stroke="#546178" stroke-width="2"><path d="M380 400h230M380 440h230M380 480h230M420 360v160M480 360v160M540 360v160"/></g>'
             '<rect x="330" y="470" width="50" height="50" fill="#d8c07a"/>')
    elif kind == "land":
        g = ('<path d="M120 540L400 400l300 130-300 60z" fill="#355f46"/><path d="M120 540L400 400l300 130" fill="none" stroke="#D4AF37" stroke-width="3" stroke-dasharray="10 8"/>'
             '<g fill="#D4AF37"><circle cx="120" cy="540" r="8"/><circle cx="400" cy="400" r="8"/><circle cx="700" cy="530" r="8"/><circle cx="400" cy="590" r="8"/></g>'
             '<path d="M400 400V300M400 300l40 14-40 14" stroke="#fff" stroke-width="3" fill="#D4AF37"/>')
    elif kind == "farm":
        g = ''.join('<path d="M-40 %d Q400 %d 840 %d" fill="none" stroke="#4d8a63" stroke-width="14" opacity=".7"/>' % (500 + i * 28, 470 + i * 28, 500 + i * 28) for i in range(5))
        g += '<g fill="#D4AF37"><rect x="120" y="360" width="110" height="80"/><path d="M110 360l65-45 65 45z" fill="#b8932a"/></g><g stroke="#2f6b4f" stroke-width="6"><path d="M560 470v-60M590 480v-80M620 470v-70"/></g>'
    elif kind == "supply":
        g = ('<g><rect x="170" y="400" width="180" height="120" fill="#c89f3a"/><rect x="350" y="360" width="210" height="160" fill="#d8b45a"/><rect x="560" y="420" width="120" height="100" fill="#b8932a"/></g>'
             '<g stroke="#0A192F" stroke-width="3" opacity=".6"><path d="M170 460h180M260 400v120M350 440h210M455 360v160M560 470h120"/></g>'
             '<rect x="200" y="280" width="190" height="90" rx="6" fill="#e7ecf4"/><circle cx="240" cy="380" r="16" fill="#0A192F"/><circle cx="350" cy="380" r="16" fill="#0A192F"/>')
    elif kind == "property":
        g = ('<g fill="#e7ecf4"><rect x="150" y="320" width="200" height="200"/><rect x="380" y="270" width="150" height="250"/><rect x="560" y="360" width="110" height="160"/></g>'
             '<g fill="#0A192F"><rect x="175" y="350" width="40" height="50"/><rect x="285" y="350" width="40" height="50"/><rect x="410" y="300" width="40" height="55"/><rect x="470" y="300" width="40" height="55"/><rect x="410" y="400" width="40" height="55"/><rect x="470" y="400" width="40" height="55"/></g>'
             '<path d="M130 320l120-60 120 60z" fill="#D4AF37"/>')
    tag = '<span class="tag">%s</span>' % html.escape(label) if label else ''
    return '<svg class="scene" viewBox="0 0 800 640" preserveAspectRatio="xMidYMid slice" role="img" aria-label="Illustration: %s">%s%s%s%s</svg>%s' % (
        html.escape(label or kind), sky, base, hills, g, tag)

def photo_or_scene(slot, kind, label):
    """Real photograph from an admin-editable slot, falling back to the vector illustration."""
    return ('<?php if ($im = slot_image("%s")): ?><img class="photo" src="<?=h($im["file"])?>" alt="<?=h($im["alt"])?>" loading="lazy">'
            '<?php if ($im["credit"] !== ""): ?><span class="tag"><?=h($im["credit"])?></span><?php endif; ?>'
            '<?php else: ?>%s<?php endif; ?>' % (slot, scene(kind, "after", label)))

def visual(kind, label, slot):
    return '<div class="visual rv">%s</div>' % photo_or_scene(slot, kind, label)

# ---------------------------------------------------------------- LAYOUT
SERVICES = [
 ("property-management", "Property Management", "building", "We coordinate rental operations, rent collection, tenant communication, inspections, maintenance, and owner reporting to help protect the condition and performance of your property."),
 ("construction-supervision", "Construction Site Supervision", "eye", "We provide agreed site visits, progress monitoring, photographic updates, materials delivery checks, and issue reporting for clients who are busy or live far from their construction sites."),
 ("construction-consultancy", "Construction Contracts & Consultancy", "crane", "We support construction and renovation projects through agreed execution, project planning, cost estimation, contractor coordination, and consultancy services within our verified capabilities."),
 ("land-verification", "Land Verification & Brokerage", "map", "We help clients coordinate land searches, document checks, site inspections, boundary verification, property enquiries, and acquisition support."),
 ("farm-management", "Farm Management & Land Searching", "leaf", "We support farm owners through operational oversight, input tracking, labour coordination, agricultural reporting, and assistance identifying suitable land for farming."),
 ("supplies-tendering", "General Supplies & Tendering", "box", "We coordinate sourcing, quotations, supply of goods, procurement documentation, supplier engagement, and delivery management for eligible requirements."),
]
SVC_SHORT = {
 "property-management": "Property Management", "construction-supervision": "Construction Supervision",
 "construction-consultancy": "Construction & Consultancy", "land-verification": "Land & Brokerage",
 "farm-management": "Farm Management", "supplies-tendering": "Supplies & Tendering"}

NAV = [("index.html", "Home"), ("about.html", "About Us"), ("services.html", "Our Services"),
       ("projects.html", "Projects"), ("properties.html", "Land & Properties"),
       ("supplies-tendering.html", "Supplies & Tenders"), ("resources.html", "Resources"), ("contact.html", "Contact Us")]

def header(active):
    items = []
    for href, label in NAV:
        if label == "Our Services":
            dd = "".join('<a href="%s.html"><i>%02d</i>%s</a>' % (s[0], i + 1, SVC_SHORT[s[0]]) for i, s in enumerate(SERVICES))
            cls = " active" if active.startswith("svc") else ""
            items.append('<div class="has-dd"><button class="dd%s" aria-haspopup="true" aria-expanded="false">Our Services ▾</button><div class="dd-menu">%s<a href="services.html"><i>＋</i>All services overview</a></div></div>' % (cls, dd))
        else:
            items.append('<a href="%s"%s>%s</a>' % (href, ' class="active"' if active == href else '', label))
    mob = '<a class="mob-only" href="portal.html">Client Portal</a><a class="mob-only" href="contact.html#brief" style="color:var(--clapet-gold-d)">Submit a Project Brief →</a>'
    return f'''<a class="skip" href="#main">Skip to content</a>
<div class="topbar"><div class="wrap"><span><span class="g">◆</span> {CONFIG["motto"]}</span><span><a href="tel:{CONFIG["phone_raw"]}">{CONFIG["phone"]}</a> &nbsp;·&nbsp; <a href="mailto:{CONFIG["email"]}">{CONFIG["email"]}</a> &nbsp;·&nbsp; <a href="portal.html">🔒 Client Portal</a></span></div></div>
<header class="site-header"><div class="wrap nav-wrap">
<a class="brand" href="index.html" aria-label="{CONFIG["full"]} — home">{logo_mark()}<span class="brand-text">{CONFIG["name"]}<small>{CONFIG["suffix"]}</small></span></a>
<nav class="nav" aria-label="Main">{"".join(items)}{mob}</nav>
<div class="nav-cta"><a class="btn btn-gold btn-sm" href="contact.html#brief">Submit a Project Brief →</a><button class="burger" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button></div>
</div></header>'''

def footer():
    svc = "".join('<li><a href="%s.html">%s</a></li>' % (s[0], SVC_SHORT[s[0]]) for s in SERVICES)
    return f'''<footer class="site-footer"><div class="wrap"><div class="foot-grid">
<div class="foot-brand"><a class="brand" href="index.html">{logo_mark()}<span class="brand-text">{CONFIG["name"]}<small>{CONFIG["suffix"]}</small></span></a>
<p>Property management, construction supervision, land verification, farm management, and general supplies.</p><div class="foot-motto">◆ {CONFIG["motto"]}</div></div>
<div><h4>Our Services</h4><ul>{svc}</ul></div>
<div><h4>Quick Links</h4><ul><li><a href="about.html">About Us</a></li><li><a href="projects.html">Projects</a></li><li><a href="properties.html">Land &amp; Properties</a></li><li><a href="partners.html">Partners</a></li><li><a href="resources.html">Resources</a></li><li><a href="contact.html">Contact Us</a></li><li><a href="portal.html">Client Portal</a></li><li><a href="legal.html">Privacy Policy</a></li></ul></div>
<div><h4>Contact</h4><ul><li>{CONFIG["address"]}</li><li><a href="tel:{CONFIG["phone_raw"]}">{CONFIG["phone"]}</a></li><li><a href="mailto:{CONFIG["email"]}">{CONFIG["email"]}</a></li><li>{CONFIG["hours"]}</li><li><a href="{WA_URL}" target="_blank" rel="noopener">WhatsApp us</a></li></ul></div>
</div><div class="foot-bottom"><span>© 2026 {CONFIG["full"]}. All rights reserved.</span><span><a href="legal.html#privacy">Privacy</a> · <a href="legal.html#terms">Terms</a> · <a href="legal.html#disclaimers">Disclaimers</a></span></div></div></footer>
<a class="wa-float" href="{WA_URL}" target="_blank" rel="noopener" aria-label="Chat with Clapet on WhatsApp"><svg viewBox="0 0 32 32"><path d="M16 3C8.8 3 3 8.7 3 15.8c0 2.5.7 4.8 2 6.8L3 29l6.6-2c1.9 1 4 1.6 6.4 1.6 7.2 0 13-5.7 13-12.8S23.200 3 16 3zm0 23.300c-2.100 0-4.100-.6-5.800-1.700l-.4-.2-3.900 1.200 1.200-3.800-.3-.4a10.300 10.300 0 01-1.700-5.600C5.100 9.900 10 5.300 16 5.300s10.900 4.600 10.900 10.500S22 26.300 16 26.300zm6-7.800c-.3-.2-1.900-.9-2.200-1-.3-.1-.5-.2-.7.200l-1 1.200c-.2.200-.4.200-.7.100-1.900-.9-3.100-1.700-4.300-3.800-.3-.6.300-.5.900-1.700.1-.2 0-.4 0-.6l-1-2.300c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.100-.9.400-.3.300-1.200 1.100-1.200 2.800s1.200 3.300 1.400 3.500c.2.200 2.400 3.700 5.800 5.100 2.200.9 3 .9 4 .8.6-.1 1.900-.8 2.200-1.600.3-.8.300-1.400.2-1.600-.1-.1-.3-.2-.6-.4z"/></svg></a>
<script src="assets/js/main.js" defer></script>'''

def page(fname, title, desc, active, body, hero=None):
    doc = f'''<?php require __DIR__ . '/inc/settings.php'; ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{html.escape(title)} | {CONFIG["full"]}</title>
<meta name="description" content="{html.escape(desc)}">
<meta name="theme-color" content="#0A192F">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
</head><body>
{header(active)}
<main id="main">
{hero or ""}
{body}
</main>
{footer()}
</body></html>'''
    doc = re.sub(r'(?<![\w/.-])(index|about|services|property-management|construction-supervision|construction-consultancy|land-verification|farm-management|supplies-tendering|projects|properties|partners|resources|contact|portal|legal)\.html', lambda m: m.group(1) + '.php', doc)
    out = fname[:-5] + ".php"
    with open(os.path.join(ROOT, out), "w", encoding="utf-8") as f:
        f.write(doc)
    print("built", out)

def page_hero(crumb, h1, sub, slot=None):
    bg = ('<?php if ($im = slot_image("%s")): ?><div class="ph-bg"><img src="<?=h($im["file"])?>" alt=""></div><?php endif; ?>' % slot) if slot else ''
    return ('<section class="page-hero">%s%s<div class="wrap"><div class="crumbs"><a href="index.html">Home</a> &nbsp;/&nbsp; %s</div><h1>%s</h1><p>%s</p></div></section>'
            % (bg, topo(8, 2), crumb, h1, sub))

def sec_head(eyebrow, h2, lead="", center=False):
    return '<div class="sec-head rv%s"><span class="eyebrow">%s</span><h2>%s</h2>%s</div>' % (" center" if center else "", eyebrow, h2, ('<p class="lead">%s</p>' % lead) if lead else "")

def cta_band(h="Have a property, project, land, farm, or supply requirement?", p="Let us understand your needs and help you identify the right service arrangement.", svc=""):
    q = "#service=" + svc if svc else ""
    return f'''<section class="cta-band"><div class="wrap rv"><span class="eyebrow">Start the conversation</span><h2>{h}</h2><p>{p}</p>
<div class="btn-row"><a class="btn btn-gold" href="contact.html{q or "#brief"}">Submit a Project Brief →</a><a class="btn btn-outline" href="contact.html#consultation">Request a Consultation</a><a class="btn btn-outline" href="{WA_URL}" target="_blank" rel="noopener">WhatsApp Our Team</a></div></div></section>'''

def checks(items):
    return '<ul class="check">%s</ul>' % "".join("<li>%s</li>" % i for i in items)

def feature_cards(items, cols="g3"):
    out = []
    for ic, t, d in items:
        out.append('<div class="card rv">%s<h3>%s</h3><p>%s</p></div>' % (icon(ic), t, d))
    return '<div class="grid %s">%s</div>' % (cols, "".join(out))

def steps(items, cls=""):
    out = "".join('<div class="step rv"><div class="n">%02d</div><h3>%s</h3><p>%s</p></div>' % (i + 1, t, d) for i, (t, d) in enumerate(items))
    return '<div class="steps %s">%s</div>' % (cls, out)

def tabs(items):
    ts = "".join('<button class="tab" role="tab" aria-selected="%s">%s</button>' % ("true" if i == 0 else "false", t) for i, (t, _) in enumerate(items))
    ps = "".join('<div class="tabpanel%s" role="tabpanel"><div class="panel-box">%s</div></div>' % (" on" if i == 0 else "", c) for i, (_, c) in enumerate(items))
    return '<div data-tabs><div class="tabs" role="tablist">%s</div>%s</div>' % (ts, ps)

def notice(title, text):
    return '<div class="notice rv"><b>%s</b> %s</div>' % (title, text)

# ================================================================= PAGES
SVC_META = {
 "property-management": ("property", "building", ["Rent tracking", "Tenants", "Owner reports"]),
 "construction-supervision": ("supervision", "construction", ["Site visits", "Material audits", "Photo reports"]),
 "construction-consultancy": ("consultancy", "construction", ["Planning", "Estimation", "Contractors"]),
 "land-verification": ("land", "land", ["Registry searches", "Boundaries", "Brokerage"]),
 "farm-management": ("farm", "farm", ["Operations", "Inputs & labour", "Land search"]),
 "supplies-tendering": ("supply", "supply", ["Sourcing", "Tenders", "Delivery"]),
}

def svc_photo_cards():
    out = []
    for i, (slug, title, ic, desc) in enumerate(SERVICES):
        slot, kind, meta = SVC_META[slug]
        short = desc.split(". ")[0].rstrip(".") + "."
        out.append(
            '<article class="pcard rv"><a class="pimg" href="%s.html" aria-label="%s">%s<span class="badge">%02d · %s</span></a>'
            '<div class="pbody"><h3>%s</h3><p>%s</p><div class="pmeta">%s</div>'
            '<div class="pfoot"><span class="from">Request a quotation</span><a class="pill" href="%s.html">View details →</a></div></div></article>'
            % (slug, title, photo_or_scene(slot, kind, title), i + 1, SVC_SHORT[slug].split(" &")[0], title, desc,
               "".join("<span>%s</span>" % m for m in meta), slug))
    return '<div class="grid g3">%s</div>' % "".join(out)

def build_home():
    tk = '<?php foreach (array_merge($S["ticker"], $S["ticker"]) as $t): ?><span><?=h($t)?></span><?php endforeach; ?>'
    opts = "".join('<option value="%s">%s</option>' % (sl, SVC_SHORT[sl]) for sl, *_ in SERVICES)
    hero = f'''<section class="hero"><?php if ($im = slot_image("hero")): ?><div class="hero-bg"><img src="<?=h($im["file"])?>" alt="" fetchpriority="high"></div><?php endif; ?>{topo(11, 1)}<div class="wrap hero-grid"><div>
<span class="pillbadge">◆ Zero-Fraud Asset Management · Infrastructure · Procurement Logistics</span>
<h1>Your Assets.<br>Our Responsibility.<br><em>One Trusted Partner.</em></h1>
<p class="lead">From managing rental properties and supervising construction to verifying land, managing farms, and delivering supplies, we help you coordinate your investments and projects with greater visibility and accountability.</p>
<div class="btn-row"><a class="btn btn-gold" href="contact.html#brief">Submit a Project/Tender Brief →</a><a class="btn btn-outline" href="contact.html#consultation">Schedule an Executive Consultation</a></div>
<ul class="trustrow"><li>{icon("doc")}<b>Documented</b><span>Time-stamped reports</span></li><li>{icon("eye")}<b>Remote oversight</b><span>Wherever you are</span></li><li>{icon("shield")}<b>Defined scope</b><span>Clear responsibilities</span></li></ul></div>
<aside class="hero-card" aria-label="Start a project brief"><h3><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-5h6v5"/></svg>Start Your Brief</h3>
<form action="contact.html" method="get"><label>Service<select name="service">{opts}</select></label>
<label>Location<input name="location" placeholder="District, town or area"></label>
<label>Timeline<select name="timeline"><option value="">Select</option><option>Immediately</option><option>Within 1–3 months</option><option>3–6 months</option><option>Exploring options</option></select></label>
<button class="btn btn-navy" type="submit">Continue your brief →</button></form><p class="note">{CONFIG["motto"]}</p></aside></div>
<?php if ($im && $im["credit"] !== ""): ?><span class="hero-credit"><?=h($im["credit"])?></span><?php endif; ?></section>
<div class="ticker" aria-label="Credentials"><div class="ticker-track">{tk}</div></div>'''

    intro = f'''<section><div class="wrap split"><div class="rv"><span class="eyebrow">Who we are</span>
<h2>One partner. Multiple solutions. Accountable service delivery.</h2>
<p>Managing property, overseeing construction, buying land, operating a farm, or fulfilling a supply contract requires time, coordination, and attention to detail.</p>
<p>{CONFIG["full"]} brings these services together under one company, helping individuals, businesses, institutions, and investors coordinate their assignments with clear communication and documented processes.</p>
<p>Whether you are based near your property or thousands of kilometres away, our services can be structured around your requirements, approved budgets, timelines, and preferred reporting arrangements.</p>
<a class="link-arrow" href="about.html">About the company →</a></div>
{visual("building", "Illustrative", "home")}</div></section>'''

    services = f'''<section class="alt"><div class="wrap">{sec_head("Our six core services", "Explore Our <span class='gold'>Core Services</span>", "Each service can be engaged on its own or combined, with clear scopes, documented decisions and agreed reporting.")}
{svc_photo_cards()}<div style="text-align:center;margin-top:44px"><a class="btn btn-navy" href="services.html">View all services →</a></div></div></section>'''

    diligence = f'''<section><div class="wrap"><div class="locband rv"><div class="loc-text"><span class="pillbadge dk">◆ Diaspora Land Due Diligence Centre</span>
<h2>Never Buy Land <span class="gold">Blindly Again.</span></h2><p>Our 30-day protocol puts documented evidence between you and a costly mistake — wherever in the world you are.</p>
<ul class="trustrow dk"><li>{icon("doc")}<b>Registry look-ups</b><span>Ministry of Lands</span></li><li>{icon("map")}<b>Boundary walking</b><span>With neighbours</span></li><li>{icon("users")}<b>LC1 validation</b><span>Communal check</span></li></ul>
<a class="btn btn-gold" href="land-verification.html">See the 30-day protocol →</a></div>
<div class="loc-img">{photo_or_scene("land", "land", "Land verification")}<span class="loc-card"><b>Independent GPS surveying</b>Qualified surveyor where needed</span></div></div></div></section>'''

    stats = f'''<section class="alt"><div class="wrap"><div class="statband rv"><div><span class="pillbadge dk">◆ Why choose us</span><h2>We Make Asset Management <span class="gold">Simple &amp; Transparent.</span></h2></div>
<div><p>Our approach gives you a seamless experience with documented processes, clear costs and dedicated support — every step of the way.</p>
<div class="stats"><div>{icon("box")}<b>6</b><span>Core services</span></div><div>{icon("doc")}<b>5</b><span>Step process</span></div><div>{icon("map")}<b>30</b><span>Day land protocol</span></div><div>{icon("handshake")}<b>1</b><span>Accountable partner</span></div></div></div></div></div></section>'''

    manifesto = f'''<section><div class="wrap"><div class="grid g2 manifest-grid">
<div class="manifesto rv"><span class="eyebrow">Managing Director's Anti-Fraud Manifesto</span>
<blockquote>“In East African real estate and procurement, the ultimate currency isn't capital — it is trust. Clapet was founded on a simple, unyielding principle: to eliminate human risk through strict corporate governance and real-time digital accountability.”</blockquote>
<cite><b>{CONFIG["md"]}</b><br>Founder &amp; Managing Director</cite></div>
<div class="promise rv"><h3>The Clapet Promise</h3><p>We do not touch your project capital. Your material budgets remain in secure trust, managed entirely through transparent logs and daily digital audits.</p></div></div></div></section>'''

    why = f'''<section class="dark"><div class="wrap"><div class="sec-head rv"><span class="eyebrow">Why choose Clapet</span><h2>Professional coordination. Clear records. Reliable communication.</h2></div>
<div class="grid g3">
{"".join('<div class="card rv" style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.12)">%s<h3 style="color:#fff">%s</h3><p style="color:#a9b8d0">%s</p></div>' % (icon(ic), t, d) for ic, t, d in [
 ("eye", "Remote oversight", "Receive updates about your property, project, or farm even when you cannot be physically present."),
 ("doc", "Documented processes", "Access agreed reports, photographs, inspection records, and project documentation."),
 ("chart", "Transparent cost management", "Review quotations, agreed budgets, authorised variations, and recorded expenditure."),
 ("handshake", "Multiple service capabilities", "Coordinate related asset and project requirements through one service provider."),
 ("shield", "Defined responsibilities", "Establish who is responsible for each task, decision, approval, and deliverable."),
 ("mail", "Client-focused reporting", "Receive updates according to an agreed schedule and reporting format.")])}
</div></div></section>'''

    proc = f'''<section><div class="wrap">{sec_head("How our services work", "Five clear steps from first conversation to close-out.", "", True)}
{steps([("Share your requirements", "Tell us about your property, construction project, land, farm, or supply requirement."),
 ("Assessment and proposal", "We clarify the scope, location, budget, deliverables, timelines, and required documentation."),
 ("Agreement and planning", "We agree on responsibilities, service fees, approval limits, reporting frequency, and timelines."),
 ("Service delivery", "The assigned team coordinates the work and records progress, decisions, and issues."),
 ("Reporting and review", "You receive the agreed reports, supporting records, and close-out information.")])}</div></section>'''

    approach = f'''<section class="alt"><div class="wrap">{sec_head("Our Work. Our Process. Our Commitment.", "Our approach to service delivery", "Every assignment follows the same disciplined pattern. Documented portfolio entries will be published here as projects are completed and clients grant permission.", True)}
<div class="grid g4">
{"".join('<div class="card rv">%s<h3>%s</h3><p>%s</p></div>' % (icon(ic), t, d) for ic, t, d in [
 ("doc", "Agreed scope", "Responsibilities, approval limits and deliverables are written down before work begins."),
 ("clock", "Time-stamped evidence", "Visits, deliveries and milestones are recorded with dated photographs and notes."),
 ("lock", "Controlled access", "Clients see their own records only, through authenticated, permission-checked access."),
 ("chart", "Honest reporting", "Estimates are separated from actuals, and limitations of any check are stated plainly.")])}
</div><div style="text-align:center;margin-top:40px"><a class="btn btn-navy" href="projects.html">View projects &amp; categories →</a></div></div></section>'''

    portal = f'''<section><div class="wrap split"><div class="rv"><span class="eyebrow">Secure client portal</span><h2>See what we see — with a permanent record.</h2>
<p class="lead">Secure project updates, documents, reports, and service requests, available to you wherever you are.</p>
{checks(["Live media drops from site with dated photographs and video","Material audit logs for deliveries, quantities and discrepancies","Approvals for variations and maintenance, recorded with timestamps","Reports and statements you can download securely"])}
<a class="btn btn-navy" href="portal.html">Preview the client portal →</a></div>
<div class="rv"><div class="phone"><div class="phone-in"><div class="wa-head"><i>C</i><div>Clapet Site Desk<small>online · Site update</small></div></div><div class="wa-body">
<div class="msg"><div class="ph">▶ SITE VIDEO · 09:12</div>Slab pour complete — Block B. Cement bags logged: 48 / 48.<time>09:14</time></div>
<div class="msg">Material audit: steel bars Y12 received 120, counted 120 ✔<time>09:20</time></div>
<div class="msg me">Approved. Thank you.<time>09:31 ✔✔</time></div></div></div></div></div></div></section>'''
    body = services + intro + diligence + manifesto + stats + why + proc + approach + portal + cta_band()
    page("index.html", "Property Management, Construction & Land Verification in Uganda",
         "CLAPET ASSET MANAGEMENT UG - SMC — The Standard of Trust. Property management, construction supervision, land verification, farm management and general supplies across Uganda.",
         "index.html", body, hero)

def build_about():
    vals = [("shield", "Integrity", "We act honestly, protect clients' interests within our agreed responsibilities, and communicate material risks openly."),
            ("eye", "Transparency", "We promote clear agreements, understandable costs, documented decisions, and accessible reports."),
            ("doc", "Accountability", "We establish responsibilities, track agreed deliverables, and document outstanding actions."),
            ("users", "Client-focused service", "We consider each client's objectives, circumstances, budget, and preferred reporting arrangements."),
            ("building", "Professionalism", "We encourage careful planning, competent service delivery, appropriate documentation, and continuous improvement."),
            ("clock", "Reliability", "We work towards agreed timelines and communicate promptly when circumstances affect delivery.")]
    body = f'''<section><div class="wrap split"><div class="rv"><span class="eyebrow">Company overview</span><h2>About {CONFIG["full"]}</h2>
<p>{CONFIG["full"]} is a multi-service company focused on asset management, property operations, construction supervision, construction contracts and consultancy, land verification and brokerage, farm management, and general supplies.</p>
<p>We work with clients who need assistance coordinating assets and projects, overseeing field activities, organising transactions, or fulfilling procurement requirements. Our approach is built around clear communication, agreed responsibilities, documented activities, and service delivery tailored to each assignment.</p>
<p>We recognise that property owners, investors, and institutions may not always have the time or physical presence required to oversee every activity themselves. Our services are designed to help bridge that gap through organised coordination and agreed reporting arrangements.</p></div>
{visual("land", "Illustrative", "about")}</div></section>
<section class="alt"><div class="wrap"><div class="grid g2">
<div class="promise rv"><h3>Our mission</h3><p>To deliver reliable property, construction, land, agricultural, and procurement services through professional management, transparent processes, and accountable execution.</p></div>
<div class="promise rv"><h3>Our vision</h3><p>To become a trusted multi-service asset management partner for individuals, businesses, institutions, and investors across Uganda and the wider East African region.</p></div></div>
<div class="manifesto rv" style="margin-top:56px"><span class="eyebrow">Our philosophy</span><blockquote>We believe good asset management begins with understanding the client's objectives, establishing clear responsibilities, maintaining accurate records, and communicating promptly when decisions or corrective actions are required.</blockquote></div></div></section>
<section><div class="wrap">{sec_head("The Clapet Code", "Our core values", "", True)}{feature_cards(vals)}</div></section>
<section class="dark"><div class="wrap split"><div class="rv"><span class="eyebrow">Leadership &amp; governance</span><h2>Accountable leadership.</h2>
<p class="lead">{CONFIG["md"]} is the Founder and Managing Director of {CONFIG["full"]}.</p>
{checks(["Managing Director's approved biography and photograph","Management team profiles, qualifications and operational responsibilities","Verified professional partnerships","Company governance and accountability arrangements"])}
<p style="font-size:.9rem;color:#8ea1bf">Leadership profiles are published as approved by each individual.</p></div>
<div class="promise rv" style="background:rgba(255,255,255,.04)"><h3>Company profile download</h3><p style="font-size:1.05rem;font-weight:500">A corporate profile PDF — identity, mission, values, service portfolio, management contacts, verified credentials and procurement capabilities — is available on request.</p><div class="btn-row"><a class="btn btn-gold btn-sm" href="contact.html#consultation">Request the company profile</a></div></div></div></section>
<section><div class="wrap narrow">{sec_head("FAQs", "Common questions answered", "", True)}{faq_html(FAQS[:5])}<div style="text-align:center;margin-top:30px"><a class="link-arrow" href="resources.html#faqs">See all FAQs →</a></div></div></section>
{cta_band()}'''
    page("about.html", "About Us", "About CLAPET ASSET MANAGEMENT UG - SMC: mission, vision, values, leadership and governance.", "about.html", body,
         page_hero("About Us", "About " + CONFIG["full"], "A professional, transparent and accountable partner for managing assets, supervising projects and coordinating service delivery.", "about"))

FAQS = [
 ("Can you manage my property if I live abroad?", "Yes. Services can be arranged for owners who live abroad or outside the property's location, subject to an agreed management scope, access to necessary information, and the appropriate authorisations."),
 ("Can I hire you only to supervise construction?", "Yes. Construction supervision can be provided as a standalone service with agreed site visits, reporting frequency, responsibilities, and limitations."),
 ("Can you help me verify land before purchase?", "Clapet can coordinate relevant document reviews, official searches, site inspections, and specialist engagements according to the agreed scope. No verification process should be represented as guaranteeing a risk-free transaction."),
 ("Do you undertake construction contracts?", "The company can consider construction assignments within its verified capacity, professional requirements, and contractual scope."),
 ("Can you manage agricultural land on my behalf?", "Yes. Farm management services can be structured around the land, activities, budget, reporting needs, and objectives of the client."),
 ("Do you supply goods to institutions and NGOs?", "Clapet can respond to suitable supply requirements and procurement opportunities within its capacity and the applicable eligibility conditions."),
 ("How do I request a quotation?", "Submit a project brief describing the service required, location, scope, timeline, and available budget information. The team can assess the enquiry and advise on next steps."),
 ("Can I monitor my project online?", "Where the client portal service is available and included in the engagement, authorised clients can view reports, project milestones, documents, and service requests."),
 ("How are maintenance costs approved?", "The owner and Clapet should agree on approval limits and procedures before work begins. The portal can record quotations, approvals, invoices, and completion evidence."),
 ("How can I become a supplier or contractor?", "Submit the partner registration form with relevant company details, service categories, experience, and supporting documents. Registration is subject to review."),
]
def faq_html(items):
    return '<div class="faq">%s</div>' % "".join("<details><summary>%s</summary><p>%s</p></details>" % (q, a) for q, a in items)

SLOT_OF = {'property-management': 'property', 'construction-supervision': 'supervision', 'construction-consultancy': 'consultancy', 'land-verification': 'land', 'farm-management': 'farm', 'supplies-tendering': 'supply'}

def service_page(slug, hero_h1, crumb, intro_html, kind, kind_label, main_html, process_title, process_steps, steps_cls, extra_html="", note=None, form_svc="", seo=""):
    hero = page_hero(crumb, hero_h1, "", SLOT_OF[slug])
    hero = hero.replace("<p></p>", "")
    body = f'''<section><div class="wrap split"><div class="rv"><span class="eyebrow">{crumb}</span><h2>{process_title[0]}</h2>{intro_html}
<div class="btn-row"><a class="btn btn-gold" href="contact.html#service={form_svc}">Request this service →</a><a class="btn btn-line" href="contact.html#consultation">Book a consultation</a></div></div>{visual(kind, kind_label, SLOT_OF[slug])}</div></section>
{main_html}
<section class="{"" if extra_html == "" else "alt"}"><div class="wrap">{sec_head("How it works", process_title[1], "", True)}{steps(process_steps, steps_cls)}</div></section>
{extra_html}
{('<section><div class="wrap narrow">' + note + '</div></section>') if note else ''}
{cta_band(svc=form_svc)}'''
    page(slug + ".html", crumb, seo, "svc-" + slug, body, hero)

def build_services():
    body = f'''<section><div class="wrap">{sec_head("Our services", "Six services. One accountable partner.", "Choose a single service or combine several. Every engagement starts with an agreed scope, defined responsibilities and a reporting schedule.", True)}{svc_photo_cards()}</div></section>
<section class="alt"><div class="wrap">{sec_head("The Clapet Code", "What stays the same on every assignment", "", True)}
{feature_cards([("eye","Absolute Transparency","Every project shilling is traceable; every milestone is documented with time-stamped digital proof. No hidden budgets."),("clock","Operational Discipline","Strict adherence to project timelines, legal compliance, and URA tax accountability."),("shield","Uncompromising Integrity","Zero tolerance for contractor collusion, material substitution, or shortcut practices."),("users","Client-First Proxy Representation","We act as your unyielding shield on the ground, protecting your interests as our own.")], "g4")}</div></section>
{cta_band()}'''
    page("services.html", "Our Services", "Property management, construction supervision, construction contracts and consultancy, land verification, farm management and general supplies.", "svc-all", body,
         page_hero("Our Services", "Our Services", "Property Management · Construction · Land Verification · Farm Management · General Supplies"))

def build_services_detail():
    # 01 Property management
    pm_tabs = [("Rent Collection & Tracking", "Maintain rent schedules, record payments, monitor arrears, issue authorised receipts, and prepare collection reports."),
               ("Tenant Management", "Coordinate tenant enquiries, onboarding, tenancy records, communication, renewals, complaints, and move-out processes."),
               ("Repairs & Maintenance", "Record faults, arrange assessments, obtain quotations, coordinate approved repairs, and document completion."),
               ("Property Inspections", "Conduct agreed inspections, record property condition, take approved photographs, and recommend corrective actions."),
               ("Financial Reporting", "Prepare statements showing rent collected, outstanding balances, authorised expenses, maintenance costs, and reconciled totals."),
               ("Vacancy Management", "Coordinate property listings, enquiries, viewings, application records, and occupancy updates."),
               ("Lease Administration", "Maintain tenancy agreements, renewal dates, relevant notices, and other authorised tenancy documentation.")]
    pm_main = f'''<section class="alt"><div class="wrap">{sec_head("What we manage", "Seven disciplines of professional property management.", "", False)}{tabs([(t, "<h3>%s</h3><p style='margin:0'>%s</p>" % (t, d)) for t, d in pm_tabs])}</div></section>
<section><div class="wrap split"><div class="rv"><span class="eyebrow">Portal visibility</span><h2>Your property, visible to you.</h2>{checks(["Properties and units, with tenancy status","Rent schedules and recorded payments","Arrears and collection reports","Maintenance requests and approval status","Inspection reports and photographs","Authorised expenses and financial statements","Tenancy documents and renewal dates"])}</div>
<div class="rv"><table class="tbl"><tr><th colspan="2">Rent is tracked in four distinct states</th></tr><tr><td>Expected</td><td>Scheduled under the tenancy for the period.</td></tr><tr><td>Received</td><td>Recorded only when evidence of payment is received.</td></tr><tr><td>Reconciled</td><td>Matched against the tenancy and period.</td></tr><tr><td>Outstanding</td><td>Balance still due, tracked as arrears.</td></tr></table>
<p style="font-size:.88rem;color:var(--muted);margin-top:14px">Payments are never marked as received solely because a scheduled date has passed.</p></div></div></section>'''
    service_page("property-management", "Your Property, Professionally Managed.", "Property Management",
        "<p>Property ownership comes with ongoing responsibilities, from collecting rent and communicating with tenants to maintaining buildings, monitoring occupancy, and managing repair costs.</p><p>%s provides property management services designed to help owners organise these responsibilities and maintain visibility over their assets. Whether you own a single rental unit, several residential properties, commercial premises, or a larger portfolio, our services can be tailored to the agreed management scope.</p>" % CONFIG["full"],
        "property", "Illustrative — managed property", pm_main,
        ("Your property, professionally managed.", "How our property management works"),
        [("Property assessment", "Identify the property, units, condition, tenancy arrangements, and owner objectives."), ("Management agreement", "Agree on fees, responsibilities, rent handling, spending limits, reporting frequency, and authorisation procedures."), ("Property onboarding", "Create property records, tenancy schedules, maintenance records, and relevant document files."), ("Day-to-day coordination", "Manage agreed tenant communications, collection records, inspections, and maintenance requests."), ("Periodic reporting", "Share statements, outstanding issues, occupancy information, and maintenance updates."), ("Review and improvement", "Review performance and agree on necessary adjustments with the owner.")],
        "six", note=notice("Owner authorisation.", "Property management responsibilities are limited to the agreed scope and the owner's written authorisations, including spending limits."), form_svc="property-management",
        seo="Property management in Uganda: rent collection, tenant management, maintenance, inspections and owner reporting.")

    # 02 Construction supervision
    sup = [("Site inspections", "Scheduled visits, progress observations, site condition records, and photographic evidence."), ("Construction progress", "Comparison of planned and observed progress against agreed milestones."), ("Materials monitoring", "Recording deliveries, available quantities, supporting documents, and discrepancies observed."), ("Contractor monitoring", "Recording attendance, reported activities, completed work, and outstanding tasks."), ("Workmanship observations", "Documenting visible defects and referring technical concerns to the appropriate professional."), ("Budget monitoring", "Comparing recorded expenditure and requested variations against authorised budgets."), ("Project reporting", "Periodic reports, photographs, risks, issues, and actions requiring decisions."), ("Handover support", "Snag lists, outstanding work records, completion documentation, and handover coordination.")]
    sup_main = f'''<section class="alt"><div class="wrap">{sec_head("Anti-fraud material audit tracking", "Every bag of cement. Every steel bar. Logged.", "We physically log deliveries and usage on site to stop contractor collusion and material substitution — and show you the evidence.")}
<table class="tbl rv"><tr><th>Service</th><th>What it includes</th></tr>{"".join("<tr><td>%s</td><td>%s</td></tr>" % x for x in sup)}</table></div></section>
<section><div class="wrap">{sec_head("See the difference", "From dilapidated to transformed.", "Drag the slider to compare a property before and after a full turnkey remodel. (Illustrative — replace with an authentic project's photographs.)")}
<div class="ba rv"><div class="before-wrap" style="position:absolute;inset:0">{scene("building","before")}</div><div class="after-wrap">{scene("building","after")}</div><span class="lbl l">Before</span><span class="lbl r">After</span><div class="handle"></div><input type="range" min="2" max="98" value="50" aria-label="Before and after comparison slider"></div></div></section>
<section class="dark"><div class="wrap split"><div class="rv"><span class="eyebrow">Remote construction monitoring</span><h2>Built for clients who can't be on site.</h2><p class="lead">Particularly useful for clients living outside the project area or abroad. Depending on the agreed scope, you may receive:</p>
{checks(["Dated photographs and videos","Summaries of completed work","Planned versus actual milestone updates","Records of materials delivered and observed","Explanations of delays and outstanding issues","Quotations and variation requests requiring approval","Inspection and handover reports"])}</div>
<div class="rv"><div class="phone"><div class="phone-in"><div class="wa-head"><i>C</i><div>Clapet Site Desk<small>Live field update</small></div></div><div class="wa-body">
<div class="msg"><div class="ph">📷 PHOTO · 08:41</div>Foundation inspection, Plot 14. Rebar spacing recorded against drawings.<time>08:42</time></div>
<div class="msg"><div class="ph">▶ VIDEO · 10:05</div>Delivery: 60 bags cement counted at gate, signed by foreman.<time>10:06</time></div>
<div class="msg me">Thanks — please hold the pour until Friday.<time>10:20 ✔✔</time></div>
<div class="msg">Noted. Instruction logged in the portal.<time>10:21</time></div></div></div></div><p style="text-align:center;font-size:.8rem;color:#8ea1bf;margin-top:16px">Illustrative WhatsApp field-update mockup</p></div></div></section>'''
    service_page("construction-supervision", "Your Eyes on the Ground, Wherever You Are.", "Construction Site Supervision",
        "<p>Managing construction from a distance can be challenging. Owners may struggle to verify progress, monitor materials, follow up contractors, assess workmanship, or identify delays before they become costly problems.</p><p>%s offers construction site supervision and project monitoring for clients who are busy, live far from their project sites, or need a designated representative to monitor agreed activities.</p>" % CONFIG["full"],
        "construction", "Illustrative — active site", sup_main,
        ("Anti-fraud property supervision.", "Our construction reporting workflow"),
        [("Project baseline", "Record the approved scope, schedule, budget, drawings and milestones available to the supervisor."), ("Site visit", "Inspect the site according to the agreed schedule and document observable conditions."), ("Evidence collection", "Upload dated photographs, observations, material records, and outstanding issues."), ("Report preparation", "Compare progress with agreed milestones and identify risks, delays, or decisions required."), ("Client review", "Share the report securely and record the client's instructions or approvals."), ("Follow-up", "Track corrective actions and record their resolution or escalation.")],
        "six", note=notice("Professional boundary.", "Site monitoring is not certified structural, architectural, engineering, or quantity-surveying approval unless carried out by appropriately qualified and authorised professionals."), form_svc="construction-supervision",
        seo="Construction site supervision in Uganda with material audit tracking, photographic reports and remote monitoring.")

    # 03 Construction contracts & consultancy (incl. civil engineering)
    cons = [("building", "Residential construction", "Support for new homes, rental units, extensions, and other residential building assignments."), ("crane", "Commercial construction", "Offices, shops, business premises and other commercial properties, subject to capability and applicable requirements."), ("building", "Renovation and remodelling", "Building improvements, repairs, extensions, and agreed property upgrades — turnkey property transformations."), ("road", "Civil works", "Eligible civil assignments — road grading, culverts and site development — within our capacity and required professional approvals."), ("doc", "Project planning & consultancy", "Defining scope, preparing work schedules, coordinating professionals, reviewing estimates, organising project documentation."), ("chart", "Cost estimation & budgeting", "Preliminary cost estimates, materials schedules, budget proposals, and expenditure tracking."), ("handshake", "Contractor coordination", "Coordinating contractors, suppliers, milestones, and communication among authorised project stakeholders.")]
    life = ["Client consultation and initial project brief", "Site assessment and requirements definition", "Design and technical professional engagement, where required", "Scope of work, budget, schedule, and proposal", "Contract preparation and agreement", "Procurement and mobilisation planning", "Construction or renovation execution", "Progress monitoring and approved variations", "Inspection, snag resolution, and handover", "Final reporting and project closure"]
    cc_main = f'''<section class="alt"><div class="wrap">{sec_head("Service categories", "From planning to practical execution.")}{feature_cards(cons)}</div></section>
<section><div class="wrap split"><div class="rv"><span class="eyebrow">Institutional capacity, seamless execution</span><h2>Construction project lifecycle</h2><ol class="check" style="counter-reset:l">{"".join("<li>%s</li>" % l for l in life)}</ol></div>
<div class="rv">{notice("Two engagement types.", "We distinguish between a contract executed by Clapet and an assignment where the company only provides supervision or consultancy. Each project carries a unique reference, signed agreement, approved scope, schedule, cost baseline, change log and completion documents.")}</div></div></section>'''
    service_page("construction-consultancy", "From Project Planning to Practical Execution.", "Construction Contracts & Consultancy",
        "<p>Construction projects require careful planning, realistic budgeting, competent execution, and effective coordination. %s supports clients who want to construct, renovate, extend, or improve residential, commercial, and other eligible structures.</p><p>Depending on the project and our verified capabilities, services may cover planning, coordination, construction execution, cost estimation, contractor management, and handover support.</p>" % CONFIG["full"],
        "construction", "Illustrative — construction project", cc_main,
        ("Planning to execution.", "How a construction assignment runs"),
        [("Consultation & brief", "We understand the project type, intended use, location, size, stage, budget and timeline."), ("Assessment & proposal", "Site assessment, scope of work, cost baseline and schedule are agreed in writing."), ("Contract & mobilisation", "Agreement signed, suppliers and contractors coordinated, mobilisation planned."), ("Execution & monitoring", "Work proceeds with progress monitoring and approved variations only."), ("Handover & close-out", "Inspection, snag resolution, completion documents and final report.")],
        "", form_svc="construction-contracts",
        seo="Construction contracts, renovation, civil works and project consultancy in Uganda.")

    # 04 Land verification
    land_main = f'''<section class="alt"><div class="wrap">{sec_head("Services offered", "Never buy land blindly again.")}
{feature_cards([("doc","Land search facilitation","Coordinate relevant searches and enquiries through the appropriate registry or competent authority."),("eye","Document review","Review the documents supplied, identify missing information, and flag inconsistencies requiring further checks."),("map","Site visits","Record the physical condition of the land, access routes, visible occupation, and other observable features."),("shield","Boundary verification coordination","Coordinate engagement with an appropriately qualified surveyor where technical boundary identification is required."),("handshake","Land acquisition support","Organise information, communicate with relevant parties, track outstanding checks, and coordinate transaction activities."),("key","Property brokerage & land searching","Facilitate authorised introductions and viewings; identify land by location, size, price and intended use.")])}</div></section>
<section class="dark"><div class="wrap">{sec_head("The Diaspora Land Due Diligence Centre", "The 30-Day Protocol", "A structured, evidence-led process built for buyers who cannot be on the ground.")}
<div class="grid g4">{"".join('<div class="card rv" style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.12)">%s<h3 style="color:#fff">%s</h3><p style="color:#a9b8d0">%s</p></div>' % x for x in [(icon("doc"), "Ministry of Lands registry look-ups", "Formal title registry look-ups coordinated through the appropriate authority."), (icon("map"), "Boundary walking", "Physical perimeter boundary walking together with neighbours."), (icon("users"), "LC1 validation", "Local council (LC1) communal validation of occupation and ownership claims."), (icon("shield"), "Independent GPS surveying", "Qualified surveyor engaged where technical boundary work is needed.")])}</div></div></section>
<section><div class="wrap">{sec_head("Land listings", "Every published listing is transparent.", "Listings show their verification status explicitly.")}
<div class="stage rv">{"".join('<span class="chip%s">%s</span>' % (c, t) for c, t in [("", "Documents Received"), ("", "Checks in Progress"), (" gold", "Checks Completed Within Scope"), ("", "Further Verification Required")])}</div>
<div class="btn-row"><a class="btn btn-navy" href="properties.html">Browse land &amp; properties →</a></div></div></section>'''
    service_page("land-verification", "Make Informed Land Decisions Before You Commit.", "Land Verification & Brokerage",
        "<p>Land transactions require careful checks of ownership documents, boundaries, access, land use, and other relevant matters. Clients may also need assistance finding suitable land or coordinating a transaction when they cannot personally attend every stage.</p><p>%s provides land search facilitation, due diligence coordination, site inspection, brokerage, and acquisition support according to the scope agreed with each client.</p>" % CONFIG["full"],
        "land", "Illustrative — land verification", land_main,
        ("Never buy land blindly again.", "Our land verification workflow"),
        [("Receive the assignment", "Record the client's requirements, property reference, seller details, and available documents."), ("Review documents", "Identify information requiring clarification or formal verification."), ("Coordinate official searches", "Submit or coordinate appropriate searches through relevant authorities."), ("Inspect the site", "Record site observations, access, visible occupation, and other relevant conditions."), ("Verify boundaries where needed", "Engage an appropriately qualified surveyor for technical boundary work."), ("Prepare findings", "Summarise completed checks, supporting evidence, limitations, and unresolved concerns."), ("Support next steps", "Provide the report and coordinate further action within the agreed mandate.")],
        "seven", note=notice("Transaction safeguards.", "We do not guarantee land ownership, authenticity, or a risk-free transaction. Verification has a defined scope, and legal, survey, valuation or other specialist advice may be necessary. Uploaded documents do not imply that land is legally cleared or free of disputes."), form_svc="land-verification",
        seo="Land verification and brokerage in Uganda: registry searches, boundary verification, site inspection and acquisition support.")

    # 05 Farm
    farm = [("leaf","Farm assessment and planning","Document the farm's size, current activities, available facilities, operational needs, and management objectives."),("wheat","Crop production monitoring","Track planting schedules, crop maintenance activities, observed challenges, and progress against plan."),("users","Livestock management coordination","Maintain records of livestock activities, feed, stock, veterinary coordination, and operational information."),("clock","Labour management","Coordinate work schedules, task allocation, attendance records, and activity reporting."),("box","Agricultural input monitoring","Record seed, fertiliser, chemicals, feed, and other authorised inputs received and used."),("crane","Equipment and infrastructure","Track equipment, maintenance, irrigation infrastructure, storage facilities, and other assets."),("chart","Financial and production reporting","Reports on recorded costs, activities, production estimates, harvests, and sales where available."),("map","Agricultural land searching","Identify potential land by location, size, soil or water information, access, price, and intended use.")]
    mods = [("Farm profile","Location, size, ownership or management reference, facilities"),("Crop register","Crop type, planting dates, plot information, planned activities"),("Livestock register","Species, stock numbers, activities, health-related records where appropriate"),("Labour","Staff assignments, work schedules, attendance, completed tasks"),("Inputs","Stock received, quantities issued, usage, remaining balances"),("Equipment","Asset register, condition, maintenance schedule"),("Expenditure","Recorded expenses, supporting documents, budget comparison"),("Production","Harvest records, output, sales, and estimates"),("Reports","Inspection findings, risks, recommendations, and action items")]
    farm_main = f'''<section class="alt"><div class="wrap">{sec_head("Farm management services", "Oversight for every part of the operation.")}{feature_cards(farm, "g4")}</div></section>
<section><div class="wrap">{sec_head("Farm reporting dashboard", "Nine modules of recorded information.")}<table class="tbl rv"><tr><th>Module</th><th>Information recorded</th></tr>{"".join("<tr><td>%s</td><td>%s</td></tr>" % m for m in mods)}</table></div></section>'''
    service_page("farm-management", "Better Oversight for Your Land and Agricultural Investment.", "Farm Management & Land Searching",
        "<p>Agricultural operations require regular attention to land preparation, planting, inputs, labour, livestock, equipment, production, and expenditure.</p><p>%s helps landowners, farmers, and agricultural investors coordinate operations, maintain farm records, monitor agreed activities, and identify suitable land for agricultural use.</p>" % CONFIG["full"],
        "farm", "Illustrative — farm operations", farm_main,
        ("Better oversight.", "Service delivery process"),
        [("Assess", "Assess the farm and the client's objectives."), ("Agree scope", "Agree on the management scope and reporting schedule."), ("Baseline", "Establish baseline records and operational plans."), ("Coordinate & record", "Coordinate approved farm activities; record inputs, labour, expenses and production."), ("Report & review", "Provide periodic performance reports; review results and agree on adjustments.")],
        "", note=notice("Estimates versus actuals.", "Production estimates are clearly distinguished from actual measured results. We do not guarantee yields or profits."), form_svc="farm-management",
        seo="Farm management and agricultural land searching in Uganda.")

    # 06 Supplies & tendering
    cats = [("building","Construction materials","Construction materials and building supplies."),("doc","Office supplies","Office stationery and consumables."),("laptop","IT hardware","Computers, IT equipment, and accessories."),("book","Scholastic supplies","School supplies, books, and uniforms."),("wheat","Agricultural inputs","Agricultural inputs and equipment."),("box","Food supplies","Bulk food rations, supplies and agricultural produce."),("users","Furniture & equipment","Furniture and institutional equipment."),("road","Civil capability","Road grading, culverts and multi-storey masonry within approved capacity.")]
    tend = [("Tender opportunity management","Maintain a register of relevant tender opportunities, deadlines, requirements, and submission status."),("Bid preparation coordination","Organise tender documents, eligibility checklists, administrative forms, and supporting records."),("Supplier sourcing","Identify potential suppliers, request quotations, and compare pricing, specifications, delivery terms, and availability."),("Procurement fulfilment","Coordinate approved purchases, delivery schedules, supplier communication, and contract requirements."),("Delivery documentation","Maintain purchase orders, delivery notes, inspection records, invoices, and supporting documents."),("Contract reporting","Track delivery milestones, outstanding items, completion status, and supplier performance.")]
    sup_main = f'''<section class="alt"><div class="wrap">{sec_head("Capabilities", "Institutional capacity, seamless execution.", "Supply categories within the company's approved capacity.")}{feature_cards(cats, "g4")}</div></section>
<section><div class="wrap">{sec_head("Tender &amp; procurement support", "Everything from sourcing to delivery documentation.")}
{tabs([("Support services", "<div class='grid g2'>" + "".join("<div><h3>%s</h3><p style='margin:0'>%s</p></div>" % t for t in tend) + "</div>"),
       ("Tenders & deadlines", "<h3>Open opportunities &amp; upcoming deadlines</h3><p>Tender opportunities and upcoming deadlines are published here as they are verified and approved internally. No bid is submitted without internal approval.</p><a class='btn btn-navy btn-sm' href='contact.html#service=general-supplies'>Submit a tender brief</a>"),
       ("Supplier registration", "<h3>Register as a supplier</h3><p>Provide your organisation name, contact information, supply categories, location, relevant experience, references, applicable registration details and supporting documents. Registration is subject to review and does not grant approved-vendor status.</p><a class='btn btn-navy btn-sm' href='partners.html#register'>Register interest</a>"),
       ("Request a quotation", "<h3>Request a quotation</h3><p>Share specifications, quantities, delivery location and deadline. We source comparative quotations and advise on next steps.</p><a class='btn btn-navy btn-sm' href='contact.html#service=general-supplies'>Request a quotation</a>")])}
</div></section>'''
    service_page("supplies-tendering", "Reliable Supply. Clear Documentation. Accountable Delivery.", "General Supplies & Tendering",
        "<p>Organisations require reliable suppliers, clear quotations, proper documentation, and timely delivery of goods and materials.</p><p>%s provides general supply and procurement coordination within its verified capacity, supporting businesses, institutions, NGOs, and eligible public-sector requirements.</p>" % CONFIG["full"],
        "supply", "Illustrative — institutional supply", sup_main,
        ("Reliable supply.", "From requirement to documented delivery"),
        [("Brief", "Submit the tender reference, specifications, quantities, delivery location and deadline."), ("Source", "Identify suppliers and compare quotations on price, specification and delivery terms."), ("Approve", "Internal bid approval and eligibility check — nothing is submitted automatically."), ("Deliver", "Coordinate purchase orders, delivery schedules and inspection."), ("Report", "Delivery notes, invoices and contract completion reporting.")],
        "", note=notice("Procurement safeguards.", "We do not claim eligibility for a specific public tender, a particular procurement registration, or any awarded contract unless it has been verified. Bid submissions require internal approval."), form_svc="general-supplies",
        seo="General supplies and tendering in Uganda: construction materials, IT hardware, scholastic and food supplies, procurement support.")

def build_projects():
    cats = [("building","Property management assignments"),("crane","Construction and renovation"),("eye","Site supervision"),("map","Land verification and brokerage"),("leaf","Farm management"),("box","General supplies and procurement")]
    body = f'''<section><div class="wrap">{sec_head("Portfolio", "Genuine, documented examples — published with permission.", "We publish only real, approved projects: no invented case studies, unverified client names or unsupported figures. Entries appear here as assignments complete and clients consent.")}
<form class="filters rv" data-filter-form="#results"><label>Category<select><option>All categories</option>{"".join("<option>%s</option>" % c[1] for c in cats)}</select></label><label>Status<select><option>Any</option><option>Planned</option><option>Ongoing</option><option>Completed</option><option>On Hold</option></select></label><label>Location<input placeholder="District or town"></label><label>Year<input placeholder="e.g. 2026"></label><button class="btn btn-navy btn-sm" style="align-self:end">Filter</button></form>
<div id="results" class="empty rv"><h3>Our first portfolio entries are being prepared.</h3><p>Each entry will show the title, category, general location, status, scope, approved photographs, timeline and documented results — and is approved before publication. Confidential budgets, sensitive site coordinates and client documents are never published without authorisation.</p><div class="btn-row" style="justify-content:center"><a class="btn btn-gold" href="contact.html#brief">Discuss your project →</a></div></div></div></section>
<section class="alt"><div class="wrap">{sec_head("Project categories", "What our portfolio will cover.", "", True)}<div class="grid g3">{"".join('<div class="card rv">%s<h3>%s</h3></div>' % (icon(i), t) for i, t in cats)}</div></div></section>
<section><div class="wrap">{sec_head("Anatomy of a project page", "Every entry is evidence-led.", "", True)}
<table class="tbl rv">{"".join("<tr><td>%s</td><td>%s</td></tr>" % x for x in [("Project overview","Title, reference, category, and summary"),("Client context","Client requirement, with permission to disclose"),("Scope of work","Agreed services and responsibilities"),("Timeline","Start date, target completion, completion date"),("Execution","Activities performed and milestones"),("Evidence","Approved photographs, reports, and records"),("Outcomes","Verified deliverables and measurable results where available"),("Lessons","Relevant observations and improvements")])}</table></div></section>
{cta_band("Planning something similar?", "Tell us about your requirement and we will advise on the right service arrangement.")}'''
    page("projects.html", "Projects & Portfolio", "Projects and portfolio of CLAPET ASSET MANAGEMENT UG - SMC.", "projects.html", body,
         page_hero("Projects", "Our Projects. Our Process. Our Commitment.", "Genuine, documented examples of our work and operational capability."))

def build_properties():
    body = f'''<section><div class="wrap">{sec_head("Search", "Find property opportunities with better information.")}
<form class="filters rv" data-filter-form="#plist"><label>Location<input placeholder="District, town or area"></label><label>Property type<select><option>All</option><option>Land</option><option>Residential</option><option>Commercial</option><option>Agricultural</option></select></label><label>Land size<input placeholder="e.g. 50 x 100 ft / acres"></label><label>Price range<select><option>Any</option><option>Under UGX 50M</option><option>UGX 50M – 200M</option><option>UGX 200M – 500M</option><option>Above UGX 500M</option></select></label><label>Intended use<select><option>Any</option><option>Residential</option><option>Commercial</option><option>Farming</option></select></label><label>Verification<select><option>Any</option><option>Documents Received</option><option>Checks in Progress</option><option>Checks Completed Within Scope</option><option>Further Verification Required</option></select></label><button class="btn btn-navy btn-sm" style="align-self:end">Search</button></form>
<div id="plist" class="empty rv"><h3>Verified listings are published after review.</h3><p>Listings remain unpublished until an authorised staff member confirms the right to market the property. Check back soon, or tell us what you're looking for and we'll search on your behalf.</p><div class="btn-row" style="justify-content:center"><a class="btn btn-gold" href="contact.html#service=land-verification">Tell us what you need →</a></div></div></div></section>
<section class="alt"><div class="wrap split"><div class="rv"><span class="eyebrow">Listing standard</span><h2>What every listing shows.</h2>{checks(["Unique listing reference and general location","Land size and measurement unit; asking price if authorised","Intended or stated land use, access and utilities","Photographs and available document status","Verification status and date","Viewing request form and enquiry button"])}
{notice("Important.", "Uploaded documents do not mean land is legally cleared or free of disputes. Official search results, physical inspections and survey reports are represented accurately.")}</div>
<div class="listing rv" aria-label="Illustrative listing format"><div class="ph">{photo_or_scene("listing","land","Illustrative listing format")}</div><div class="body"><span class="chip gold">Checks Completed Within Scope</span><h3 style="margin-top:12px">Sample listing · CAM-L-0000</h3><dl><dt>Location</dt><dd>District, Uganda</dd><dt>Size</dt><dd>— acres</dd><dt>Use</dt><dd>Residential</dd><dt>Verified</dt><dd>Date shown here</dd></dl><a class="btn btn-navy btn-sm" href="contact.html#service=land-verification">Request a viewing</a></div></div></div></section>
<section><div class="wrap"><div class="form-card narrow rv" style="max-width:860px;margin:auto"><span class="eyebrow">Listing submission</span><h2>List your property with us</h2><p class="lead">Owners or authorised representatives can submit a property. It remains unpublished until reviewed.</p>
<form action="submit.php" method="post" data-ajax enctype="multipart/form-data"><input type="hidden" name="service" value="listing"><input class="hp" name="website" tabindex="-1" autocomplete="off"><div class="fgrid">
<label class="f">Full name<input name="full_name" required maxlength="120"></label><label class="f">Email<input type="email" name="email" required></label><label class="f">Telephone<input name="phone" type="tel"></label><label class="f">Property type<select name="property_type"><option>Land</option><option>Residential</option><option>Commercial</option><option>Agricultural</option></select></label><label class="f">Location<input name="location" required></label><label class="f">Asking price (UGX)<input name="asking_price"></label>
<label class="f full">Size and description<textarea name="description" required></textarea></label><label class="f full">Ownership / representation details<input name="ownership_details" required></label><label class="f full">Supporting documents (PDF, JPG, PNG — max 5 MB each)<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png"></label></div>
<div class="form-nav"><button class="btn btn-gold" type="submit">Submit listing</button></div><div class="form-msg" role="status"></div></form></div></div></section>'''
    page("properties.html", "Land & Properties", "Search verified land and property listings with explicit verification status.", "properties.html", body,
         page_hero("Land &amp; Properties", "Find Property Opportunities with Better Information.", "Explore land and property listings managed or marketed by Clapet, each with explicit verification status."))

def build_partners():
    cats = ["Construction and technical services","Property maintenance and facilities management","Land surveying and due diligence support","Agricultural and farm operations","General supplies and distribution","Logistics and delivery","Professional consultancy","Institutional service agreements","Joint bids and consortium arrangements where permitted"]
    body = f'''<section><div class="wrap split"><div class="rv"><span class="eyebrow">Partners &amp; affiliate operations</span><h2>Partner with the Standard of Trust.</h2>
<p class="lead">We welcome collaboration with qualified contractors, consultants, property professionals, surveyors, agricultural specialists, suppliers, distributors, and institutional partners.</p>
<p>Our open corporate infrastructure also supports regional brand creators and ambassadors — framing referral and lead-generation campaigns under clear, written agreements, with company ownership and direct control remaining with Clapet.</p></div>
<div class="rv"><h3>Partnership categories</h3>{checks(cats)}</div></div></section>
<section class="alt"><div class="wrap">{sec_head("Partner management workflow", "A fair, documented process.", "", True)}{steps([("Apply","Partner submits an application."),("Reference","The system creates a reference number."),("Review","An authorised officer reviews; due diligence is completed."),("Decision","Approved, rejected, or returned for clarification."),("Monitor","Approved partners are categorised; credentials monitored for expiry; performance reviewed.")])}
{notice("Please note.", "Registration does not automatically grant approved-vendor status or authorisation to represent the company.")}</div></section>
<section id="register"><div class="wrap"><div class="form-card rv" style="max-width:900px;margin:auto"><span class="eyebrow">Partnership enquiry</span><h2>Register your interest</h2>
<form action="submit.php" method="post" data-ajax enctype="multipart/form-data"><input type="hidden" name="service" value="partner"><input class="hp" name="website" tabindex="-1" autocomplete="off"><div class="fgrid">
<label class="f">Organisation name<input name="organisation" required></label><label class="f">Contact person<input name="full_name" required></label><label class="f">Email<input type="email" name="email" required></label><label class="f">Telephone<input type="tel" name="phone"></label>
<label class="f">Service category<select name="category">{"".join("<option>%s</option>" % c for c in cats)}</select></label><label class="f">Location<input name="location"></label>
<label class="f full">Relevant experience and references<textarea name="experience" required></textarea></label><label class="f full">Registration details<input name="registration_details"></label><label class="f full">Supporting documents (max 5 MB each)<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"></label></div>
<div class="form-nav"><button class="btn btn-gold" type="submit">Submit application</button></div><div class="form-msg" role="status"></div></form></div></div></section>'''
    page("partners.html", "Partners & Supplier Registration", "Partner with CLAPET ASSET MANAGEMENT UG - SMC: contractors, consultants, suppliers and institutional partners.", "partners.html", body,
         page_hero("Partners", "Building Strong Partnerships Through Reliable Execution.", "Partner with the Standard of Trust."))

def build_resources():
    cats = [("building","Property management guides"),("eye","Construction planning and supervision"),("map","Land verification and acquisition"),("leaf","Farm management and operations"),("box","Procurement and tendering"),("doc","Company news and announcements")]
    body = f'''<section><div class="wrap">{sec_head("Knowledge hub", "Guides to help you decide with confidence.", "Practical articles and checklists across property, construction, land, agriculture and procurement are published here as they are reviewed and approved.", True)}
<div class="grid g3">{"".join('<div class="card rv">%s<h3>%s</h3><p>Guides and checklists coming soon.</p></div>' % (icon(i), t) for i, t in cats)}</div></div></section>
<section class="alt" id="faqs"><div class="wrap narrow">{sec_head("FAQs", "Frequently asked questions", "", True)}{faq_html(FAQS)}</div></section>
{cta_band("Can't find your answer?", "Our team is happy to talk through your requirement.")}'''
    page("resources.html", "Resources, News & FAQs", "Property, construction, land, farm and procurement guidance plus frequently asked questions.", "resources.html", body,
         page_hero("Resources", "Resources, News &amp; FAQs", "Useful information that helps clients understand property, construction, land, agricultural, and procurement matters."))

def build_contact():
    opts = [("property-management","Property management","Rent, tenants, maintenance"),("construction-supervision","Construction supervision","Remote site monitoring"),("construction-contracts","Construction contracts","Build, renovate, consult"),("land-verification","Land verification","Due diligence, brokerage"),("farm-management","Farm management","Operations, land search"),("general-supplies","Supplies &amp; tenders","Procurement, quotations")]
    sf = lambda s, f: '<div class="svc-fields" data-svc="%s">%s</div>' % (s, f)
    def fld(name, label, full=False, ph=""):
        return '<label class="f%s">%s<input name="%s" placeholder="%s"></label>' % (" full" if full else "", label, name, ph)
    svc_fields = "".join([
     sf("property-management", fld("property_type","Property type") + fld("units","Number of units") + fld("occupancy","Current occupancy") + fld("maintenance_concerns","Known maintenance concerns", True)),
     sf("construction-supervision", fld("site_location","Site location") + fld("project_stage","Project stage") + fld("contractor","Contractor details") + fld("visit_frequency","Preferred visit frequency")),
     sf("construction-contracts", fld("project_type","Project type & intended use") + fld("drawings","Drawings available? (yes/no)") + fld("budget","Estimated budget") + fld("target_dates","Target start / completion")),
     sf("land-verification", fld("plot_reference","Plot / property reference") + fld("seller_details","Seller details") + fld("land_location","Location / district") + fld("documents_available","Documents available")),
     sf("farm-management", fld("land_size","Land size") + fld("activity","Crop or livestock activity") + fld("production_stage","Production stage") + fld("farm_location","Farm location")),
     sf("general-supplies", fld("tender_reference","Tender reference") + fld("quantities","Specifications & quantities") + fld("delivery_location","Delivery location") + fld("deadline","Submission deadline"))])
    body = f'''<section><div class="wrap"><div class="grid contact-grid">
<div class="rv"><span class="eyebrow">Contact</span><h2>Talk to the team.</h2><p class="lead">Whether you need help managing a property, supervising construction, verifying land, coordinating farm operations, or fulfilling a supply requirement, we are ready to understand your needs.</p>
<ul class="contact-list">
<li>{icon("phone")}<div><b>Telephone</b><span><a href="tel:{CONFIG["phone_raw"]}">{CONFIG["phone"]}</a></span></div></li>
<li>{icon("mail")}<div><b>Email</b><span><a href="mailto:{CONFIG["email"]}">{CONFIG["email"]}</a></span></div></li>
<li>{icon("map")}<div><b>Office</b><span>{CONFIG["address"]}</span></div></li>
<li>{icon("clock")}<div><b>Business hours</b><span>{CONFIG["hours"]}</span></div></li>
<li>{icon("users")}<div><b>WhatsApp</b><span><a href="{WA_URL}" target="_blank" rel="noopener">Chat with the team</a></span></div></li></ul></div>
<div class="form-card rv" id="brief"><span class="eyebrow">Submit a Project Brief</span><h2 style="font-size:1.6rem">Tell us about your requirement</h2>
<p style="color:var(--muted)">Five short steps. Your enquiry is saved securely and issued a reference number.</p>
<form id="brief-form" action="submit.php" method="post" enctype="multipart/form-data" novalidate><input type="hidden" name="form" value="brief"><input class="hp" name="website" tabindex="-1" autocomplete="off">
<div class="prog"><i class="on"></i><i></i><i></i><i></i><i></i></div><div class="stepttl">Step 1 of 5</div>
<div class="fstep on"><h3 style="margin-top:10px">Select a service</h3><div class="svc-pick">{"".join('<label><input type="radio" name="service" value="%s"><span>%s<small>%s</small></span></label>' % o for o in opts)}</div></div>
<div class="fstep"><h3>Provide your details</h3><div class="fgrid"><label class="f">Full name<input name="full_name" required maxlength="120"></label><label class="f">Organisation (if applicable)<input name="organisation"></label><label class="f">Email<input type="email" name="email" required></label><label class="f">Telephone<input type="tel" name="phone" pattern="[0-9+()\\-\\s]{{7,40}}"></label><label class="f full">Preferred contact method<select name="contact_method"><option>Email</option><option>Telephone</option><option>WhatsApp</option></select></label></div></div>
<div class="fstep"><h3>Describe the requirement</h3><div class="fgrid"><label class="f">Location<input name="location" required></label><label class="f">Timeline<input name="timeline" placeholder="e.g. start within 2 months"></label><label class="f full">Budget information (optional)<input name="budget_info"></label>{svc_fields}<label class="f full">Scope / description<textarea name="description" required></textarea></label></div></div>
<div class="fstep"><h3>Attach supporting files</h3><label class="f">Plans, specifications, photographs or documents (PDF, JPG, PNG, DOCX, XLSX · max 5 MB each · up to 5 files)<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"></label></div>
<div class="fstep"><h3>Review and submit</h3><div class="review"></div><label class="consent" style="margin-top:18px"><input type="checkbox" name="consent" required><span>I consent to {CONFIG["full"]} processing this information to respond to my enquiry, in line with the <a href="legal.html#privacy" target="_blank">privacy notice</a>.</span></label></div>
<div class="form-nav"><button type="button" class="btn btn-line btn-sm" data-prev>← Back</button><button type="button" class="btn btn-navy btn-sm" data-next>Continue →</button><button type="submit" class="btn btn-gold btn-sm">Submit brief</button></div>
<div class="form-msg" role="status"></div></form></div></div></div></section>
<section class="alt" id="consultation"><div class="wrap split"><div class="rv"><span class="eyebrow">Executive consultation</span><h2>Schedule an Executive Consultation</h2><p class="lead">Tell us a little first so we match you with the right lead. Then choose a convenient time.</p>{checks(["Availability selection with instant confirmation","Rescheduling and cancellation by link","Optional calendar integration"])}
<div class="booking" style="margin-top:24px"><b style="font-family:var(--font-primary)">Booking calendar</b><p style="margin:8px 0 0;color:var(--muted);font-size:.92rem">The Calendly / TidyCal booking calendar embeds here once the account is connected. Until then, submit the intake form and we will confirm a time.</p><!-- EMBED: paste Calendly/TidyCal inline widget here --></div></div>
<div class="form-card rv"><h3>Consultation intake</h3><form action="submit.php" method="post" data-ajax><input type="hidden" name="service" value="consultation"><input class="hp" name="website" tabindex="-1" autocomplete="off"><div class="fgrid">
<label class="f">Full name<input name="full_name" required></label><label class="f">Email<input type="email" name="email" required></label><label class="f">Telephone<input type="tel" name="phone"></label>
<label class="f">Project scope<select name="scope"><option>Property management</option><option>Construction supervision</option><option>Construction contract</option><option>Land verification</option><option>Farm management</option><option>Supplies / tender</option></select></label>
<label class="f">Budget range<select name="budget"><option>Under UGX 20M</option><option>UGX 20M – 100M</option><option>UGX 100M – 500M</option><option>Above UGX 500M</option><option>Not sure yet</option></select></label>
<label class="f">Timeline<select name="timeline"><option>Immediately</option><option>Within 1–3 months</option><option>3–6 months</option><option>Exploring</option></select></label>
<label class="f full">Preferred date / time<input name="preferred_time" placeholder="e.g. weekday mornings (EAT)"></label></div>
<div class="form-nav"><button class="btn btn-gold" type="submit">Request consultation</button></div><div class="form-msg" role="status"></div></form></div></div></section>'''
    page("contact.html", "Contact Us", "Contact CLAPET ASSET MANAGEMENT UG - SMC: submit a project brief or book an executive consultation.", "contact.html", body,
         page_hero("Contact Us", "Let's Discuss Your Next Project.", "Contact us to discuss the services required, the location, your timeline, and the most appropriate next steps."))

def build_portal():
    log = [("09:12","Cement 42.5R","48 bags","48 bags","✔ Match"),("09:40","Steel Y12","120 bars","120 bars","✔ Match"),("11:05","River sand","2 trips","2 trips","✔ Match"),("14:30","Bricks","4,000","3,850","⚠ Variance")]
    body = f'''<section><div class="wrap"><div class="split"><div class="rv"><span class="eyebrow">Client Portal</span><h2>Secure project updates, documents, reports, and service requests.</h2>
<p class="lead">A private area where clients view information related to their own projects, properties, and service engagements. Access is by invitation.</p>
{checks(["Projects with milestones, approved updates and reports","Properties, rent schedules and authorised statements","Documents authorised for your account only","Approvals for variations and quotations — recorded with timestamp","Service requests with live status"])}</div>
<div class="form-card rv signin"><h3>Sign in</h3><p style="color:var(--muted);font-size:.92rem">Portal accounts are created by Clapet after engagement. For access, contact your account manager.</p>
<form onsubmit="return false"><label class="f">Email<input type="email" disabled placeholder="you@example.com"></label><br><label class="f">Password<input type="password" disabled placeholder="••••••••"></label><div class="form-nav"><button class="btn btn-navy" disabled>Sign in (activated at launch)</button></div></form>
<p style="font-size:.8rem;color:var(--muted);margin-top:16px">Clients can only ever see their own records — permissions are verified on the server for every request.</p></div></div></div></section>
<section class="alt"><div class="wrap">{sec_head("Interface preview", "A look inside the dashboard.", "Illustrative mockup with sample data — not live client information.", True)}
<div class="portal rv"><div class="p-side"><div class="pb">CLAPET<small>CLIENT PORTAL</small></div><a class="on" href="#">Overview</a><a href="#">My Projects</a><a href="#">Properties</a><a href="#">Reports</a><a href="#">Documents</a><a href="#">Financial Records</a><a href="#">Approvals</a><a href="#">Service Requests</a><a href="#">Messages</a><a href="#">Profile</a></div>
<div class="p-main"><div class="p-top"><h3>Welcome back — Sample Client</h3><span class="chip ok">Protected</span></div>
<div class="kpis"><div class="kpi"><b>3</b><span>Active assignments</span></div><div class="kpi"><b>2</b><span>Pending approvals</span></div><div class="kpi"><b>5</b><span>New reports</span></div><div class="kpi"><b>1</b><span>Open requests</span></div></div>
<div class="pgrid"><div class="pbox"><h4>Material audit log · Plot 14 (sample)</h4><table class="log"><tr><th>Time</th><th>Item</th><th>Expected</th><th>Counted</th><th>Status</th></tr>{"".join("<tr>%s</tr>" % "".join("<td>%s</td>" % c for c in r) for r in log)}</table></div>
<div class="pbox"><h4>Milestone progress</h4><small>Foundation</small><div class="bar"><i style="width:100%"></i></div><small>Walling</small><div class="bar"><i style="width:62%"></i></div><small>Roofing</small><div class="bar"><i style="width:15%"></i></div><h4 style="margin-top:6px">Live media drops</h4><div class="media-row"><div>09:12</div><div>10:05</div><div>14:30</div></div></div></div></div></div></div></section>
{cta_band("Want portal access for your project?", "Portal access is included with eligible engagements. Tell us about your requirement.")}'''
    page("portal.html", "Client Portal", "Secure client portal preview: project updates, material audit logs, reports and approvals.", "portal.html", body,
         page_hero("Client Portal", "Client Portal", "Secure project updates, documents, reports, and service requests."))

def build_legal():
    body = f'''<section><div class="wrap narrow"><div class="rv" id="privacy"><span class="eyebrow">Privacy policy</span><h2>Privacy policy</h2>
<p>We collect the personal information you give us through forms, calls and messages — such as your name, contact details, project or property details and supporting documents — so that we can respond to your enquiry, deliver agreed services, and meet legal obligations.</p>
<p>Access is restricted to authorised staff. Uploaded documents are stored privately and are never published or indexed. We retain information only as long as needed for the purpose collected or as required by law, and you may request access, correction or deletion of your information by contacting us. Full policy text to be finalised with legal counsel before launch.</p></div>
<div class="rv" id="terms" style="margin-top:56px"><span class="eyebrow">Terms of service</span><h2>Terms of service</h2><p>Services are requested through a project brief and become binding only when a written proposal or agreement is accepted. Fees, expenses, approval limits, cancellation and dispute handling are set out in each agreement.</p></div>
<div class="rv" id="disclaimers" style="margin-top:56px"><span class="eyebrow">Disclaimers</span><h2>Service-specific disclaimers</h2>
{checks(["<b>Property management:</b> responsibilities and spending are limited to the owner's written authorisations.","<b>Construction supervision:</b> site monitoring is not regulated technical certification.","<b>Land due diligence &amp; brokerage:</b> verification has a defined scope; no guarantee of ownership, authenticity or a risk-free transaction.","<b>Farm management:</b> production estimates are not guarantees of yield or profit.","<b>Tenders &amp; supply:</b> eligibility and contract delivery are subject to verified credentials and internal approval."])}</div>
<div class="rv" id="cookies" style="margin-top:56px"><span class="eyebrow">Cookies &amp; complaints</span><h2>Cookie notice &amp; complaints procedure</h2><p>This website uses only essential storage. If you have a complaint, email <a href="mailto:{CONFIG["email"]}">{CONFIG["email"]}</a> quoting any reference number; we acknowledge complaints promptly and respond in writing.</p></div></div></section>'''
    page("legal.html", "Legal & Policies", "Privacy policy, terms of service, disclaimers and complaints procedure.", "legal.html", body,
         page_hero("Legal &amp; Policies", "Legal &amp; Policies", "Privacy, terms, disclaimers and complaints procedure."))

if __name__ == "__main__":
    os.makedirs(os.path.join(ROOT, "assets"), exist_ok=True)
    with open(os.path.join(ROOT, "assets", "favicon.svg"), "w", encoding="utf-8") as f:
        f.write(logo_mark().replace('<svg class="brand-mark"', '<svg xmlns="http://www.w3.org/2000/svg"'))
    build_home(); build_about(); build_services(); build_services_detail()
    build_projects(); build_properties(); build_partners(); build_resources()
    build_contact(); build_portal(); build_legal()
