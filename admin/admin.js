(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* ---------------- quick jump palette (Ctrl/Cmd+K) */
  var pal = $('#palette'), pq = $('#pq'), pl = $('#pl'), items = window.JUMP || [], sel = 0;
  function render() {
    var q = pq.value.toLowerCase(), list = items.filter(function (i) { return i[0].toLowerCase().indexOf(q) > -1; }).slice(0, 14);
    pl.innerHTML = '';
    list.forEach(function (i, n) {
      var li = document.createElement('li'), a = document.createElement('a');
      a.href = i[1]; a.textContent = i[0]; if (n === sel) li.className = 'on'; li.appendChild(a); pl.appendChild(li);
    });
    pl._n = list.length;
  }
  function openPal() { if (!pal) return; pal.hidden = false; pq.value = ''; sel = 0; render(); pq.focus(); }
  function closePal() { if (pal) pal.hidden = true; }
  if (pal) {
    var btn = $('#cmdk'); if (btn) btn.addEventListener('click', openPal);
    pq.addEventListener('input', function () { sel = 0; render(); });
    pq.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { sel = Math.min(sel + 1, pl._n - 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { sel = Math.max(sel - 1, 0); render(); e.preventDefault(); }
      else if (e.key === 'Enter') { var a = $('li.on a', pl); if (a) location.href = a.href; }
      else if (e.key === 'Escape') closePal();
    });
    pal.addEventListener('click', function (e) { if (e.target === pal) closePal(); });
    document.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openPal(); }
      else if (e.key === 'Escape') { closePal(); $$('.dlpop').forEach(function (p) { p.hidden = true; }); }
    });
  }

  /* ---------------- download menus */
  $$('[data-dl]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.stopPropagation();
      var pop = b.nextElementSibling;
      $$('.dlpop').forEach(function (p) { if (p !== pop) p.hidden = true; });
      pop.hidden = !pop.hidden;
    });
  });
  document.addEventListener('click', function () { $$('.dlpop').forEach(function (p) { p.hidden = true; }); });

  /* ---------------- date range applied to form downloads */
  var rf = $('#rf'), rt = $('#rt');
  function applyRange() {
    $$('a[data-dlr]').forEach(function (a) {
      var u = a.getAttribute('href').replace(/&(from|to)=[^&]*/g, '');
      if (rf && rf.value) u += '&from=' + rf.value;
      if (rt && rt.value) u += '&to=' + rt.value;
      a.setAttribute('href', u);
    });
  }
  if (rf) { rf.addEventListener('change', applyRange); rt.addEventListener('change', applyRange);
    $('#rclear').addEventListener('click', function () { rf.value = ''; rt.value = ''; applyRange(); }); }

  /* ---------------- text editor: find + unsaved marker */
  var q = $('#q'), f = $('#cform'), bar = $('#sbar');
  if (q) {
    q.addEventListener('input', function () {
      var v = q.value.toLowerCase();
      $$('.blk').forEach(function (b) {
        var fld = $('textarea,input', b);
        b.style.display = (!v || b.getAttribute('data-t').indexOf(v) > -1 || (fld && fld.value.toLowerCase().indexOf(v) > -1)) ? '' : 'none';
      });
      $$('.sec').forEach(function (s) { s.style.display = $('.blk:not([style*="none"])', s) ? '' : 'none'; });
    });
    f.addEventListener('input', function (e) { if (e.target !== q) bar.classList.add('changed'); });
    window.addEventListener('beforeunload', function (e) { if (bar.classList.contains('changed') && !f._saving) { e.preventDefault(); e.returnValue = ''; } });
    f.addEventListener('submit', function () { f._saving = true; });
  }

  /* ---------------- charts (inline SVG, no libraries) */
  var NS = 'http://www.w3.org/2000/svg';
  function el(tag, attrs, parent) {
    var e = document.createElementNS(NS, tag);
    for (var k in attrs) e.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(e);
    return e;
  }
  function niceMax(m) { if (m <= 4) return 4; if (m <= 8) return 8; var p = Math.pow(10, Math.floor(Math.log10(m))), n = m / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p; }

  function lineChart(box, series, opts) {
    opts = opts || {};
    var W = box.clientWidth || 600, H = box.clientHeight || 200, m = { l: 36, r: 12, t: 10, b: 24 };
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'none' }, box);
    var mx = niceMax(Math.max.apply(null, series.map(function (s) { return Math.max(s.v, s.u); }).concat([1])));
    var iw = W - m.l - m.r, ih = H - m.t - m.b, n = series.length;
    var X = function (i) { return m.l + (n === 1 ? iw / 2 : iw * i / (n - 1)); }, Y = function (v) { return m.t + ih - ih * v / mx; };
    for (var g = 0; g <= 4; g++) {
      var y = m.t + ih * g / 4;
      el('line', { x1: m.l, x2: W - m.r, y1: y, y2: y, stroke: '#e3e7ee', 'stroke-width': 1 }, svg);
      var t = el('text', { x: m.l - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 10, fill: '#5d6c82' }, svg); t.textContent = Math.round(mx * (1 - g / 4));
    }
    var step = Math.max(1, Math.ceil(n / 8));
    series.forEach(function (s, i) {
      if (i % step === 0 || i === n - 1) { var t2 = el('text', { x: X(i), y: H - 6, 'text-anchor': 'middle', 'font-size': 10, fill: '#5d6c82' }, svg); t2.textContent = s.d.slice(5); }
    });
    function path(key, color, area) {
      var d = series.map(function (s, i) { return (i ? 'L' : 'M') + X(i).toFixed(1) + ' ' + Y(s[key]).toFixed(1); }).join(' ');
      if (area) el('path', { d: d + ' L' + X(n - 1) + ' ' + (m.t + ih) + ' L' + X(0) + ' ' + (m.t + ih) + ' Z', fill: color, opacity: .12 }, svg);
      el('path', { d: d, fill: 'none', stroke: color, 'stroke-width': 2.2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round' }, svg);
    }
    path('v', '#1c3358', true); path('u', '#D4AF37', false);
    if (!opts.mini) {
      series.forEach(function (s, i) { if (s.f) { el('circle', { cx: X(i), cy: Y(Math.min(s.f, mx)), r: 4.5, fill: '#22a06b', stroke: '#fff', 'stroke-width': 1.5 }, svg); } });
      var tip = document.createElement('div'); tip.className = 'tip'; box.style.position = 'relative'; box.appendChild(tip);
      var hl = el('line', { y1: m.t, y2: m.t + ih, stroke: '#D4AF37', 'stroke-width': 1, opacity: 0 }, svg);
      box.addEventListener('mousemove', function (e) {
        var r = box.getBoundingClientRect(), x = (e.clientX - r.left) / r.width * W, i = Math.max(0, Math.min(n - 1, Math.round((x - m.l) / (iw / Math.max(1, n - 1)))));
        var s = series[i]; hl.setAttribute('x1', X(i)); hl.setAttribute('x2', X(i)); hl.setAttribute('opacity', 1);
        tip.innerHTML = '<b>' + s.d + '</b><br>Views ' + s.v + '<br>Visitors ' + s.u + (s.f ? '<br>Enquiries ' + s.f : '');
        tip.style.display = 'block'; tip.style.left = Math.min(r.width - 120, Math.max(0, X(i) / W * r.width + 10)) + 'px'; tip.style.top = '8px';
      });
      box.addEventListener('mouseleave', function () { tip.style.display = 'none'; hl.setAttribute('opacity', 0); });
    }
  }

  function barChart(box, vals, labels) {
    var W = box.clientWidth || 500, H = box.clientHeight || 150, m = { l: 6, r: 6, t: 8, b: 20 }, n = vals.length;
    var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H, preserveAspectRatio: 'none' }, box);
    var mx = Math.max.apply(null, vals.concat([1])), bw = (W - m.l - m.r) / n, ih = H - m.t - m.b;
    vals.forEach(function (v, i) {
      var h = ih * v / mx, x = m.l + i * bw + bw * .15;
      var r = el('rect', { x: x, y: m.t + ih - h, width: bw * .7, height: Math.max(v ? 2 : 0, h), rx: 3, fill: v === mx ? '#D4AF37' : '#1c3358' }, svg);
      var t = el('title', {}, r); t.textContent = labels[i] + ': ' + v;
      if (n <= 7 || i % 3 === 0) { var tx = el('text', { x: x + bw * .35, y: H - 5, 'text-anchor': 'middle', 'font-size': 10, fill: '#5d6c82' }, svg); tx.textContent = labels[i]; }
    });
  }

  function drawAll() {
    var mc = $('#mainChart'); if (mc) { mc.innerHTML = ''; lineChart(mc, JSON.parse(mc.getAttribute('data-series'))); }
    var mini = $('#miniChart'); if (mini) { mini.innerHTML = ''; lineChart(mini, JSON.parse(mini.getAttribute('data-series')), { mini: true }); }
    var hc = $('#hoursChart'); if (hc) { hc.innerHTML = ''; barChart(hc, JSON.parse(hc.getAttribute('data-v')), JSON.parse(hc.getAttribute('data-l'))); }
    var dc = $('#daysChart'); if (dc) { dc.innerHTML = ''; barChart(dc, JSON.parse(dc.getAttribute('data-v')), JSON.parse(dc.getAttribute('data-l'))); }
  }
  var rz; window.addEventListener('resize', function () { clearTimeout(rz); rz = setTimeout(drawAll, 150); });
  window.addEventListener('load', drawAll);
  if (document.readyState === 'complete') drawAll();
})();
