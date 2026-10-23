"""Makes every piece of visible website text editable from /admin.

The page builder passes each finished page through `make_editable`. Text-bearing elements are
replaced by `<?=t("page","id")?>` calls and their original text is recorded in inc/content-map.json.
Edits made in the admin are stored in storage/content.json and win over the original text.
"""
import re, json, html

VOID = {"br", "img", "input", "meta", "link", "hr", "source", "wbr"}
INLINE = {"a", "b", "i", "em", "strong", "span", "br", "small", "sup", "code"}
BLOCK_TAGS = {"h1", "h2", "h3", "h4", "p", "li", "td", "th", "summary", "blockquote", "cite", "dt", "dd", "a", "button"}
SPAN_CLASSES = {"eyebrow", "pillbadge", "from", "chip", "badge", "loc-card", "tag", "num"}
DIV_CLASSES = {"hero-motto", "foot-motto", "stepttl", "crumbs"}
SKIP_TREE = {"script", "style", "svg", "select", "textarea", "option", "head"}

TAG_RE = re.compile(r"<(/?)([a-zA-Z][a-zA-Z0-9]*)((?:\"[^\"]*\"|'[^']*'|[^'\">])*)>")
PHP_RE = re.compile(r"<\?.*?\?>", re.S)
LABELS = {"h1": "Heading", "h2": "Heading", "h3": "Subheading", "h4": "Subheading", "p": "Paragraph", "li": "List item",
          "td": "Table cell", "th": "Table heading", "summary": "Question", "blockquote": "Quote", "cite": "Credit",
          "dt": "Label", "dd": "Value", "a": "Link / button", "button": "Button", "span": "Label", "div": "Text", "meta": "Search engine"}


class Node:
    __slots__ = ("tag", "attrs", "start", "inner_start", "inner_end", "end", "parent", "kids")

    def __init__(self, tag, attrs, start, inner_start, parent):
        self.tag, self.attrs, self.start, self.inner_start = tag, attrs, start, inner_start
        self.inner_end = self.end = None
        self.parent, self.kids = parent, []


def _parse(src):
    root = Node("#root", "", 0, 0, None)
    root.inner_end = root.end = len(src)
    cur = root
    skip_until = None
    for m in TAG_RE.finditer(src):
        closing, tag, attrs = m.group(1), m.group(2).lower(), m.group(3)
        if skip_until:
            if closing and tag == skip_until:
                skip_until = None
            else:
                continue
        if closing:
            n = cur
            while n is not root and n.tag != tag:
                n = n.parent
            if n is not root:
                n.inner_end, n.end = m.start(), m.end()
                cur = n.parent
            continue
        node = Node(tag, attrs, m.start(), m.end(), cur)
        cur.kids.append(node)
        if tag in VOID or attrs.rstrip().endswith("/"):
            node.inner_start = node.inner_end = m.end()
            node.end = m.end()
            continue
        if tag in ("script", "style"):
            skip_until = tag
        cur = node
    return root


def _descendants(n):
    for k in n.kids:
        yield k
        yield from _descendants(k)


def _cls(n):
    m = re.search(r'class="([^"]*)"', n.attrs)
    return set(m.group(1).split()) if m else set()


def _eligible(n, src):
    if n.inner_end is None or n.inner_end <= n.inner_start:
        return False
    if n.tag in BLOCK_TAGS:
        pass
    elif n.tag == "span" and _cls(n) & SPAN_CLASSES:
        pass
    elif n.tag == "div" and _cls(n) & DIV_CLASSES:
        pass
    else:
        return False
    inner = src[n.inner_start:n.inner_end]
    if "\x00" in inner:
        return False
    if any(d.tag not in INLINE for d in _descendants(n)):
        return False
    text = re.sub(r"<[^>]+>", "", inner)
    return bool(re.search(r"[A-Za-z]{2}", text))


def _plain(s, n=70):
    t = html.unescape(re.sub(r"<[^>]+>", "", s))
    t = re.sub(r"\s+", " ", t).strip()
    return t if len(t) <= n else t[: n - 1] + "…"


def make_editable(doc, page, registry, global_registry, global_done):
    """Return doc with editable text replaced by t() calls; fill registries with the originals."""
    stash = []

    def hide(m):
        stash.append(m.group(0))
        return "\x00%d\x00" % (len(stash) - 1)

    def unhide(s):
        return re.sub(r"\x00(\d+)\x00", lambda m: stash[int(m.group(1))], s)

    src = PHP_RE.sub(hide, doc)
    src = re.sub(r"<!--.*?-->", "", src, flags=re.S)
    root = _parse(src)

    edits = []
    counters = {"page": 0, "global": 0}
    section = {"page": "Top of page", "global": "Menu & footer"}

    def in_global(n):
        while n is not None:
            cls = _cls(n) if n.tag != "#root" else set()
            if n.tag in ("header", "footer") or "topbar" in cls or "skip" in cls:
                return True
            n = n.parent
        return False

    def walk(n):
        for k in n.kids:
            if k.tag in SKIP_TREE:
                continue
            if _eligible(k, src):
                g = in_global(k)
                bucket = "global" if g else "page"
                if g and global_done:
                    # global blocks already registered from an earlier page: reuse ids by order
                    counters["global"] += 1
                    gid = "g%d" % counters["global"]
                    edits.append((k.inner_start, k.inner_end, '<?=t("global","%s")?>' % gid))
                else:
                    counters[bucket] += 1
                    bid = ("g%d" if g else "b%d") % counters[bucket]
                    inner = unhide(src[k.inner_start:k.inner_end]).strip()
                    reg = global_registry if g else registry
                    reg[bid] = {"t": LABELS.get(k.tag, "Text"), "l": _plain(inner), "d": inner,
                                "s": section["global" if g else "page"], "n": len(reg)}
                    edits.append((k.inner_start, k.inner_end, '<?=t("%s","%s")?>' % ("global" if g else page, bid)))
                if k.tag in ("h1", "h2") and not g:
                    section["page"] = _plain(src[k.inner_start:k.inner_end], 48)
                continue
            if k.tag in ("h1", "h2") and not in_global(k):
                section["page"] = _plain(src[k.inner_start:k.inner_end], 48)
            walk(k)

    walk(root)
    out = src
    for a, b, rep in sorted(edits, reverse=True):
        out = out[:a] + rep + out[b:]
    return unhide(out)


def write_map(path, content_map):
    with open(path, "w", encoding="utf8") as f:
        json.dump(content_map, f, ensure_ascii=False, indent=0)
