/*!
 * Kenakata Kit – Store dashboard (/dashboard). No libraries, no build step.
 * Screens: Home, Orders (list + detail), Products (list + edit/new, variations), Blog (list + edit/new).
 * Data: window.kkSD (from PHP) + /wp-json/kenakata/v1/sd/*. Text from the server is always inserted as text
 * (textContent); editor HTML is cleaned with an allowlist before it is shown.
 */
(function () {
	'use strict';

	var C = window.kkSD;
	var app = document.getElementById('sd-app');
	if (!C || !app) { return; }
	var T = C.i18n || {};
	function t(key) { return Object.prototype.hasOwnProperty.call(T, key) ? T[key] : key; }

	/* ------------------------------------------------------------------ helpers */

	/** Create an element. attrs: class, text, html (trusted static only), on{Event}, data-*, any attribute. */
	function h(tag, attrs) {
		var el = document.createElement(tag);
		var kids = Array.prototype.slice.call(arguments, 2);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				var v = attrs[k];
				if (v === null || v === undefined || v === false) { return; }
				if (k === 'class') { el.className = v; }
				else if (k === 'text') { el.textContent = v; }
				else if (k.slice(0, 2) === 'on' && typeof v === 'function') { el.addEventListener(k.slice(2).toLowerCase(), v); }
				else if (k === 'value' && ('value' in el)) { el.value = v; }
				else if (k === 'checked' || k === 'selected' || k === 'disabled') { el[k] = !!v; }
				else { el.setAttribute(k, v === true ? '' : String(v)); }
			});
		}
		appendList(el, kids);
		return el;
	}
	/** Append children (elements, strings, nested arrays; null/false skipped). */
	function appendList(el, kids) {
		kids.forEach(function (c) {
			if (c === null || c === undefined || c === false) { return; }
			if (Array.isArray(c)) { appendList(el, c); return; }
			el.appendChild(typeof c === 'object' ? c : document.createTextNode(String(c)));
		});
		return el;
	}
	function append(el) { return appendList(el, Array.prototype.slice.call(arguments, 1)); }
	function clear(el) { while (el.firstChild) { el.removeChild(el.firstChild); } return el; }

	var ICONS = {
		home: '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/>',
		orders: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
		products: '<path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
		blog: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
		more: '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
		phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		copy: '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>',
		back: '<path d="m15 5-7 7 7 7"/>',
		plus: '<path d="M12 5v14M5 12h14"/>',
		search: '<path d="m20 20-3.5-3.5"/><circle cx="11" cy="11" r="7"/>',
		ext: '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h5"/>',
		shop: '<path d="M4 9h16l-1-4H5z"/><path d="M5 9v10h14V9"/><path d="M10 19v-5h4v5"/>',
		logout: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
		wp: '<circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/>',
		user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		close: '<path d="M6 6l12 12M18 6 6 18"/>',
		image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 17-5-5-9 8"/>',
		warn: '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17h.01"/>',
		cartx: '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M3 4h2.5l2.2 10.2a1.5 1.5 0 0 0 1.5 1.2h8.2a1.5 1.5 0 0 0 1.5-1.1L21 8H7"/><path d="m11 9 4 4m0-4-4 4"/>',
		shield: '<path d="M12 3 4 6v6c0 4.5 3.2 8 8 9 4.8-1 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
		truck: '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		printer: '<path d="M7 9V3h10v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M7 14h10v7H7z"/>',
		sync: '<path d="M20 11a8 8 0 0 0-14.8-3.5M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.8 3.5M20 20v-4h-4"/>'
	};
	var WA = '<path fill="currentColor" stroke="none" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4a.4.4 0 0 0 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .1-1.2c0-.2-.2-.2-.5-.4Z"/>';
	function icon(name) {
		var span = document.createElement('span');
		span.className = 'sd-ico';
		span.setAttribute('aria-hidden', 'true');
		// Static, trusted SVG markup only (never server data).
		span.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + (name === 'whatsapp' ? WA : (ICONS[name] || '')) + '</svg>';
		return span;
	}

	function escUrl(u) { return /^(https?:|tel:|mailto:)/i.test(String(u || '')) ? String(u) : '#'; }

	var lang = C.lang || document.documentElement.lang || 'en';
	function ago(ts) {
		if (!ts) { return ''; }
		var diff = Math.round(ts - Date.now() / 1000);
		var abs = Math.abs(diff);
		if (abs < 60) { return t('justNow'); }
		try {
			var rtf = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });
			if (abs < 3600) { return rtf.format(Math.round(diff / 60), 'minute'); }
			if (abs < 86400) { return rtf.format(Math.round(diff / 3600), 'hour'); }
			if (abs < 86400 * 30) { return rtf.format(Math.round(diff / 86400), 'day'); }
		} catch (e) { /* old browser: fall through */ }
		return new Date(ts * 1000).toLocaleDateString(lang);
	}

	/* ------------------------------------------------------------------ toast */
	var toastBox = h('div', { class: 'sd-toasts', role: 'status', 'aria-live': 'polite' });
	function toast(msg, type) {
		var el = h('div', { class: 'sd-toast' + (type ? ' sd-toast--' + type : ''), text: msg });
		toastBox.appendChild(el);
		setTimeout(function () { el.classList.add('is-out'); }, 3200);
		setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 3700);
	}

	/* ------------------------------------------------------------------ API */
	function apiUrl(path) {
		var parts = String(path).split('?');
		var u = C.rest + parts[0];
		if (parts[1]) { u += (u.indexOf('?') === -1 ? '?' : '&') + parts[1]; }
		return u;
	}
	function api(path, opts) {
		opts = opts || {};
		var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce, Accept: 'application/json' } };
		if (opts.form) { init.body = opts.form; }
		else if (opts.body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
		if (!navigator.onLine) { return Promise.reject(new Error(t('offline'))); }
		return fetch(opts.root ? opts.root + path : apiUrl(path), init).then(function (r) {
			return r.text().then(function (txt) {
				var data = null;
				try { data = txt ? JSON.parse(txt) : null; } catch (e) { data = null; }
				if (r.ok) { return data; }
				var code = data && data.code ? data.code : '';
				var msg = data && data.message ? data.message : t('error');
				if (code === 'rest_cookie_invalid_nonce' || r.status === 401) { msg = t('sessionEnded'); }
				else if (r.status === 403 && code.indexOf('kk_sd_') !== 0) { msg = t('noAccess'); }
				var err = new Error(msg);
				err.status = r.status;
				err.code = code;
				throw err;
			});
		}, function () { throw new Error(t('offline')); });
	}
	function qs(obj) {
		return Object.keys(obj).filter(function (k) { return obj[k] !== '' && obj[k] !== null && obj[k] !== undefined; })
			.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]); }).join('&');
	}

	/* ------------------------------------------------------------------ unsaved changes */
	var dirty = false;
	function setDirty(v) { dirty = !!v; }
	window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

	/* ------------------------------------------------------------------ HTML cleaning for the editor */
	var ALLOWED = { P: 1, BR: 1, H2: 1, H3: 1, H4: 1, STRONG: 1, B: 1, EM: 1, I: 1, U: 1, UL: 1, OL: 1, LI: 1, A: 1, BLOCKQUOTE: 1, IMG: 1, FIGURE: 1, FIGCAPTION: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TH: 1, TD: 1, HR: 1, SPAN: 1 };
	var DROP = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, FORM: 1, INPUT: 1, BUTTON: 1, TEXTAREA: 1, SELECT: 1, LINK: 1, META: 1, SVG: 1, MATH: 1, NOSCRIPT: 1, TEMPLATE: 1 };
	function cleanNode(node, doc) {
		var out = doc.createDocumentFragment();
		Array.prototype.forEach.call(node.childNodes, function (n) {
			if (n.nodeType === 3) { out.appendChild(doc.createTextNode(n.nodeValue)); return; }
			if (n.nodeType !== 1 || DROP[n.nodeName]) { return; }
			var inner = cleanNode(n, doc);
			var tag = n.nodeName === 'DIV' ? 'P' : n.nodeName; // browsers make <div> lines while typing: keep them as paragraphs
			if (!ALLOWED[tag]) { out.appendChild(inner); return; } // unwrap unknown tags, keep their text
			var el = doc.createElement(tag.toLowerCase());
			if (n.nodeName === 'A') {
				var href = n.getAttribute('href') || '';
				if (/^(https?:|mailto:|tel:|\/|#)/i.test(href)) { el.setAttribute('href', href); }
				if (n.getAttribute('target') === '_blank') { el.setAttribute('target', '_blank'); el.setAttribute('rel', 'noopener'); }
			} else if (n.nodeName === 'IMG') {
				var src = n.getAttribute('src') || '';
				if (!/^(https?:|\/)/i.test(src)) { return; }
				el.setAttribute('src', src);
				if (n.getAttribute('alt')) { el.setAttribute('alt', n.getAttribute('alt')); }
			}
			el.appendChild(inner);
			out.appendChild(el);
		});
		return out;
	}
	function cleanHTML(html) {
		var parsed = new DOMParser().parseFromString('<!doctype html><body>' + String(html || ''), 'text/html');
		return cleanNode(parsed.body, document);
	}

	/* ------------------------------------------------------------------ form pieces */
	var uid = 0;
	function field(label, input, help) {
		var id = input.id || ('sd-f' + (++uid));
		input.id = id;
		return h('div', { class: 'sd-field' },
			h('label', { class: 'sd-label', for: id, text: label }),
			input,
			help ? h('p', { class: 'sd-help', text: help }) : null);
	}
	function input(attrs) {
		var el = h('input', Object.assign({ class: 'sd-input', type: 'text' }, attrs || {}));
		el.addEventListener('input', function () { setDirty(true); });
		return el;
	}
	function textarea(attrs, value) {
		var el = h('textarea', Object.assign({ class: 'sd-input sd-textarea' }, attrs || {}));
		el.value = value || '';
		el.addEventListener('input', function () { setDirty(true); });
		return el;
	}
	function select(options, value, attrs) {
		var el = h('select', Object.assign({ class: 'sd-input' }, attrs || {}));
		options.forEach(function (o) { el.appendChild(h('option', { value: o[0], text: o[1], selected: String(o[0]) === String(value) })); });
		el.addEventListener('change', function () { setDirty(true); });
		return el;
	}
	function toggle(label, checked, onChange) {
		var box = h('input', { type: 'checkbox', class: 'sd-switch__input', checked: checked });
		box.addEventListener('change', function () { setDirty(true); if (onChange) { onChange(box.checked); } });
		return { el: h('label', { class: 'sd-switch' }, box, h('span', { class: 'sd-switch__track', 'aria-hidden': 'true' }), h('span', { text: label })), input: box };
	}
	function button(label, attrs, ico) {
		return h('button', Object.assign({ type: 'button', class: 'sd-btn' }, attrs || {}), ico ? icon(ico) : null, h('span', { text: label }));
	}
	function busy(btn, on, label) {
		btn.disabled = on;
		btn.classList.toggle('is-busy', on);
		var s = btn.querySelector('span:last-child');
		if (s) {
			if (on) { btn.dataset.label = s.textContent; s.textContent = label || t('saving'); }
			else if (btn.dataset.label) { s.textContent = btn.dataset.label; }
		}
	}
	function card(title, kids, extraClass) {
		return h('section', { class: 'sd-card' + (extraClass ? ' ' + extraClass : '') }, title ? h('h2', { class: 'sd-card__title', text: title }) : null, kids);
	}
	function badge(status, label) { return h('span', { class: 'sd-badge sd-badge--' + status, text: label }); }
	function copyText(text) {
		var done = function () { toast(t('copied'), 'ok'); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
		} else { fallbackCopy(text); done(); }
	}
	function fallbackCopy(text) {
		var ta = h('textarea', { class: 'sd-offscreen', 'aria-hidden': 'true' });
		ta.value = text;
		document.body.appendChild(ta);
		ta.select();
		try { document.execCommand('copy'); } catch (e) { /* ignore */ }
		document.body.removeChild(ta);
	}
	function loading() { return h('div', { class: 'sd-loading', text: t('loading') }); }
	function errorBox(err, retry) {
		return h('div', { class: 'sd-error', role: 'alert' }, h('p', { text: err && err.message ? err.message : t('error') }),
			retry ? button(t('retry'), { class: 'sd-btn sd-btn--ghost', onclick: retry }) : null);
	}
	function empty() { return h('div', { class: 'sd-empty', text: t('nothing') }); }

	/** Rich text editor (headings, bold, italic, lists, link, picture). Returns { el, get(), set(html) }. */
	function editor(html, opts) {
		opts = opts || {};
		var area = h('div', { class: 'sd-editor__area', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true', 'aria-label': opts.label || t('content') });
		area.appendChild(cleanHTML(html));
		area.addEventListener('focus', function () { try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* ignore */ } });
		area.addEventListener('input', function () { setDirty(true); });
		area.addEventListener('paste', function (e) {
			// Paste as clean HTML (Word/Google Docs styles removed).
			var data = e.clipboardData;
			if (!data) { return; }
			e.preventDefault();
			var htmlData = data.getData('text/html');
			var frag = htmlData ? cleanHTML(htmlData) : document.createTextNode(data.getData('text/plain'));
			var sel = window.getSelection();
			if (!sel.rangeCount) { area.appendChild(frag); return; }
			var range = sel.getRangeAt(0);
			range.deleteContents();
			range.insertNode(frag);
			range.collapse(false);
			setDirty(true);
		});
		var saved = null;
		function keep() { var s = window.getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) { saved = s.getRangeAt(0).cloneRange(); } }
		function restore() { area.focus(); if (saved) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(saved); } }
		area.addEventListener('keyup', keep);
		area.addEventListener('mouseup', keep);
		area.addEventListener('blur', keep);
		function cmd(name, value) { restore(); try { document.execCommand(name, false, value); } catch (e) { /* ignore */ } keep(); setDirty(true); }
		var tools = [
			[t('paragraph'), 'P', function () { cmd('formatBlock', '<p>'); }],
			[t('heading') + ' 2', 'H2', function () { cmd('formatBlock', '<h2>'); }],
			[t('heading') + ' 3', 'H3', function () { cmd('formatBlock', '<h3>'); }],
			[t('bold'), 'B', function () { cmd('bold'); }],
			[t('italic'), 'I', function () { cmd('italic'); }],
			[t('list'), '•', function () { cmd('insertUnorderedList'); }],
			[t('olist'), '1.', function () { cmd('insertOrderedList'); }],
			[t('link'), '🔗', function () {
				var url = window.prompt(t('linkPrompt'), 'https://');
				if (url && /^(https?:|mailto:|tel:|\/)/i.test(url)) { cmd('createLink', url); }
			}]
		];
		var bar = h('div', { class: 'sd-editor__bar', role: 'toolbar' });
		tools.forEach(function (tool) {
			var b = h('button', { type: 'button', class: 'sd-editor__btn', title: tool[0], 'aria-label': tool[0], text: tool[1] });
			b.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the text selection
			b.addEventListener('click', tool[2]);
			bar.appendChild(b);
		});
		if (opts.images && C.caps.upload) {
			var file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', class: 'sd-offscreen', tabindex: '-1' });
			var pic = h('button', { type: 'button', class: 'sd-editor__btn', title: t('insertImage'), 'aria-label': t('insertImage') }, icon('image'));
			pic.addEventListener('mousedown', function (e) { e.preventDefault(); keep(); });
			pic.addEventListener('click', function () { file.click(); });
			file.addEventListener('change', function () {
				if (!file.files[0]) { return; }
				toast(t('uploading'));
				upload(file.files[0]).then(function (m) { cmd('insertImage', m.url); }, function (err) { toast(err.message, 'bad'); });
				file.value = '';
			});
			bar.appendChild(pic);
			bar.appendChild(file);
		}
		return {
			el: h('div', { class: 'sd-editor' + (opts.small ? ' sd-editor--small' : '') }, bar, area),
			get: function () {
				var copy = document.createElement('div');
				copy.appendChild(cleanHTML(area.innerHTML));
				wrapLoose(copy);
				return copy.innerHTML.replace(/<p><br><\/p>/g, '').trim();
			}
		};
	}

	/** Put text and inline tags that sit directly in the editor (e.g. the first typed line) into <p> paragraphs. */
	var INLINE = { STRONG: 1, B: 1, EM: 1, I: 1, U: 1, A: 1, SPAN: 1, BR: 1, IMG: 1 };
	function wrapLoose(box) {
		var p = null;
		Array.prototype.slice.call(box.childNodes).forEach(function (n) {
			var inline = n.nodeType === 3 || (n.nodeType === 1 && INLINE[n.nodeName]);
			if (!inline) { p = null; return; }
			if (n.nodeType === 3 && !n.nodeValue.trim() && !p) { box.removeChild(n); return; }
			if (!p) { p = document.createElement('p'); box.insertBefore(p, n); }
			p.appendChild(n);
		});
	}

	function upload(file) {
		var fd = new FormData();
		fd.append('file', file, file.name);
		return api('media', { method: 'POST', form: fd });
	}

	/* ------------------------------------------------------------------ layout */
	var NAV = [
		['', 'home', t('home'), true],
		['orders', 'orders', t('orders'), C.caps.orders],
		['products', 'products', t('products'), C.caps.products],
		['posts', 'blog', t('blog'), C.caps.posts]
	].filter(function (n) { return n[3]; });

	var main = h('main', { class: 'sd-main', id: 'sd-main', tabindex: '-1' });
	var sideNav = h('nav', { class: 'sd-nav', 'aria-label': t('menu') });
	var bottomNav = h('nav', { class: 'sd-bottom', 'aria-label': t('menu') });
	var sheet = h('div', { class: 'sd-sheet', hidden: true });

	var incBadges = [];
	function setIncomplete(n) { incBadges.forEach(function (b) { b.textContent = String(n); b.hidden = !n; }); }
	function incompleteLink() {
		if (!C.incomplete) { return null; }
		var badge = h('b', { class: 'sd-count', hidden: true }); incBadges.push(badge);
		return h('a', { class: 'sd-nav__item', href: '#/incomplete', 'data-route': 'incomplete' }, icon('cartx'), h('span', { text: t('incomplete') }), badge);
	}
	function extraLinks() {
		return [
			incompleteLink(),
			h('a', { class: 'sd-nav__item', href: escUrl(C.home), target: '_blank', rel: 'noopener' }, icon('shop'), h('span', { text: t('viewSite') })),
			C.adminUrl ? h('a', { class: 'sd-nav__item', href: escUrl(C.adminUrl) }, icon('wp'), h('span', { text: t('wpAdmin') })) : null,
			!C.adminUrl && C.profileUrl ? h('a', { class: 'sd-nav__item', href: escUrl(C.profileUrl) }, icon('user'), h('span', { text: t('profile') })) : null,
			h('a', { class: 'sd-nav__item', href: escUrl(C.logoutUrl) }, icon('logout'), h('span', { text: t('logout') }))
		];
	}

	function shell() {
		clear(app);
		var brand = h('a', { class: 'sd-brand', href: '#/' },
			C.logo ? h('img', { src: escUrl(C.logo), alt: '', class: 'sd-brand__logo' }) : h('span', { class: 'sd-brand__mark', text: (C.site || 'S').charAt(0) }),
			h('span', { class: 'sd-brand__name', text: C.site }));
		NAV.forEach(function (n) {
			sideNav.appendChild(h('a', { class: 'sd-nav__item', href: '#/' + n[0], 'data-route': n[0] }, icon(n[1]), h('span', { text: n[2] })));
			bottomNav.appendChild(h('a', { class: 'sd-bottom__item', href: '#/' + n[0], 'data-route': n[0] }, icon(n[1]), h('span', { text: n[2] })));
		});
		var moreBtn = h('button', { type: 'button', class: 'sd-bottom__item', 'aria-haspopup': 'true' }, icon('more'), h('span', { text: t('more') }));
		bottomNav.appendChild(moreBtn);
		append(sheet, h('div', { class: 'sd-sheet__panel', role: 'dialog', 'aria-label': t('menu') },
			h('p', { class: 'sd-sheet__user', text: C.user.name }), extraLinks(),
			button(t('close'), { class: 'sd-btn sd-btn--ghost sd-sheet__close', onclick: function () { sheet.hidden = true; } })));
		sheet.addEventListener('click', function (e) { if (e.target === sheet) { sheet.hidden = true; } });
		moreBtn.addEventListener('click', function () { sheet.hidden = false; });

		append(app,
			h('aside', { class: 'sd-side' }, brand, sideNav, h('div', { class: 'sd-side__foot' }, h('p', { class: 'sd-side__user', text: C.user.name }), extraLinks())),
			h('header', { class: 'sd-top' }, brand.cloneNode(true)),
			main, bottomNav, sheet, toastBox);
	}

	function setActive(route) {
		Array.prototype.forEach.call(document.querySelectorAll('[data-route]'), function (a) {
			var on = a.getAttribute('data-route') === route;
			a.classList.toggle('is-active', on);
			if (on) { a.setAttribute('aria-current', 'page'); } else { a.removeAttribute('aria-current'); }
		});
	}

	function pageHead(title, opts) {
		opts = opts || {};
		return h('div', { class: 'sd-head' },
			opts.back ? h('a', { class: 'sd-back', href: opts.back }, icon('back'), h('span', { text: t('back') })) : null,
			h('div', { class: 'sd-head__row' }, h('h1', { class: 'sd-h1', text: title }), opts.action || null),
			opts.sub ? h('p', { class: 'sd-sub', text: opts.sub }) : null);
	}

	/* ------------------------------------------------------------------ router */
	var current = '';
	var skipHash = false;
	function parseHash() {
		var raw = (location.hash || '#/').replace(/^#\/?/, '');
		var parts = raw.split('?');
		var params = {};
		(parts[1] || '').split('&').forEach(function (p) {
			if (!p) { return; }
			var kv = p.split('=');
			params[decodeURIComponent(kv[0])] = decodeURIComponent((kv[1] || '').replace(/\+/g, ' '));
		});
		return { path: parts[0].split('/').filter(Boolean), params: params, raw: raw };
	}
	function go(hash) { location.hash = '#/' + hash; }

	function route() {
		if (skipHash) { skipHash = false; return; }
		var r = parseHash();
		if (dirty && r.raw !== current) {
			if (!window.confirm(t('unsaved'))) { skipHash = true; location.hash = '#/' + current; return; }
		}
		setDirty(false);
		current = r.raw;
		sheet.hidden = true;
		var section = r.path[0] || '';
		setActive(section);
		clear(main);
		main.appendChild(loading());
		window.scrollTo(0, 0);
		var id = r.path[1];
		var view;
		if (section === '') { view = viewHome(); }
		else if (section === 'orders' && C.caps.orders) { view = id ? viewOrder(id) : viewOrders(r.params); }
		else if (section === 'products' && C.caps.products) { view = id ? viewProduct(id === 'new' ? 0 : id) : viewProducts(r.params); }
		else if (section === 'incomplete' && C.incomplete) { view = viewIncomplete(r.params); }
		else if (section === 'posts' && C.caps.posts) { view = id ? viewPost(id === 'new' ? 0 : id) : viewPosts(r.params); }
		else { view = Promise.resolve(h('div', {}, pageHead(t('notFound')), h('p', {}, h('a', { href: '#/', text: t('home') })))); }
		Promise.resolve(view).then(function (el) {
			if (parseHash().raw !== r.raw) { return; } // user moved on
			clear(main);
			main.appendChild(el);
			main.focus({ preventScroll: true });
		}, function (err) {
			clear(main);
			main.appendChild(errorBox(err, route));
		});
	}

	/* ------------------------------------------------------------------ Home */
	var summaryCache = null;
	function getSummary(force) {
		if (summaryCache && !force) { return Promise.resolve(summaryCache); }
		return api('summary').then(function (s) { summaryCache = s; return s; });
	}

	/* ------------------------------------------------------------------ home insights (load on tap) */
	var SVGNS = 'http://www.w3.org/2000/svg';
	function svgEl(tag, attrs) {
		var el = document.createElementNS(SVGNS, tag);
		Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, String(attrs[k])); });
		for (var i = 2; i < arguments.length; i++) { if (arguments[i]) { el.appendChild(arguments[i]); } }
		return el;
	}
	function shortDate(iso) { var p = String(iso).split('-'); return p.length === 3 ? Number(p[2]) + '/' + Number(p[1]) : String(iso); }

	/** One collapsible block. load(body) is called the first time it is opened, never before. */
	function insightBlock(title, load) {
		var loaded = false;
		var body = h('div', { class: 'sd-insight__body', hidden: true });
		var hint = h('span', { text: t('showIt') });
		var head = h('button', { type: 'button', class: 'sd-insight__head', 'aria-expanded': 'false' }, h('span', { text: title }), hint);
		head.addEventListener('click', function () {
			var open = body.hidden;
			body.hidden = !open;
			head.setAttribute('aria-expanded', open ? 'true' : 'false');
			hint.textContent = open ? t('hideIt') : t('showIt');
			if (open && !loaded) { loaded = true; load(body, function () { loaded = false; }); }
		});
		return h('div', { class: 'sd-insight' }, head, body);
	}
	function loadInto(body, path, draw, reset) {
		clear(body).appendChild(loading());
		api(path).then(function (data) { clear(body); draw(body, data); }, function (err) {
			clear(body).appendChild(errorBox(err, function () { reset(); loadInto(body, path, draw, reset); }));
		});
	}

	function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
	/** Report periods: [key, label, from, to]. */
	function periods() {
		var now = new Date(), y = now.getFullYear(), m = now.getMonth(), d = now.getDate();
		var day = function (offset) { return ymd(new Date(y, m, d - offset)); };
		return [
			['today', t('today'), day(0), day(0)],
			['yesterday', t('yesterday'), day(1), day(1)],
			['last7', t('last7'), day(6), day(0)],
			['last30', t('last30'), day(29), day(0)],
			['month', t('thisMonth'), ymd(new Date(y, m, 1)), day(0)],
			['lastmonth', t('lastMonth'), ymd(new Date(y, m - 1, 1)), ymd(new Date(y, m, 0))]
		];
	}
	function drawTrend(body, data, switchTo, state) {
		clear(body);
		state = state || {};
		var seg = h('div', { class: 'sd-seg sd-seg--wrap', role: 'group', 'aria-label': t('period') }, periods().map(function (p) {
			return h('button', { type: 'button', class: state.key === p[0] ? 'is-active' : '', onclick: function () { switchTo({ key: p[0], from: p[2], to: p[3] }); } }, p[1]);
		}).concat([h('button', { type: 'button', class: state.key === 'custom' ? 'is-active' : '', onclick: function () { custom.hidden = !custom.hidden; } }, t('customRange'))]));
		var fromIn = h('input', { type: 'date', class: 'sd-input', value: state.from || '', 'aria-label': t('dateFrom') });
		var toIn = h('input', { type: 'date', class: 'sd-input', value: state.to || '', 'aria-label': t('dateTo') });
		var custom = h('div', { class: 'sd-range', hidden: state.key !== 'custom' }, fromIn, h('span', { text: '→' }), toIn,
			button(t('show'), { class: 'sd-btn sd-btn--sm sd-btn--primary', onclick: function () { if (fromIn.value && toIn.value) { switchTo({ key: 'custom', from: fromIn.value, to: toIn.value }); } } }));
		body.appendChild(seg);
		body.appendChild(custom);
		if (data.from) { body.appendChild(h('p', { class: 'sd-note', text: (data.from === data.to ? data.from : data.from + ' → ' + data.to) })); }
		body.appendChild(h('dl', { class: 'sd-kpis' },
			h('div', null, h('dt', { text: t('sales') }), h('dd', { text: data.sales })),
			h('div', null, h('dt', { text: t('orders') }), h('dd', { text: String(data.orders) })),
			h('div', null, h('dt', { text: t('avgOrder') }), h('dd', { text: data.avg })),
			data.items !== undefined ? h('div', null, h('dt', { text: t('itemsSold') }), h('dd', { text: String(data.items) })) : null));
		if (!data.orders) { body.appendChild(h('p', { class: 'sd-note', text: t('noSales') })); return; }
		if (data.series.length < 2) { return; }
		var W = 600, H = 170, base = 140, n = data.series.length, step = (W - 16) / n, bw = Math.max(3, step * 0.68);
		var max = 0;
		data.series.forEach(function (r) { if (r.sales > max) { max = r.sales; } });
		var svg = svgEl('svg', { class: 'sd-chart', viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': t('chartLabel') + ': ' + data.sales });
		data.series.forEach(function (r, i) {
			var bh = max ? Math.max(r.sales > 0 ? 3 : 0, (r.sales / max) * (base - 10)) : 0;
			var x = 8 + i * step + (step - bw) / 2;
			var bar = svgEl('rect', { class: 'sd-chart__bar' + (i === n - 1 ? ' is-today' : ''), x: x.toFixed(1), y: (base - bh).toFixed(1), width: bw.toFixed(1), height: bh.toFixed(1), rx: 2 });
			bar.appendChild(svgEl('title', {}, document.createTextNode(shortDate(r.d) + ': ' + r.text + ' (' + r.orders + ')')));
			svg.appendChild(bar);
		});
		[0, Math.floor((n - 1) / 2), n - 1].forEach(function (i, k) {
			var label = svgEl('text', { class: 'sd-chart__axis', x: (8 + i * step + step / 2).toFixed(1), y: H - 8, 'text-anchor': k === 0 ? 'start' : (k === 2 ? 'end' : 'middle') });
			label.appendChild(document.createTextNode(shortDate(data.series[i].d)));
			svg.appendChild(label);
		});
		body.appendChild(svg);
		if (data.truncated) { body.appendChild(h('p', { class: 'sd-note', text: t('approxOrders') })); }
	}

	function drawTop(body, data) {
		if (!data.items.length) { body.appendChild(h('p', { class: 'sd-note', text: t('noSales') })); return; }
		body.appendChild(h('p', { class: 'sd-note', text: t('last30') }));
		body.appendChild(h('ol', { class: 'sd-toplist' }, data.items.map(function (it, i) {
			return h('li', null, h('span', { class: 'sd-toplist__rank', text: String(i + 1) }),
				h('span', { class: 'sd-toplist__name', text: it.name }),
				h('span', { class: 'sd-toplist__meta', text: it.qty + ' ' + t('sold') + ' · ' + it.total }));
		})));
		if (data.truncated) { body.appendChild(h('p', { class: 'sd-note', text: t('approxOrders') })); }
	}

	function drawCod(body, data) {
		if (!data.available) { body.appendChild(h('p', { class: 'sd-note', text: t('noShippedStatus') })); return; }
		body.appendChild(h('dl', { class: 'sd-kpis' },
			h('div', null, h('dt', { text: t('toCollect') }), h('dd', { text: data.amount })),
			h('div', null, h('dt', { text: t('parcelsOut') }), h('dd', { text: String(data.orders) })),
			h('div', null, h('dt', { text: t('oldestParcel') }), h('dd', { text: String(data.oldest_days) }))));
		body.appendChild(h('p', { class: 'sd-note', text: t('cashHelp') }));
		body.appendChild(h('a', { class: 'sd-btn', href: '#/orders?status=shipped' }, icon('truck'), h('span', { text: t('orders') })));
	}

	function insightsCard() {
		var trend = insightBlock(t('salesReport'), function (body, reset) {
			var show = function (st) {
				loadInto(body, 'report/range?' + qs({ from: st.from, to: st.to }), function (b, data) { drawTrend(b, data, show, st); }, reset);
			};
			var p = periods()[2];
			show({ key: p[0], from: p[2], to: p[3] });
		});
		var top = insightBlock(t('topProducts'), function (body, reset) { loadInto(body, 'report/top', drawTop, reset); });
		var cod = insightBlock(t('cashAtCourier'), function (body, reset) { loadInto(body, 'report/cod', drawCod, reset); });
		return card(t('insights'), h('div', { class: 'sd-insights' }, h('p', { class: 'sd-insights__help', text: t('insightsHelp') }), trend, top, cod));
	}

	function viewHome() {
		return getSummary(true).then(function (s) {
			var root = h('div', { class: 'sd-view' }, pageHead(t('hello') + ', ' + C.user.name));
			if (s.periods) {
				root.appendChild(h('div', { class: 'sd-stats' }, ['today', 'last7', 'last30'].map(function (k) {
					var p = s.periods[k];
					var money = p.sales !== undefined; // amounts are hidden for people without permission: show order counts only
					return h('div', { class: 'sd-stat' },
						h('span', { class: 'sd-stat__label', text: t(k) }),
						h('strong', { class: 'sd-stat__value', text: money ? p.sales : String(p.orders) }),
						h('span', { class: 'sd-stat__meta', text: money ? p.orders + ' ' + t('ordersCount') : t('ordersCount') }));
				})));
			}
			var attention = [];
			if (s.counts) {
				var waiting = (s.counts.processing || 0) + (s.counts['on-hold'] || 0) + (s.counts.pending || 0);
				if (C.incomplete) { setIncomplete(s.incomplete || 0); if (s.incomplete) { attention.push(h('a', { class: 'sd-alert', href: '#/incomplete' }, icon('cartx'), h('span', { text: s.incomplete + ' ' + t('incLeft') }))); } }
				if (waiting) { attention.push(h('a', { class: 'sd-alert sd-alert--new', href: '#/orders?status=processing' }, icon('orders'), h('span', { text: t('newOrders') }), h('strong', { text: String(waiting) }))); }
			}
			if (C.caps.products && s.low_stock) { attention.push(h('a', { class: 'sd-alert sd-alert--warn', href: '#/products?filter=low' }, icon('warn'), h('span', { text: t('lowStock') }), h('strong', { text: String(s.low_stock) }))); }
			if (C.caps.products && s.out_stock) { attention.push(h('a', { class: 'sd-alert sd-alert--bad', href: '#/products?filter=out' }, icon('warn'), h('span', { text: t('outOfStock') }), h('strong', { text: String(s.out_stock) }))); }
			if (attention.length) { root.appendChild(card(t('needsAction'), h('div', { class: 'sd-alerts' }, attention))); }

			if (s.counts) {
				root.appendChild(card(t('byStatus'), h('div', { class: 'sd-chips sd-chips--wrap' }, Object.keys(C.statuses).map(function (k) {
					return h('a', { class: 'sd-chip', href: '#/orders?status=' + encodeURIComponent(k) }, h('span', { text: C.statuses[k] }), h('b', { text: String(s.counts[k] || 0) }));
				}))));
			}
			if (C.caps.money && C.caps.orders) { root.appendChild(insightsCard()); }
			var quick = [];
			if (C.caps.products) { quick.push(h('a', { class: 'sd-btn sd-btn--primary', href: '#/products/new' }, icon('plus'), h('span', { text: t('addProduct') }))); }
			if (C.caps.posts) { quick.push(h('a', { class: 'sd-btn', href: '#/posts/new' }, icon('blog'), h('span', { text: t('newPost') }))); }
			quick.push(h('a', { class: 'sd-btn', href: escUrl(C.home), target: '_blank', rel: 'noopener' }, icon('shop'), h('span', { text: t('openShop') })));
			root.appendChild(card(t('quickActions'), h('div', { class: 'sd-actions' }, quick)));
			return root;
		});
	}

	/* ------------------------------------------------------------------ list helper */
	/** Paged list with "Load more". fetchPage(page) → {items,pages}; renderItem(item) → element. */
	function pagedList(fetchPage, renderItem, listClass) {
		var list = h('div', { class: listClass || 'sd-list' });
		var more = button(t('loadMore'), { class: 'sd-btn sd-btn--ghost sd-more', hidden: true });
		var page = 1;
		function load() {
			busy(more, true, t('loading'));
			return fetchPage(page).then(function (res) {
				busy(more, false);
				if (page === 1 && !res.items.length) { list.appendChild(empty()); }
				res.items.forEach(function (it) { list.appendChild(renderItem(it)); });
				more.hidden = page >= res.pages;
				page++;
			}, function (err) { busy(more, false); list.appendChild(errorBox(err)); });
		}
		more.addEventListener('click', load);
		return { el: h('div', {}, list, more), load: load };
	}

	function searchBox(value, placeholder, onSearch) {
		var inp = h('input', { class: 'sd-input sd-search__input', type: 'search', value: value || '', placeholder: placeholder, 'aria-label': t('search'), enterkeyhint: 'search' });
		var timer;
		inp.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { onSearch(inp.value.trim()); }, 500); });
		inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { clearTimeout(timer); onSearch(inp.value.trim()); } });
		return h('div', { class: 'sd-search' }, icon('search'), inp);
	}

	/* ------------------------------------------------------------------ Orders */
	function contactButtons(o, compact) {
		if (!o.phone) { return null; }
		var stop = function (e) { e.stopPropagation(); };
		return h('div', { class: 'sd-contact' },
			h('a', { class: 'sd-btn sd-btn--sm', href: 'tel:' + o.phone.replace(/[^\d+]/g, ''), onclick: stop }, icon('phone'), compact ? null : h('span', { text: t('call') })),
			o.wa ? h('a', { class: 'sd-btn sd-btn--sm sd-btn--wa', href: escUrl(o.wa), target: '_blank', rel: 'noopener', onclick: stop }, icon('whatsapp'), compact ? null : h('span', { text: t('whatsapp') })) : null);
	}

	function orderRow(o) {
		// The order number is the link (stretched over the card); Call/WhatsApp sit above it, so links are not nested.
		var row = h('article', { class: 'sd-row sd-order' },
			h('div', { class: 'sd-order__top' },
				h('a', { class: 'sd-order__link', href: '#/orders/' + o.id, text: '#' + o.number }),
				badge(o.status, o.status_label),
				o.mp_wait ? h('span', { class: 'sd-badge sd-badge--mp', text: t('mpCheck') }) : null,
				h('span', { class: 'sd-muted sd-order__time', text: o.date ? ago(o.date.ts) : '' })),
			h('div', { class: 'sd-order__who' }, h('span', { class: 'sd-order__name', text: o.name || '—' }), o.area ? h('span', { class: 'sd-muted', text: ' · ' + o.area }) : null),
			h('div', { class: 'sd-order__items sd-muted', text: o.items.join(', ') }),
			h('div', { class: 'sd-order__foot' }, h('strong', { class: 'sd-order__total', text: o.total }), contactButtons(o, true)));
		return row;
	}

	function viewOrders(params) {
		var status = params.status || '';
		var search = params.search || '';
		return getSummary(false).catch(function () { return {}; }).then(function (s) {
			var counts = s.counts || {};
			var chips = h('div', { class: 'sd-chips', role: 'tablist' },
				[['', t('all')]].concat(Object.keys(C.statuses).map(function (k) { return [k, C.statuses[k]]; })).map(function (c) {
					var href = '#/orders' + (c[0] || search ? '?' + qs({ status: c[0], search: search }) : '');
					return h('a', { class: 'sd-chip' + (c[0] === status ? ' is-active' : ''), href: href, role: 'tab', 'aria-selected': c[0] === status ? 'true' : 'false' },
						h('span', { text: c[1] }), c[0] && counts[c[0]] !== undefined ? h('b', { text: String(counts[c[0]]) }) : null);
				}));
			var shown = [];
			var list = pagedList(function (page) {
				return api('orders?' + qs({ status: status, search: search, page: page }));
			}, function (o) { shown.push(o.id); return orderRow(o); });
			var root = h('div', { class: 'sd-view' }, pageHead(t('orders')),
				searchBox(search, t('searchOrders'), function (v) { go('orders?' + qs({ status: status, search: v })); }),
				chips, orderTools(shown), list.el);
			return list.load().then(function () { return root; });
		});
	}

	/** Print / Sync buttons above the orders list. Print takes the orders loaded in the list (more after "Load more"). */
	function printUrl(kind, ids) { return C.print + '&kind=' + kind + '&ids=' + ids.join(','); }
	function openPrint(kind, ids) {
		if (!ids.length) { toast(t('nothingToPrint'), 'error'); return; }
		window.open(printUrl(kind, ids), '_blank', 'noopener');
	}
	function orderTools(shown) {
		var sync = C.courierSync ? button(t('syncCourier'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', title: t('syncHelp') }, 'sync') : null;
		if (sync) {
			sync.addEventListener('click', function () {
				busy(sync, true, t('syncing'));
				api('courier-sync', { method: 'POST', body: {} }).then(function (res) {
					busy(sync, false);
					toast(fill(t('syncDone'), { done: res.done, skipped: res.skipped }) + (res.errors && res.errors.length ? ' ' + res.errors[0] : ''), res.errors && res.errors.length ? 'error' : '');
					summaryCache = null;
					route();
				}, function (e) { busy(sync, false); toast(e.message, 'error'); });
			});
		}
		if (!C.print && !sync) { return null; }
		return h('div', { class: 'sd-tools' },
			C.print ? button(t('printInvoices'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', title: t('printListHelp'), onclick: function () { openPrint('invoice', shown); } }, 'printer') : null,
			C.print ? button(t('printLabels'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', title: t('printListHelp'), onclick: function () { openPrint('label', shown); } }, 'printer') : null,
			sync);
	}

	function viewOrder(id) {
		return api('orders/' + encodeURIComponent(id)).then(renderOrder);
	}

	/* ------------------------------------------------------------------ courier: one-tap booking and courier check */
	function fill(text, map) { return String(text).replace(/\{(\w+)\}/g, function (m, k) { return map[k] !== undefined ? map[k] : m; }); }
	function bookingCouriers() { return C.courierList || []; }

	/** Confirmation box: shows what will be sent, lets the shopkeeper fix it, then books on "Send now". */
	function openDispatch(o, courier) {
		var d = o.dispatch || {};
		var opener = document.activeElement;
		var title = fill(t('sendConfirmTitle'), { courier: courier.label });
		var name = h('input', { class: 'sd-input', type: 'text', value: d.name || '' });
		var phone = h('input', { class: 'sd-input', type: 'tel', inputmode: 'tel', value: d.phone || '' });
		var addr = h('textarea', { class: 'sd-input sd-textarea', rows: '3' });
		addr.value = d.address || '';
		var amount = h('input', { class: 'sd-input', type: 'number', min: '0', step: 'any', inputmode: 'decimal', value: String(d.cod_amount === undefined ? '' : d.cod_amount) });
		var note = h('input', { class: 'sd-input', type: 'text', maxlength: '200' });
		var err = h('p', { class: 'sd-dialog__error', role: 'alert', hidden: true });
		var send = button(t('sendNow'), { class: 'sd-btn sd-btn--primary' }, 'truck');
		var cancel = button(t('cancelIt'), { class: 'sd-btn sd-btn--ghost' });
		var panel = h('div', { class: 'sd-dialog__panel', role: 'dialog', 'aria-modal': 'true', 'aria-label': title },
			h('h2', { class: 'sd-dialog__title', text: title }),
			h('p', { class: 'sd-help', text: t('sendConfirmHelp') }),
			field(t('recipient'), name), field(t('phone'), phone), field(t('address'), addr),
			field(t('collectAmount'), amount), field(t('courierNote'), note),
			err, h('div', { class: 'sd-dialog__actions' }, cancel, send));
		var back = h('div', { class: 'sd-dialog' }, panel);
		var working = false;
		function close() {
			document.removeEventListener('keydown', onKey);
			if (back.parentNode) { back.parentNode.removeChild(back); }
			if (opener && opener.focus) { opener.focus(); }
		}
		function onKey(e) { if (e.key === 'Escape' && !working) { close(); } }
		document.addEventListener('keydown', onKey);
		back.addEventListener('click', function (e) { if (e.target === back && !working) { close(); } });
		cancel.addEventListener('click', function () { if (!working) { close(); } });
		send.addEventListener('click', function () {
			if (working) { return; }
			working = true; err.hidden = true; busy(send, true, t('sending'));
			api('book/' + o.id, { root: C.courierRest, method: 'POST', body: { courier: courier.key, name: name.value, phone: phone.value, address: addr.value, cod_amount: amount.value, note: note.value } })
				.then(function () { return api('orders/' + o.id); })
				.then(function (res) { close(); summaryCache = null; toast(t('sentOk'), 'ok'); swap(renderOrder(res)); },
					function (e) { working = false; busy(send, false); err.textContent = e.message; err.hidden = false; });
		});
		document.body.appendChild(back);
		name.focus();
	}

	/** Top of the courier card: one Send button per turned-on courier, or what was already booked (tracking filled in by the booking). */
	function bookingBlock(o) {
		var b = o.booking;
		var kids = [];
		if (b) {
			var statusLine = h('span', { class: 'sd-booked__status', text: b.status_label || '—' });
			var refresh = button(t('refreshStatus'), { class: 'sd-btn sd-btn--sm sd-btn--ghost' });
			refresh.addEventListener('click', function () {
				busy(refresh, true, t('loading'));
				api('status/' + o.id, { root: C.courierRest }).then(function (res) {
					busy(refresh, false); statusLine.textContent = res.booking.status_label || '—'; toast(t('statusUpdated'), 'ok');
				}, function (e) { busy(refresh, false); toast(e.message, 'bad'); });
			});
			kids.push(h('div', { class: 'sd-booked' },
				h('p', { class: 'sd-booked__title' }, icon('shield'), h('strong', { text: fill(t('sentTo'), { courier: b.label }) }), b.booked ? h('span', { class: 'sd-muted', text: ' · ' + b.booked }) : null),
				h('dl', { class: 'sd-kpis' },
					h('div', null, h('dt', { text: t('trackingId') }), h('dd', { class: 'sd-mono', text: b.tracking_code || b.consignment_id })),
					h('div', null, h('dt', { text: t('courierStatus') }), h('dd', null, statusLine))),
				h('div', { class: 'sd-booked__actions' },
					o.tracking_url ? h('a', { class: 'sd-btn sd-btn--sm', href: escUrl(o.tracking_url), target: '_blank', rel: 'noopener' }, icon('truck'), h('span', { text: t('openTracking') })) : null,
					refresh)));
			if (b.can_rebook && o.dispatch) { kids.push(h('p', { class: 'sd-note', text: t('sendAgain') })); }
		}
		if (o.dispatch && bookingCouriers().length) {
			kids.push(h('div', { class: 'sd-dispatch__btns' }, bookingCouriers().map(function (c) {
				return button(fill(t('sendTo'), { courier: c.label }), { class: 'sd-btn sd-btn--primary sd-btn--block', onclick: function () { openDispatch(o, c); } }, 'truck');
			})));
		}
		return kids.length ? h('div', { class: 'sd-dispatch' }, kids) : null;
	}

	/** "Courier check": what this phone number's earlier orders did. Only when turned on; loads only when tapped. */
	function courierCheck(o) {
		if (!C.courierCheck || !o.phone) { return null; }
		var panel = h('div', { class: 'sd-cc__panel', hidden: true });
		var loaded = false;
		var btn = button(t('courierCheck'), { class: 'sd-btn sd-btn--block sd-btn--ghost sd-cc__toggle', 'aria-expanded': 'false' }, 'shield');
		var VERDICT_ICON = { good: '✓', caution: '!', risky: '✕', 'new': 'i' };
		function load(refresh) {
			clear(panel); panel.appendChild(loading());
			api('checkup/' + o.id + (refresh ? '?refresh=1' : ''), { root: C.courierRest }).then(draw, function (e) { loaded = false; clear(panel); panel.appendChild(errorBox(e)); });
		}
		function bar(success, cancelled, slim) {
			var decided = success + cancelled;
			return decided ? h('div', { class: 'sd-cc__bar' + (slim ? ' is-slim' : ''), role: 'img', 'aria-label': success + ' / ' + cancelled }, h('span', { style: 'width:' + Math.round(success * 100 / decided) + '%' })) : null;
		}
		function tile(label, value, cls) { return h('div', { class: 'sd-cc__tile' + (cls ? ' ' + cls : '') }, h('b', { text: value }), h('span', { text: label })); }
		function draw(d) {
			clear(panel);
			panel.appendChild(h('div', { class: 'sd-cc__verdict sd-cc__verdict--' + d.verdict.level },
				h('span', { class: 'sd-cc__light', 'aria-hidden': 'true', text: VERDICT_ICON[d.verdict.level] || 'i' }),
				h('strong', { text: d.verdict.text })));
			(d.warnings || []).forEach(function (w) { panel.appendChild(h('p', { class: 'sd-cc__warn', role: 'status', text: w })); });
			d.sources.forEach(function (src) {
				var head = h('div', { class: 'sd-cc__head' }, h('strong', { text: src.label }));
				if (src.ago) {
					head.appendChild(h('span', { class: 'sd-muted', text: fill(t('checkUpdated'), { ago: src.ago }) }));
					head.appendChild(button(t('checkRefresh'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { load(true); } }));
				}
				var sec = h('section', { class: 'sd-cc__src' }, head,
					h('div', { class: 'sd-cc__tiles' },
						tile(t('checkTotal'), String(src.total)),
						tile(t('checkSuccess'), String(src.success), 'is-good'),
						tile(t('checkCancelled'), String(src.cancelled), src.cancelled ? 'is-bad' : ''),
						tile(t('checkRate'), src.rate === null ? '—' : src.rate + '%', 'is-rate')),
					bar(src.success, src.cancelled, false));
				if (src.open) { sec.appendChild(h('p', { class: 'sd-cc__open', text: src.open + ' ' + t('checkOpen') })); }
				if (src.couriers && src.couriers.length) {
					sec.appendChild(h('p', { class: 'sd-cc__sub', text: t('checkByCourier') }));
					sec.appendChild(h('ul', { class: 'sd-cc__list' }, src.couriers.map(function (c) {
						return h('li', null,
							h('span', { class: 'sd-cc__name', text: c.name }),
							h('span', { class: 'sd-cc__nums' }, h('b', { class: 'is-good', text: String(c.success) }), ' / ', h('b', { class: c.cancelled ? 'is-bad' : '', text: String(c.cancelled) }), ' · ', c.rate === null ? '—' : c.rate + '%'),
							bar(c.success, c.cancelled, true));
					})));
				}
				if (src.reports && src.reports.length) {
					sec.appendChild(h('p', { class: 'sd-cc__sub', text: t('checkReports') }));
					sec.appendChild(h('ul', { class: 'sd-cc__reports' }, src.reports.map(function (r) {
						return h('li', null, h('strong', { text: [r.courier, r.date].filter(Boolean).join(' · ') }), h('span', { text: [r.name, r.details].filter(Boolean).join(': ') }));
					})));
				}
				panel.appendChild(sec);
			});
			panel.appendChild(h('p', { class: 'sd-cc__foot', text: fill(t('checkLights'), { good: d.good, bad: d.bad }) }));
		}
		btn.addEventListener('click', function () {
			var open = panel.hidden;
			panel.hidden = !open;
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
			btn.classList.toggle('is-open', open);
			if (open && !loaded) { loaded = true; load(false); }
		});
		return h('div', { class: 'sd-cc' }, btn, panel);
	}

	/* ------------------------------------------------------------------ incomplete orders */
	function viewIncomplete(params) {
		var status = params.status || 'open';
		var search = params.search || '';
		var wrap = h('div', {});
		wrap.appendChild(pageHead(t('incomplete')));
		wrap.appendChild(h('p', { class: 'sd-help', text: t('incompleteHelp') }));
		return api('incomplete?' + qs({ status: status, search: search, page: params.page })).then(function (res) {
			setIncomplete(res.counts.open || 0);
			var defs = [['open', t('incOpen')], ['called', t('incCalled')], ['recovered', t('incRecovered')], ['dismissed', t('incDismissed')], ['all', t('incAll')]];
			wrap.appendChild(h('div', { class: 'sd-chips', role: 'group' }, defs.map(function (d) {
				var n = d[0] === 'all' ? null : res.counts[d[0]];
				return h('a', { class: 'sd-chip' + (status === d[0] ? ' is-active' : ''), href: '#/incomplete?' + qs({ status: d[0], search: search }) }, h('span', { text: d[1] }), n ? h('b', { text: String(n) }) : null);
			})));
			wrap.appendChild(searchBox(search, t('incSearch'), function (v) { location.hash = '#/incomplete?' + qs({ status: status, search: v }); }));
			if (!res.items.length) { wrap.appendChild(h('div', { class: 'sd-empty', text: t('incNone') })); return wrap; }
			var list = h('div', { class: 'sd-list' });
			res.items.forEach(function (it) { list.appendChild(incompleteRow(it)); });
			wrap.appendChild(list);
			return wrap;
		});
	}

	function incompleteRow(it) {
		var card = h('article', { class: 'sd-row sd-inc' });
		function redraw(next) {
			var fresh = incompleteRow(next);
			if (card.parentNode) { card.parentNode.replaceChild(fresh, card); }
		}
		function act(btn, path, body, done) {
			busy(btn, true, t('loading'));
			api(path, { method: 'POST', body: body || {} }).then(function (res) { summaryCache = null; done(res); }, function (e) { busy(btn, false); toast(e.message, 'bad'); });
		}
		var items = h('ul', { class: 'sd-inc__items' }, it.items.map(function (i) { return h('li', null, h('span', { text: i.name || '—' }), h('b', { text: '× ' + i.qty })); }));
		var actions = h('div', { class: 'sd-inc__actions' });
		if (it.status === 'open' || it.status === 'called') {
			if (it.status === 'open') {
				var called = button(t('incMarkCalled'), { class: 'sd-btn sd-btn--sm' });
				called.addEventListener('click', function () { act(called, 'incomplete/' + it.id, { status: 'called' }, function (r) { toast(t('saved'), 'ok'); redraw(r.item); }); });
				actions.appendChild(called);
			} else {
				var reopen = button(t('incReopen'), { class: 'sd-btn sd-btn--sm sd-btn--ghost' });
				reopen.addEventListener('click', function () { act(reopen, 'incomplete/' + it.id, { status: 'open' }, function (r) { redraw(r.item); }); });
				actions.appendChild(reopen);
			}
			actions.appendChild(button(t('incMakeOrder'), { class: 'sd-btn sd-btn--sm sd-btn--primary', onclick: function () { openMakeOrder(it, redraw); } }, 'orders'));
			var dismiss = button(t('incDismiss'), { class: 'sd-btn sd-btn--sm sd-btn--ghost' });
			dismiss.addEventListener('click', function () { act(dismiss, 'incomplete/' + it.id, { status: 'dismissed' }, function () { if (card.parentNode) { card.parentNode.removeChild(card); } toast(t('saved'), 'ok'); }); });
			actions.appendChild(dismiss);
		} else if (it.order_id) {
			actions.appendChild(h('a', { class: 'sd-btn sd-btn--sm', href: '#/orders/' + it.order_id }, icon('orders'), h('span', { text: t('incSeeOrder') + ' #' + (it.order_number || it.order_id) })));
		}
		append(card,
			h('div', { class: 'sd-order__top' },
				h('strong', { class: 'sd-order__name', text: it.name || it.phone }),
				badge(it.status === 'recovered' ? 'completed' : (it.status === 'called' ? 'on-hold' : (it.status === 'dismissed' ? 'cancelled' : 'processing')), it.status === 'open' ? t('incOpen') : (it.status === 'called' ? t('incCalled') : (it.status === 'recovered' ? t('incRecovered') : t('incDismissed')))),
				h('span', { class: 'sd-muted sd-order__time', text: it.ts ? ago(it.ts) : '' })),
			h('p', { class: 'sd-inc__total' }, h('span', { class: 'sd-muted', text: t('incCart') + ' · ' }), h('b', { text: it.total })),
			items,
			it.address ? h('p', { class: 'sd-muted sd-inc__addr', text: [it.address, it.city].filter(Boolean).join(', ') }) : null,
			contactButtons({ phone: it.phone, wa: it.wa }, false),
			actions);
		return card;
	}

	/** Confirm, then turn the cart into a real order. */
	function openMakeOrder(it, onDone) {
		var opener = document.activeElement;
		var err = h('p', { class: 'sd-dialog__error', role: 'alert', hidden: true });
		var go = button(t('incMakeOrder'), { class: 'sd-btn sd-btn--primary' }, 'orders');
		var cancel = button(t('cancelIt'), { class: 'sd-btn sd-btn--ghost' });
		var panel = h('div', { class: 'sd-dialog__panel', role: 'dialog', 'aria-modal': 'true', 'aria-label': t('incMakeTitle') },
			h('h2', { class: 'sd-dialog__title', text: t('incMakeTitle') }),
			h('p', { class: 'sd-help', text: t('incMakeHelp') }),
			h('p', null, h('strong', { text: it.name || it.phone }), h('span', { class: 'sd-muted', text: ' · ' + it.phone })),
			h('ul', { class: 'sd-inc__items' }, it.items.map(function (i) { return h('li', null, h('span', { text: i.name }), h('b', { text: '× ' + i.qty })); })),
			h('p', null, h('b', { text: it.total })),
			err, h('div', { class: 'sd-dialog__actions' }, cancel, go));
		var back = h('div', { class: 'sd-dialog' }, panel);
		var working = false;
		function close() { document.removeEventListener('keydown', onKey); if (back.parentNode) { back.parentNode.removeChild(back); } if (opener && opener.focus) { opener.focus(); } }
		function onKey(e) { if (e.key === 'Escape' && !working) { close(); } }
		document.addEventListener('keydown', onKey);
		back.addEventListener('click', function (e) { if (e.target === back && !working) { close(); } });
		cancel.addEventListener('click', function () { if (!working) { close(); } });
		go.addEventListener('click', function () {
			if (working) { return; }
			working = true; err.hidden = true; busy(go, true, t('loading'));
			api('incomplete/' + it.id + '/order', { method: 'POST', body: {} }).then(function (res) {
				close(); summaryCache = null; toast(t('incMade') + ' #' + res.order_number, 'ok'); onDone(res.item);
			}, function (e) { working = false; busy(go, false); err.textContent = e.message; err.hidden = false; });
		});
		document.body.appendChild(back);
		go.focus();
	}

	/** bKash / Nagad / Rocket by hand: what the customer typed, and the two decisions. */
	function mpayCard(o) {
		var m = o.mpay;
		var mrow = function (label, value) { return h('div', { class: 'sd-totals__row' }, h('dt', { text: label }), h('dd', { text: value || '—' })); };
		var states = { wait: t('mpWait'), yes: t('mpYes'), no: t('mpNo') };
		var decide = function (btn, decision) {
			if (decision === 'no' && !window.confirm(t('mpNoConfirm'))) { return; }
			busy(btn, true, t('saving'));
			api('orders/' + o.id + '/mpay', { method: 'POST', body: { decision: decision } }).then(function () {
				summaryCache = null; toast(t('saved'), 'ok'); route();
			}, function (e) { busy(btn, false); toast(e.message, 'error'); });
		};
		var yes = m.state !== 'yes' ? button(t('mpReceived'), { class: 'sd-btn sd-btn--primary' }) : null;
		var no = m.state !== 'no' ? button(t('mpNotReceived'), { class: 'sd-btn sd-btn--ghost' }) : null;
		if (yes) { yes.addEventListener('click', function () { decide(yes, 'yes'); }); }
		if (no) { no.addEventListener('click', function () { decide(no, 'no'); }); }
		return card(m.wallet + ' · ' + states[m.state], [
			h('dl', { class: 'sd-totals' },
				mrow(t('mpAmount') + (m.advance ? ' (' + t('mpAdvance') + ')' : ''), m.amount_text),
				mrow(t('mpFrom'), m.sender),
				mrow(t('mpTo'), m.to)),
			h('div', { class: 'sd-line' }, h('span', { class: 'sd-mono sd-big', text: m.trx }),
				button(t('copy'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { copyText(m.trx); } }, 'copy')),
			m.shot ? h('p', null, h('a', { href: escUrl(m.shot), target: '_blank', rel: 'noopener', text: t('mpShot') })) : null,
			h('p', { class: 'sd-muted', text: t('mpHelp') }),
			yes || no ? h('div', { class: 'sd-tools' }, yes, no) : null
		]);
	}

	function renderOrder(o) {
		var root = h('div', { class: 'sd-view' });
		root.appendChild(pageHead(t('order') + ' #' + o.number, { back: '#/orders', sub: o.date ? o.date.text : '' }));
		root.firstChild.querySelector('.sd-head__row').appendChild(badge(o.status, o.status_label));
		if (C.print) {
			root.appendChild(h('div', { class: 'sd-tools' },
				button(t('printInvoice'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { openPrint('invoice', [o.id]); } }, 'printer'),
				button(t('printLabel'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { openPrint('label', [o.id]); } }, 'printer')));
		}

		// Customer.
		var hist = o.history || { total: 0 };
		var histLine = hist.total <= 1
			? h('p', { class: 'sd-history', text: t('firstOrder') })
			: h('p', { class: 'sd-history' + (hist.cancelled ? ' sd-history--warn' : '') },
				h('strong', { text: hist.total + ' ' + t('historyTotal') }), ' · ' + hist.completed + ' ' + t('historyDone') + ' · ' + hist.cancelled + ' ' + t('historyCancel'));
		var addressText = [o.address, o.area].filter(Boolean).join(', ');
		root.appendChild(card(t('customer'), [
			h('p', { class: 'sd-big', text: o.name || '—' }),
			o.phone ? h('div', { class: 'sd-line' }, h('span', { class: 'sd-mono', text: o.phone }),
				button(t('copy'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { copyText(o.phone); } }, 'copy')) : null,
			contactButtons(o, false),
			courierCheck(o),
			addressText ? h('div', { class: 'sd-line sd-line--top' }, h('span', { text: addressText }),
				button(t('copy'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { copyText(addressText); } }, 'copy')) : null,
			o.email ? h('p', { class: 'sd-muted', text: o.email }) : null,
			histLine,
			o.customer_note ? h('div', { class: 'sd-note-box' }, h('strong', { text: t('customerNote') + ': ' }), o.customer_note) : null
		]));

		// Items and totals.
		var tot = o.totals;
		root.appendChild(card(t('items'), [
			h('ul', { class: 'sd-lines' }, o.lines.map(function (l) {
				return h('li', { class: 'sd-lineitem' },
					l.image ? h('img', { src: escUrl(l.image), alt: '', class: 'sd-lineitem__img', loading: 'lazy' }) : h('span', { class: 'sd-lineitem__img' }),
					h('div', { class: 'sd-lineitem__body' }, h('strong', { text: l.name }), l.meta ? h('span', { class: 'sd-muted', text: l.meta }) : null, h('span', { class: 'sd-muted', text: '× ' + l.qty + (l.sku ? ' · ' + l.sku : '') })),
					h('span', { class: 'sd-lineitem__total', text: l.total }));
			})),
			h('dl', { class: 'sd-totals' },
				row(t('subtotal'), tot.subtotal),
				tot.shipping ? row(t('delivery') + (tot.shipping_method ? ' (' + tot.shipping_method + ')' : ''), tot.shipping) : null,
				tot.discount ? row(t('discount'), '− ' + tot.discount) : null,
				tot.refunded ? row(t('refunded'), '− ' + tot.refunded) : null,
				row(t('total'), tot.total, 'sd-totals__grand'),
				tot.to_collect ? row(t('toCollect'), tot.to_collect, 'sd-totals__cod') : null),
			h('p', { class: 'sd-muted', text: t('payment') + ': ' + (o.payment || '—') })
		]));
		if (o.mpay) { root.appendChild(mpayCard(o)); }

		// Status.
		var statusSel = select(Object.keys(C.statuses).map(function (k) { return [k, C.statuses[k]]; }), o.status, { 'aria-label': t('status') });
		var statusBtn = button(t('save'), { class: 'sd-btn sd-btn--primary' });
		statusBtn.addEventListener('click', function () {
			if (statusSel.value === o.status) { return; }
			busy(statusBtn, true);
			api('orders/' + o.id, { method: 'POST', body: { status: statusSel.value } }).then(function (res) {
				setDirty(false); summaryCache = null; toast(t('saved'), 'ok'); swap(renderOrder(res));
			}, function (err) { busy(statusBtn, false); toast(err.message, 'bad'); });
		});
		root.appendChild(card(t('changeStatus'), h('div', { class: 'sd-inline' }, statusSel, statusBtn)));

		// Courier & tracking.
		var couriers = [['', t('selectCourier')]].concat(Object.keys(C.couriers).map(function (k) { return [k, C.couriers[k]]; }));
		var cSel = select(couriers, o.courier_key);
		var cId = input({ value: o.tracking_id });
		var cUrl = input({ value: o.tracking_url, type: 'url', inputmode: 'url', placeholder: 'https://' });
		var cBtn = button(t('save'), { class: 'sd-btn sd-btn--primary' });
		cBtn.addEventListener('click', function () {
			busy(cBtn, true);
			api('orders/' + o.id, { method: 'POST', body: { courier: cSel.value, tracking_id: cId.value.trim(), tracking_url: cUrl.value.trim() } }).then(function (res) {
				setDirty(false); summaryCache = null; toast(t('saved'), 'ok'); swap(renderOrder(res));
			}, function (err) { busy(cBtn, false); toast(err.message, 'bad'); });
		});
		var courierText = [o.name, o.phone, addressText, (tot.cod ? 'COD: ' : t('total') + ': ') + (tot.to_collect || tot.total), t('order') + ' #' + o.number, o.lines.map(function (l) { return l.name + ' × ' + l.qty; }).join(', ')].filter(Boolean).join('\n');
		var copyBtn = button(t('copyCourier'), { class: 'sd-btn sd-btn--ghost', onclick: function () { copyText(courierText); } }, 'truck');
		// With a courier turned on, courier / tracking ID / link are set by the booking itself: no typing, no manual fields.
		root.appendChild(card(t('courier'), bookingCouriers().length || o.booking
			? [bookingBlock(o), bookingCouriers().length && !o.booking && !o.dispatch ? h('p', { class: 'sd-note', text: t('trackingAuto') }) : null, copyBtn]
			: [copyBtn, field(t('courierName'), cSel), field(t('trackingId'), cId), field(t('trackingUrl'), cUrl, t('trackingHelp')), cBtn]));

		// Notes.
		var noteTa = textarea({ rows: 3, 'aria-label': t('addNote') });
		var noteType = select([['0', t('notePrivate')], ['1', t('noteCustomer')]], '0', { 'aria-label': t('notes') });
		var noteBtn = button(t('addNote'), { class: 'sd-btn' });
		noteBtn.addEventListener('click', function () {
			var text = noteTa.value.trim();
			if (!text) { noteTa.focus(); return; }
			busy(noteBtn, true);
			api('orders/' + o.id, { method: 'POST', body: { note: text, note_customer: noteType.value === '1' } }).then(function (res) {
				setDirty(false); toast(t('noteAdded'), 'ok'); swap(renderOrder(res));
			}, function (err) { busy(noteBtn, false); toast(err.message, 'bad'); });
		});
		root.appendChild(card(t('notes'), [
			h('div', { class: 'sd-inline sd-inline--stack' }, noteTa, h('div', { class: 'sd-inline' }, noteType, noteBtn)),
			o.notes.length ? h('ul', { class: 'sd-notes' }, o.notes.map(function (n) {
				return h('li', { class: 'sd-notes__item' + (n.customer ? ' is-customer' : '') },
					h('p', { text: n.content }),
					h('span', { class: 'sd-muted', text: (n.date ? n.date.text : '') + ' · ' + (n.by || t('system')) + (n.customer ? ' · ' + t('noteFromTeam') : '') }));
			})) : null
		]));
		if (o.edit_url) { root.appendChild(h('p', { class: 'sd-foot-link' }, h('a', { href: escUrl(o.edit_url) }, icon('ext'), h('span', { text: t('openInAdmin') })))); }
		return root;

		function row(label, value, cls) { return h('div', { class: 'sd-totals__row' + (cls ? ' ' + cls : '') }, h('dt', { text: label }), h('dd', { text: value })); }
	}

	/** Replace the current view (after saving) without a new route. */
	function swap(el) { clear(main); main.appendChild(el); }

	/* ------------------------------------------------------------------ Products */
	var catsCache = null, brandsCache = null, attrCache = null;
	function getCats() {
		if (catsCache) { return Promise.resolve(catsCache); }
		return api('categories').then(function (c) { catsCache = c; return c; });
	}
	function getBrands() {
		if (!C.brands) { return Promise.resolve([]); }
		if (brandsCache) { return Promise.resolve(brandsCache); }
		return api('brands').then(function (b) { brandsCache = b; return b; }, function () { return []; });
	}
	function getAttrSuggestions() {
		if (attrCache) { return Promise.resolve(attrCache); }
		return api('attributes').then(function (a) { attrCache = a; return a; }, function () { return []; });
	}

	/** Tick list of categories or brands, with "+ New …" when the user may add terms. */
	function termPicker(kind, list, selected) {
		var chosen = selected.slice();
		var box = h('div', { class: 'sd-checks' });
		function draw() {
			clear(box);
			if (!list.length) { box.appendChild(h('p', { class: 'sd-muted', text: kind === 'brand' ? t('noBrandsYet') : t('none') })); }
			list.forEach(function (c) {
				var cb = h('input', { type: 'checkbox', value: String(c.id), checked: chosen.indexOf(c.id) !== -1 });
				cb.addEventListener('change', function () {
					setDirty(true);
					if (cb.checked) { if (chosen.indexOf(c.id) === -1) { chosen.push(c.id); } } else { chosen = chosen.filter(function (x) { return x !== c.id; }); }
				});
				box.appendChild(h('label', { class: 'sd-check', style: 'padding-left:' + (c.depth * 18) + 'px' }, cb, h('span', { text: c.name })));
			});
		}
		draw();
		var kids = [box];
		if (C.caps.terms) {
			var nameIn = input({ placeholder: kind === 'brand' ? t('newBrandName') : t('newCategoryName'), autocomplete: 'off' });
			var parentSel = kind === 'brand' ? null : select([['0', t('noParent')]].concat(list.map(function (c) { return [String(c.id), new Array(c.depth + 1).join('— ') + c.name]; })), '0', { 'aria-label': t('parentCategory') });
			var add = button(kind === 'brand' ? t('addBrand') : t('addCategory'), { class: 'sd-btn sd-btn--sm' }, 'plus');
			var form = h('div', { class: 'sd-newterm', hidden: true }, nameIn, parentSel, add);
			var open = button(kind === 'brand' ? t('newBrand') : t('newCategory'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { form.hidden = !form.hidden; if (!form.hidden) { nameIn.focus(); } } }, 'plus');
			function create() {
				var name = nameIn.value.trim();
				if (!name) { nameIn.focus(); return; }
				busy(add, true);
				api('terms', { method: 'POST', body: { tax: kind, name: name, parent: parentSel ? parentSel.value : 0 } }).then(function (res) {
					busy(add, false);
					list = res.list;
					if (kind === 'brand') { brandsCache = list; } else { catsCache = list; }
					if (chosen.indexOf(res.id) === -1) { chosen.push(res.id); }
					nameIn.value = ''; form.hidden = true; setDirty(true); draw();
					toast(res.existed ? t('termExisted') : t('termAdded'), 'ok');
				}, function (e) { busy(add, false); toast(e.message, 'bad'); });
			}
			add.addEventListener('click', create);
			nameIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); create(); } });
			kids.push(h('div', { class: 'sd-newterm__bar' }, open), form);
		}
		return { el: kids, value: function () { return chosen.slice(); } };
	}

	function statusLabel(s) { return t({ publish: 'published', draft: 'draft', pending: 'pending', private: 'private', future: 'scheduled' }[s] || s); }

	function productCard(p) {
		return h('a', { class: 'sd-pcard', href: '#/products/' + p.edit_id },
			h('span', { class: 'sd-pcard__img' }, p.image ? h('img', { src: escUrl(p.image), alt: '', loading: 'lazy' }) : icon('image')),
			h('span', { class: 'sd-pcard__body' },
				h('strong', { class: 'sd-pcard__name', text: p.name }),
				h('span', { class: 'sd-pcard__price' }, p.regular ? h('del', { text: p.regular }) : null, ' ', p.price || '—'),
				h('span', { class: 'sd-pcard__meta' },
					h('span', { class: 'sd-pill sd-pill--' + p.stock_status, text: p.stock_label }),
					p.status !== 'publish' ? h('span', { class: 'sd-pill', text: statusLabel(p.status) }) : null,
					p.variations ? h('span', { class: 'sd-muted', text: p.variations + ' ' + t('optionsCount') }) : null)));
	}

	function viewProducts(params) {
		var filter = params.filter || '';
		var search = params.search || '';
		var chips = h('div', { class: 'sd-chips', role: 'tablist' }, [['', t('all')], ['low', t('lowStock')], ['out', t('outOfStock')], ['draft', t('drafts')]].map(function (c) {
			return h('a', { class: 'sd-chip' + (c[0] === filter ? ' is-active' : ''), href: '#/products' + (c[0] ? '?filter=' + c[0] : ''), role: 'tab', 'aria-selected': c[0] === filter ? 'true' : 'false', text: c[1] });
		}));
		var list = pagedList(function (page) {
			return api('products?' + qs({ filter: filter, search: search, page: page }));
		}, productCard, 'sd-grid');
		var root = h('div', { class: 'sd-view' },
			pageHead(t('products'), { action: h('a', { class: 'sd-btn sd-btn--primary', href: '#/products/new' }, icon('plus'), h('span', { text: t('addProduct') })) }),
			filter === 'low' || filter === 'out' ? null : searchBox(search, t('searchProducts'), function (v) { go('products?' + qs({ filter: filter, search: v })); }),
			chips, list.el);
		return list.load().then(function () { return root; });
	}

	function viewProduct(id) {
		return Promise.all([id ? api('products/' + encodeURIComponent(id)) : Promise.resolve(null), getCats(), getBrands()]).then(function (res) {
			return renderProduct(res[0], res[1], res[2]);
		});
	}

	/** Price + stock fields used for simple products and for each variation. */
	function priceStockFields(p) {
		var reg = input({ value: p.regular_price, inputmode: 'decimal', autocomplete: 'off' });
		var sale = input({ value: p.sale_price, inputmode: 'decimal', autocomplete: 'off' });
		var qty = input({ value: p.stock_quantity === '' ? '' : String(p.stock_quantity), inputmode: 'numeric', type: 'number', min: '0', step: '1' });
		var stat = select([['instock', t('inStock')], ['outofstock', t('outOfStock')], ['onbackorder', t('onBackorder')]], p.stock_status || 'instock');
		var qtyField = field(t('quantity'), qty);
		var statField = field(t('stockStatus'), stat);
		var manage = toggle(t('trackStock'), !!p.manage_stock, function (on) { qtyField.hidden = !on; statField.hidden = on; });
		qtyField.hidden = !p.manage_stock;
		statField.hidden = !!p.manage_stock;
		return {
			el: [h('div', { class: 'sd-cols' }, field(t('regularPrice') + ' (' + C.currency + ')', reg), field(t('salePrice'), sale)), manage.el, qtyField, statField],
			values: function () {
				return { regular_price: reg.value.trim(), sale_price: sale.value.trim(), manage_stock: manage.input.checked, stock_quantity: qty.value === '' ? 0 : parseInt(qty.value, 10), stock_status: stat.value };
			},
			check: function () {
				var r = parseFloat(reg.value), s = parseFloat(sale.value);
				if (sale.value.trim() !== '' && (isNaN(r) || s >= r)) { sale.focus(); return t('salePriceError'); }
				return '';
			},
			reg: reg
		};
	}

	function renderProduct(p, cats, brands) {
		var creating = !p;
		brands = brands || [];
		p = p || { id: 0, type: 'simple', editable_type: true, name: '', status: 'publish', sku: '', regular_price: '', sale_price: '', manage_stock: false, stock_quantity: '', stock_status: 'instock', short_description: '', description: '', category_ids: [], brand_ids: [], attributes: [], images: [], variations: [], permalink: '', edit_url: '' };
		var root = h('div', { class: 'sd-view sd-form' });
		root.appendChild(pageHead(creating ? t('newProduct') : p.name, { back: '#/products' }));
		if (!p.editable_type) {
			root.appendChild(card('', [h('p', { text: t('adminOnlyType') }), p.edit_url ? h('a', { class: 'sd-btn', href: escUrl(p.edit_url) }, icon('ext'), h('span', { text: t('openInAdmin') })) : null]));
			return root;
		}
		var variable = p.type === 'variable';
		var name = input({ value: p.name, required: true, autocomplete: 'off' });
		var visible = toggle(t('visibility'), p.status === 'publish');
		var sku = input({ value: p.sku, autocomplete: 'off' });
		var ps = priceStockFields(p);
		var opts = optionsEditor(p);
		var priceCard = card(t('priceStock'), [ps.el]);
		var optsCard = card(t('optionsTitle'), opts.el, 'sd-opts-card');
		function showType(isVar) { variable = isVar; priceCard.hidden = isVar; optsCard.hidden = !isVar; }
		var typeSeg = null;
		if (p.type === 'simple') {
			var bSimple = h('button', { type: 'button', class: 'is-active', text: t('typeSimple') });
			var bVar = h('button', { type: 'button', text: t('typeVariable') });
			bSimple.addEventListener('click', function () { bSimple.classList.add('is-active'); bVar.classList.remove('is-active'); showType(false); });
			bVar.addEventListener('click', function () { bVar.classList.add('is-active'); bSimple.classList.remove('is-active'); showType(true); setDirty(true); });
			typeSeg = h('div', null, h('div', { class: 'sd-seg sd-seg--type', role: 'group', 'aria-label': t('productType') }, bSimple, bVar),
				h('p', { class: 'sd-help', text: creating ? t('typeHelp') : t('typeConvertHelp') }));
		}

		// Pictures.
		var images = p.images.slice();
		var gallery = h('div', { class: 'sd-gallery' });
		function drawGallery() {
			clear(gallery);
			images.forEach(function (img, i) {
				gallery.appendChild(h('figure', { class: 'sd-gallery__item' + (i === 0 ? ' is-main' : '') },
					h('img', { src: escUrl(img.url), alt: '' }),
					i === 0 ? h('figcaption', { text: t('mainImage') }) : null,
					h('div', { class: 'sd-gallery__tools' },
						i > 0 ? button(t('makeMain'), { class: 'sd-btn sd-btn--xs', onclick: function () { images.unshift(images.splice(i, 1)[0]); setDirty(true); drawGallery(); } }) : null,
						button(t('remove'), { class: 'sd-btn sd-btn--xs sd-btn--danger', onclick: function () { images.splice(i, 1); setDirty(true); drawGallery(); } }))));
			});
			if (C.caps.upload) {
				var file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', multiple: true, class: 'sd-offscreen', id: 'sd-file-' + (++uid) });
				var label = h('label', { class: 'sd-gallery__add', for: file.id }, icon('plus'), h('span', { text: t('addImages') }));
				file.addEventListener('change', function () {
					var files = Array.prototype.slice.call(file.files);
					if (!files.length) { return; }
					label.classList.add('is-busy');
					label.lastChild.textContent = t('uploading');
					files.reduce(function (chain, f) {
						return chain.then(function () { return upload(f).then(function (m) { images.push(m); setDirty(true); }, function (err) { toast(f.name + ': ' + err.message, 'bad'); }); });
					}, Promise.resolve()).then(drawGallery);
				});
				gallery.appendChild(h('div', { class: 'sd-gallery__item sd-gallery__item--add' }, label, file));
			}
		}
		drawGallery();

		// Categories and brands.
		var catPick = termPicker('category', cats, p.category_ids || []);
		var brandPick = C.brands ? termPicker('brand', brands, p.brand_ids || []) : null;

		var shortEd = editor(p.short_description, { small: true, label: t('shortDesc') });
		var descEd = editor(p.description, { label: t('description') });
		var saveBtn = button(t('save'), { class: 'sd-btn sd-btn--primary sd-btn--lg' });

		root.appendChild(card('', [field(t('name'), name), visible.el, typeSeg]));
		root.appendChild(priceCard);
		root.appendChild(optsCard);
		showType(variable);
		root.appendChild(card(t('images'), gallery));
		root.appendChild(card(t('categories'), catPick.el));
		if (brandPick) { root.appendChild(card(t('brand'), [h('p', { class: 'sd-help', text: t('brandHelp') })].concat(brandPick.el))); }
		root.appendChild(card(t('shortDesc'), shortEd.el));
		root.appendChild(card(t('description'), descEd.el));
		root.appendChild(card('', field(t('sku'), sku)));

		saveBtn.addEventListener('click', function () {
			if (!name.value.trim()) { toast(t('requiredName'), 'bad'); name.focus(); return; }
			if (!variable) {
				if (ps.reg.value.trim() === '' && (creating || visible.input.checked)) { toast(t('requiredPrice'), 'bad'); ps.reg.focus(); return; }
				var problem = ps.check();
				if (problem) { toast(problem, 'bad'); return; }
			} else {
				var optProblem = opts.check(visible.input.checked);
				if (optProblem) { toast(optProblem, 'bad'); return; }
			}
			var body = {
				name: name.value.trim(), status: visible.input.checked ? 'publish' : 'draft', sku: sku.value.trim(),
				short_description: shortEd.get(), description: descEd.get(),
				category_ids: catPick.value(),
				image_ids: images.map(function (i) { return i.id; })
			};
			if (brandPick) { body.brand_ids = brandPick.value(); }
			if (variable) { body.type = 'variable'; Object.assign(body, opts.values()); } else { Object.assign(body, ps.values()); }
			busy(saveBtn, true);
			api(creating ? 'products' : 'products/' + p.id, { method: 'POST', body: body }).then(function (res) {
				setDirty(false); summaryCache = null; toast(t('saved'), 'ok');
				if (creating) { skipHash = true; location.hash = '#/products/' + res.id; current = 'products/' + res.id; }
				attrCache = null;
				swap(renderProduct(res, catsCache || cats, brandsCache || brands));
			}, function (err) { busy(saveBtn, false); toast(err.message, 'bad'); });
		});
		root.appendChild(h('div', { class: 'sd-savebar' }, saveBtn,
			p.permalink ? h('a', { class: 'sd-btn sd-btn--ghost', href: escUrl(p.permalink), target: '_blank', rel: 'noopener' }, icon('ext'), h('span', { text: t('viewProduct') })) : null));

		if (p.edit_url) { root.appendChild(h('p', { class: 'sd-foot-link' }, h('a', { href: escUrl(p.edit_url) }, icon('ext'), h('span', { text: t('openInAdmin') })))); }
		return root;
	}

	/**
	 * Options of a variable product: option types (any name: Size, Colour, Weight…) with their values, then one row per
	 * combination with its own price, sale price, stock and picture. Names and values already used in the shop are suggested.
	 */
	function optionsEditor(p) {
		var attrs = (p.attributes || []).filter(function (a) { return a.variation; }).map(function (a) { return { name: a.name, values: a.values.slice() }; });
		if (!attrs.length) { attrs.push({ name: '', values: [] }); }
		var rows = (p.variations || []).map(function (v) {
			return { id: v.id, options: v.options || {}, regular_price: v.regular_price, sale_price: v.sale_price, stock: v.manage_stock ? String(v.stock_quantity) : '', stock_status: v.stock_status, sku: v.sku, image_id: v.image_id || 0, image: v.image || '' };
		});
		var removed = [];
		var sugg = [];
		var listId = 'sd-attrnames-' + (++uid);
		var nameList = h('datalist', { id: listId });
		getAttrSuggestions().then(function (a) {
			sugg = a || [];
			sugg.forEach(function (x) { nameList.appendChild(h('option', { value: x.name })); });
		});
		var attrBox = h('div', { class: 'sd-attrs' });
		var rowBox = h('div', { class: 'sd-vars' });
		var info = h('p', { class: 'sd-help' });

		function suggestionsFor(name) {
			var n = String(name || '').trim().toLowerCase();
			for (var i = 0; i < sugg.length; i++) { if (sugg[i].name.toLowerCase() === n) { return sugg[i].values; } }
			return [];
		}
		function key(opts) { return attrs.map(function (a) { return (opts[a.name] || '').toLowerCase(); }).join('|'); }
		function label(opts) { return attrs.map(function (a) { return opts[a.name] || t('anyValue'); }).join(' / '); }

		function drawAttrs() {
			clear(attrBox);
			attrs.forEach(function (a, ai) {
				var nameIn = input({ value: a.name, list: listId, placeholder: t('optionNameHint'), autocomplete: 'off', 'aria-label': t('optionName') });
				// Only the value suggestions and the rows change: the fields are not rebuilt, so the field the user moves to keeps its focus.
				nameIn.addEventListener('change', function () {
					var old = a.name; a.name = nameIn.value.trim();
					rows.forEach(function (r) { if (old && r.options[old] !== undefined) { r.options[a.name] = r.options[old]; delete r.options[old]; } });
					clear(vData);
					suggestionsFor(a.name).filter(function (x) { return a.values.indexOf(x) === -1; }).forEach(function (x) { vData.appendChild(h('option', { value: x })); });
					setDirty(true); drawRows();
				});
				var chips = h('div', { class: 'sd-chips sd-chips--wrap sd-vals' });
				a.values.forEach(function (v, vi) {
					chips.appendChild(h('span', { class: 'sd-chip sd-chip--val' }, h('span', { text: v }),
						h('button', { type: 'button', class: 'sd-chip__x', 'aria-label': t('remove') + ' ' + v, text: '×', onclick: function () { a.values.splice(vi, 1); setDirty(true); drawAttrs(); drawRows(); } })));
				});
				var vList = 'sd-attrvals-' + (++uid);
				var vData = h('datalist', { id: vList }, suggestionsFor(a.name).filter(function (x) { return a.values.indexOf(x) === -1; }).map(function (x) { return h('option', { value: x }); }));
				var valIn = input({ list: vList, placeholder: t('optionValueHint'), autocomplete: 'off', 'aria-label': t('optionValues') });
				function addVals() {
					valIn.value.split(/[,،|]/).map(function (x) { return x.trim(); }).filter(Boolean).forEach(function (v) {
						if (!a.values.some(function (x) { return x.toLowerCase() === v.toLowerCase(); })) { a.values.push(v); }
					});
					valIn.value = ''; setDirty(true); drawAttrs(); drawRows();
					var again = attrBox.querySelectorAll('.sd-attr')[ai]; if (again) { again.querySelector('.sd-attr__val input').focus(); }
				}
				valIn.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addVals(); } });
				valIn.addEventListener('change', function () { if (valIn.value.trim()) { addVals(); } });
				var del = attrs.length > 1 ? button(t('remove'), { class: 'sd-btn sd-btn--xs sd-btn--danger', onclick: function () { attrs.splice(ai, 1); setDirty(true); drawAttrs(); drawRows(); } }) : null;
				attrBox.appendChild(h('div', { class: 'sd-attr' },
					h('div', { class: 'sd-attr__head' }, field(t('optionName'), nameIn), del),
					chips,
					h('div', { class: 'sd-attr__val' }, valIn, vData, button(t('add'), { class: 'sd-btn sd-btn--sm', onclick: addVals }))));
			});
			if (attrs.length < 3) {
				attrBox.appendChild(button(t('addOptionType'), { class: 'sd-btn sd-btn--sm sd-btn--ghost', onclick: function () { attrs.push({ name: '', values: [] }); drawAttrs(); } }, 'plus'));
			}
		}

		function combos() {
			var list = [{}];
			attrs.filter(function (a) { return a.name && a.values.length; }).forEach(function (a) {
				var next = [];
				list.forEach(function (base) { a.values.forEach(function (v) { var o = Object.assign({}, base); o[a.name] = v; next.push(o); }); });
				list = next;
			});
			return attrs.some(function (a) { return a.name && a.values.length; }) ? list : [];
		}

		function drawRows() {
			clear(rowBox);
			var all = combos();
			info.textContent = all.length ? fill(t('combosInfo'), { n: all.length, have: rows.length }) : t('optionsStart');
			rows.forEach(function (r, ri) {
				var reg = input({ value: r.regular_price, inputmode: 'decimal', autocomplete: 'off', 'aria-label': t('regularPrice') });
				var sale = input({ value: r.sale_price, inputmode: 'decimal', autocomplete: 'off', 'aria-label': t('salePrice') });
				var qty = input({ value: r.stock, inputmode: 'numeric', type: 'number', min: '0', step: '1', placeholder: t('stockBlank'), 'aria-label': t('quantity') });
				reg.addEventListener('input', function () { r.regular_price = reg.value.trim(); r.dirty = true; });
				sale.addEventListener('input', function () { r.sale_price = sale.value.trim(); r.dirty = true; });
				qty.addEventListener('input', function () { r.stock = qty.value.trim(); r.dirty = true; });
				var pic = h('span', { class: 'sd-var__pic' }, r.image ? h('img', { src: escUrl(r.image), alt: '' }) : icon('image'));
				if (C.caps.upload) {
					var file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', class: 'sd-offscreen', id: 'sd-vfile-' + (++uid) });
					file.addEventListener('change', function () {
						if (!file.files[0]) { return; }
						pic.classList.add('is-busy');
						upload(file.files[0]).then(function (m) { r.image_id = m.id; r.image = m.url; r.dirty = true; setDirty(true); drawRows(); }, function (err) { pic.classList.remove('is-busy'); toast(err.message, 'bad'); });
					});
					pic = h('label', { class: 'sd-var__pic', for: file.id, title: t('optionPicture') }, pic.firstChild, file);
				}
				var stale = all.length && !all.some(function (c) { return key(c) === key(r.options); });
				rowBox.appendChild(h('div', { class: 'sd-var' + (stale ? ' is-stale' : '') },
					pic,
					h('div', { class: 'sd-var__main' },
						h('strong', { class: 'sd-var__label', text: label(r.options) }),
						stale ? h('span', { class: 'sd-pill sd-pill--outofstock', text: t('optionStale') }) : null,
						h('div', { class: 'sd-var__fields' },
							field(t('regularPrice') + ' (' + C.currency + ')', reg), field(t('salePrice'), sale), field(t('quantity'), qty))),
					button('×', { class: 'sd-btn sd-btn--xs sd-btn--danger sd-var__del', 'aria-label': t('remove') + ' ' + label(r.options), onclick: function () {
						if (r.id) { removed.push(r.id); }
						rows.splice(ri, 1); setDirty(true); drawRows();
					} })));
			});
		}

		var makeBtn = button(t('makeCombos'), { class: 'sd-btn sd-btn--primary sd-btn--sm' }, 'plus');
		makeBtn.addEventListener('click', function () {
			var all = combos();
			if (!all.length) { toast(t('optionsStart'), 'bad'); return; }
			var have = {}; rows.forEach(function (r) { have[key(r.options)] = true; });
			var add = all.filter(function (c) { return !have[key(c)]; });
			if (rows.length + add.length > C.maxVariations) { toast(fill(t('tooManyOptions'), { n: C.maxVariations }), 'bad'); return; }
			var first = rows[0] || {};
			add.forEach(function (c) { rows.push({ id: 0, options: c, regular_price: first.regular_price || '', sale_price: '', stock: '', stock_status: 'instock', sku: '', image_id: 0, image: '', dirty: true }); });
			setDirty(true); drawRows();
			toast(add.length ? fill(t('combosAdded'), { n: add.length }) : t('combosNone'), add.length ? 'ok' : '');
		});

		var bReg = input({ inputmode: 'decimal', placeholder: t('regularPrice'), 'aria-label': t('regularPrice') });
		var bSale = input({ inputmode: 'decimal', placeholder: t('salePrice'), 'aria-label': t('salePrice') });
		var bQty = input({ inputmode: 'numeric', type: 'number', min: '0', placeholder: t('quantity'), 'aria-label': t('quantity') });
		var bApply = button(t('applyAll'), { class: 'sd-btn sd-btn--sm', onclick: function () {
			if (!bReg.value.trim() && !bSale.value.trim() && !bQty.value.trim()) { bReg.focus(); return; }
			rows.forEach(function (r) {
				if (bReg.value.trim()) { r.regular_price = bReg.value.trim(); }
				if (bSale.value.trim()) { r.sale_price = bSale.value.trim(); }
				if (bQty.value.trim()) { r.stock = bQty.value.trim(); }
				r.dirty = true;
			});
			setDirty(true); drawRows();
		} });

		drawAttrs();
		drawRows();
		return {
			el: [nameList, h('p', { class: 'sd-help', text: t('optionsHelp') }), attrBox,
				h('div', { class: 'sd-opts-bar' }, makeBtn), info,
				h('details', { class: 'sd-bulk' }, h('summary', { text: t('sameForAll') }), h('div', { class: 'sd-bulk__row' }, bReg, bSale, bQty, bApply)),
				rowBox],
			check: function (publishing) {
				var named = attrs.filter(function (a) { return a.name && a.values.length; });
				if (!named.length) { return t('optionsStart'); }
				if (attrs.some(function (a) { return !a.name && a.values.length; })) { return t('optionNameMissing'); }
				if (!rows.length) { return t('makeCombosFirst'); }
				for (var i = 0; i < rows.length; i++) {
					var r = rows[i], rp = parseFloat(r.regular_price), sp = parseFloat(r.sale_price);
					if (publishing && String(r.regular_price || '').trim() === '') { return fill(t('optionNeedsPrice'), { option: label(r.options) }); }
					if (String(r.sale_price || '').trim() !== '' && (isNaN(rp) || sp >= rp)) { return label(r.options) + ': ' + t('salePriceError'); }
				}
				return '';
			},
			values: function () {
				var list = rows.map(function (r) {
					var o = { options: r.options, regular_price: String(r.regular_price || ''), sale_price: String(r.sale_price || ''), image_id: r.image_id || 0 };
					if (r.id) { o.id = r.id; }
					if (String(r.stock).trim() !== '') { o.manage_stock = true; o.stock_quantity = parseInt(r.stock, 10) || 0; } else { o.manage_stock = false; o.stock_status = r.stock_status === 'outofstock' ? 'outofstock' : 'instock'; }
					return o;
				});
				removed.forEach(function (id) { list.push({ id: id, remove: true }); });
				return { attributes: attrs.filter(function (a) { return a.name && a.values.length; }), variations: list };
			}
		};
	}

	/* ------------------------------------------------------------------ Blog posts */
	function postRow(p) {
		return h('a', { class: 'sd-row sd-post', href: '#/posts/' + p.id },
			h('span', { class: 'sd-post__img' }, p.image ? h('img', { src: escUrl(p.image), alt: '', loading: 'lazy' }) : icon('blog')),
			h('span', { class: 'sd-post__body' }, h('strong', { text: p.title || '—' }),
				h('span', { class: 'sd-muted' }, p.status !== 'publish' ? h('span', { class: 'sd-pill', text: statusLabel(p.status) }) : null, ' ' + (p.date ? p.date.text : ''))));
	}

	function viewPosts(params) {
		var search = params.search || '';
		var list = pagedList(function (page) { return api('posts?' + qs({ search: search, page: page })); }, postRow);
		var root = h('div', { class: 'sd-view' },
			pageHead(t('blogPosts'), { action: h('a', { class: 'sd-btn sd-btn--primary', href: '#/posts/new' }, icon('plus'), h('span', { text: t('newPost') })) }),
			searchBox(search, t('searchPosts'), function (v) { go('posts?' + qs({ search: v })); }), list.el);
		return list.load().then(function () { return root; });
	}

	function viewPost(id) {
		return (id ? api('posts/' + encodeURIComponent(id)) : Promise.resolve(null)).then(renderPost);
	}

	function renderPost(p) {
		var creating = !p;
		p = p || { id: 0, title_raw: '', content: '', excerpt: '', status: 'draft', featured_id: 0, featured: '', link: '', edit_url: '' };
		var root = h('div', { class: 'sd-view sd-form' }, pageHead(creating ? t('newPostTitle') : (p.title_raw || p.title), { back: '#/posts' }));
		var title = input({ value: p.title_raw, autocomplete: 'off' });
		var ed = editor(p.content, { images: true });
		var excerpt = textarea({ rows: 3 }, p.excerpt);
		var featured = { id: p.featured_id, url: p.featured };
		var coverBox = h('div', { class: 'sd-cover' });
		function drawCover() {
			clear(coverBox);
			if (featured.id) {
				coverBox.appendChild(h('img', { src: escUrl(featured.url), alt: '' }));
				coverBox.appendChild(button(t('remove'), { class: 'sd-btn sd-btn--xs sd-btn--danger', onclick: function () { featured = { id: 0, url: '' }; setDirty(true); drawCover(); } }));
			}
			if (C.caps.upload) {
				var file = h('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif', class: 'sd-offscreen', id: 'sd-file-' + (++uid) });
				var label = h('label', { class: 'sd-btn sd-btn--sm', for: file.id }, icon('image'), h('span', { text: t('choosePicture') }));
				file.addEventListener('change', function () {
					if (!file.files[0]) { return; }
					label.lastChild.textContent = t('uploading');
					upload(file.files[0]).then(function (m) { featured = { id: m.id, url: m.url }; setDirty(true); drawCover(); }, function (err) { toast(err.message, 'bad'); drawCover(); });
				});
				coverBox.appendChild(label);
				coverBox.appendChild(file);
			}
		}
		drawCover();

		function save(status, btn) {
			if (!title.value.trim()) { toast(t('requiredName'), 'bad'); title.focus(); return; }
			busy(btn, true);
			api(creating ? 'posts' : 'posts/' + p.id, { method: 'POST', body: { title: title.value.trim(), content: ed.get(), excerpt: excerpt.value.trim(), status: status, featured_id: featured.id || 0 } }).then(function (res) {
				setDirty(false); toast(t('saved'), 'ok');
				if (creating) { skipHash = true; location.hash = '#/posts/' + res.id; current = 'posts/' + res.id; }
				swap(renderPost(res));
			}, function (err) { busy(btn, false); toast(err.message, 'bad'); });
		}
		var published = p.status === 'publish';
		var mainBtn = button(published ? t('update') : (C.caps.publishPost ? t('publish') : t('submitReview')), { class: 'sd-btn sd-btn--primary sd-btn--lg' });
		mainBtn.addEventListener('click', function () { save('publish', mainBtn); });
		var draftBtn = button(published ? t('toDraft') : t('saveDraft'), { class: 'sd-btn' });
		draftBtn.addEventListener('click', function () { save('draft', draftBtn); });

		root.appendChild(card('', field(t('title'), title)));
		root.appendChild(card(t('featuredImage'), coverBox));
		root.appendChild(card(t('content'), ed.el));
		root.appendChild(card(t('excerpt'), excerpt));
		root.appendChild(h('div', { class: 'sd-savebar' }, mainBtn, draftBtn,
			p.link ? h('a', { class: 'sd-btn sd-btn--ghost', href: escUrl(p.link), target: '_blank', rel: 'noopener' }, icon('ext'), h('span', { text: t('viewPost') })) : null));
		if (p.edit_url) { root.appendChild(h('p', { class: 'sd-foot-link' }, h('a', { href: escUrl(p.edit_url) }, icon('ext'), h('span', { text: t('openInAdmin') })))); }
		return root;
	}

	/* ------------------------------------------------------------------ start */
	shell();
	window.addEventListener('hashchange', route);
	route();
}());
