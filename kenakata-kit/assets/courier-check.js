/* Kenakata Kit: Courier check side panel on WooCommerce order screens. Server data is shown with textContent only. */
(function () {
	'use strict';
	var C = window.kkCC;
	if (!C || !window.fetch) { return; }
	var T = C.i18n || {};
	var panel, body, current = null, lastBtn = null;

	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) { e.className = cls; } if (text != null) { e.textContent = String(text); } return e; }
	function fmt(s) { var a = Array.prototype.slice.call(arguments, 1), i = 0; return String(s).replace(/%(\d\$)?[sd]/g, function () { return a[i++]; }); }

	function build() {
		panel = el('aside', 'kk-cc-panel'); panel.hidden = true; panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-label', T.title);
		var head = el('div', 'kk-cc-panel__head'); head.appendChild(el('h2', '', T.title));
		var x = el('button', 'kk-cc-x', '×'); x.type = 'button'; x.setAttribute('aria-label', T.close); x.addEventListener('click', close);
		head.appendChild(x); panel.appendChild(head);
		body = el('div', 'kk-cc-panel__body'); panel.appendChild(body);
		document.body.appendChild(panel);
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { close(); } });
	}
	function close() { panel.hidden = true; if (lastBtn) { lastBtn.focus(); } }

	function tile(n, label) { var t = el('div', 'kk-cc-tile'); t.appendChild(el('b', '', n)); t.appendChild(el('span', '', label)); return t; }
	function source(s) {
		var box = el('section', 'kk-cc-src');
		box.appendChild(el('h3', '', s.label + (s.ago ? ' · ' + fmt(T.checked, s.ago) : '')));
		var tiles = el('div', 'kk-cc-tiles');
		tiles.appendChild(tile(s.total, T.total)); tiles.appendChild(tile(s.success, T.delivered)); tiles.appendChild(tile(s.cancelled, T.returned));
		tiles.appendChild(tile(s.rate == null ? '—' : s.rate + '%', T.rate));
		box.appendChild(tiles);
		if (s.rate != null) { var bar = el('div', 'kk-cc-bar'), fill = el('i'); fill.style.width = Math.max(0, Math.min(100, s.rate)) + '%'; bar.appendChild(fill); box.appendChild(bar); }
		if (s.open) { box.appendChild(el('p', 'kk-cc-muted', T.open + ': ' + s.open)); }
		if (s.couriers && s.couriers.length) {
			box.appendChild(el('p', '', T.byCourier));
			var tb = el('table', 'kk-cc-table'), hr = el('tr');
			['', T.total, T.delivered, T.returned, T.rate].forEach(function (v) { hr.appendChild(el('th', '', v)); });
			tb.appendChild(hr);
			s.couriers.forEach(function (c) {
				var tr = el('tr');
				[c.name, c.total, c.success, c.cancelled, c.rate == null ? '—' : c.rate + '%'].forEach(function (v) { tr.appendChild(el('td', '', v)); });
				tb.appendChild(tr);
			});
			box.appendChild(tb);
		}
		if (s.reports && s.reports.length) {
			box.appendChild(el('p', '', T.reports));
			var ul = el('ul', 'kk-cc-reports');
			s.reports.forEach(function (r) { var li = el('li'); li.appendChild(el('strong', '', [r.courier, r.date].filter(Boolean).join(' · '))); li.appendChild(el('div', '', r.details)); ul.appendChild(li); });
			box.appendChild(ul);
		}
		return box;
	}
	function render(id, d) {
		body.textContent = '';
		var v = d.verdict || { level: 'new', text: '' };
		body.appendChild(el('p', 'kk-cc-verdict kk-cc-verdict--' + v.level, v.text));
		body.appendChild(el('p', 'kk-cc-muted', d.phone));
		(d.warnings || []).forEach(function (w) { body.appendChild(el('p', 'kk-cc-warn', w)); });
		(d.sources || []).forEach(function (s) { body.appendChild(source(s)); });
		var again = el('button', 'button', T.again); again.type = 'button';
		again.addEventListener('click', function () { load(id, true); });
		body.appendChild(again);
		updateButtons(id, d);
	}
	function updateButtons(id, d) {
		var best = null;
		(d.sources || []).forEach(function (s) { if ((s.success + s.cancelled) > (best ? best.success + best.cancelled : 0)) { best = s; } });
		var level = (d.verdict && d.verdict.level) || 'new';
		var text = best && best.rate != null ? best.rate + '%' : T['new'];
		Array.prototype.forEach.call(document.querySelectorAll('[data-kk-cc="' + id + '"]'), function (b) {
			b.className = b.className.replace(/\s?kk-cc-btn--\w+/g, '') + ' kk-cc-btn--' + level;
			b.textContent = fmt(T.parcels, text, best ? best.total : 0);
		});
	}
	function load(id, refresh) {
		current = id;
		body.textContent = ''; body.appendChild(el('p', 'kk-cc-muted', T.loading));
		panel.hidden = false;
		fetch(C.url + id + (refresh ? '?refresh=1' : ''), { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce } })
			.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
			.then(function (res) {
				if (current !== id) { return; }
				if (!res.ok) { body.textContent = ''; body.appendChild(el('p', 'kk-cc-warn', (res.j && res.j.message) || T.failed)); return; }
				render(id, res.j);
			})
			.catch(function () { if (current === id) { body.textContent = ''; body.appendChild(el('p', 'kk-cc-warn', T.failed)); } });
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-kk-cc]');
		if (!b) { return; }
		e.preventDefault(); e.stopPropagation(); // the orders list row is clickable: do not open the order
		if (!panel) { build(); }
		lastBtn = b;
		load(b.getAttribute('data-kk-cc'), false);
	}, true);
}());
