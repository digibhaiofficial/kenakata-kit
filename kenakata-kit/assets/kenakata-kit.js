/* Kenakata Kit 3 – carousels, live search, phone check, checkout delivery area, login popup,
 * side cart, sticky Buy Now bar, mobile bottom bar. No dependencies (jQuery only for WooCommerce events). */
(function () {
	'use strict';
	var D = window.kkData || { i18n: {}, slider: {} };
	// REST address with a query: with WordPress's plain permalinks the base is "…/?rest_route=/kenakata/v1/", so the
	// query must start with "&" there (a second "?" made the wishlist page and "recently viewed" empty before 3.29).
	function restUrl(path, query) { var u = (D.restUrl || '') + path; return query ? u + (u.indexOf('?') > -1 ? '&' : '?') + query : u; }
	// wp_localize_script() turns numbers into strings ("0" would count as true): make the on/off flags real numbers.
	['popup', 'drawer', 'drawerAuto', 'drawerTake', 'ajaxSingle', 'cartRedirect', 'minChars'].forEach(function (k) { D[k] = Number(D[k]) || 0; });
	D.i18n = D.i18n || {};
	var T = function (key, n) { return String((D.i18n || {})[key] || '').replace('%d', n).replace('%s', n); };
	var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
	var $ = window.jQuery;

	function onReady(fn) { if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn); }

	/* ------------------------------------------------------------------
	 * Carousels (.kk-slider): arrows, dots, autoplay, loop, keyboard
	 * ---------------------------------------------------------------- */
	function initSlider(root) {
		if (root.kkSlider) return;
		root.kkSlider = true;
		var track = root.querySelector('.kk-slider__track');
		if (!track) return;
		var prev = root.querySelector('.kk-slider__arrow--prev');
		var next = root.querySelector('.kk-slider__arrow--next');
		var dotsBox = root.querySelector('.kk-slider__dots');
		var loop = root.getAttribute('data-loop') === '1';
		var auto = root.getAttribute('data-autoplay') === '1' && !reduce;
		var speed = parseInt(root.getAttribute('data-speed'), 10) || 4000;
		var paused = false, timer = null, resumeTimer = null;

		function step() {
			var first = track.children[0];
			if (!first) return track.clientWidth;
			var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
			var per = Math.max(1, Math.round((track.clientWidth + gap) / (first.getBoundingClientRect().width + gap)));
			return per * (first.getBoundingClientRect().width + gap);
		}
		function maxScroll() { return Math.max(0, track.scrollWidth - track.clientWidth); }
		function atStart() { return track.scrollLeft <= 2; }
		function atEnd() { return track.scrollLeft >= maxScroll() - 2; }
		function pages() { var s = step(); return s ? Math.max(1, Math.ceil((maxScroll() - 2) / s) + 1) : 1; }
		function current() { var s = step(); return s ? Math.min(pages() - 1, Math.round(track.scrollLeft / s)) : 0; }
		function to(left) { track.scrollTo({ left: left, behavior: reduce ? 'auto' : 'smooth' }); }
		function go(dir) {
			if (dir > 0 && atEnd()) { if (loop || auto) to(0); return; }
			if (dir < 0 && atStart()) { if (loop) to(maxScroll()); return; }
			to(Math.min(maxScroll(), Math.max(0, track.scrollLeft + dir * step())));
		}
		function buildDots() {
			if (!dotsBox) return;
			var n = pages();
			if (dotsBox.children.length === n) return;
			dotsBox.innerHTML = '';
			for (var i = 0; i < n; i++) {
				var b = document.createElement('button');
				b.type = 'button';
				b.className = 'kk-slider__dot';
				b.setAttribute('aria-label', T('slide', i + 1));
				(function (i) { b.addEventListener('click', function () { to(i * step()); }); })(i);
				dotsBox.appendChild(b);
			}
			dotsBox.hidden = n < 2;
		}
		function update() {
			var noScroll = maxScroll() < 4;
			if (prev) prev.disabled = noScroll || (!loop && atStart());
			if (next) next.disabled = noScroll || (!loop && !auto && atEnd());
			if (dotsBox) {
				buildDots();
				var c = current();
				Array.prototype.forEach.call(dotsBox.children, function (d, i) { d.setAttribute('aria-current', i === c ? 'true' : 'false'); });
			}
		}
		if (prev) prev.addEventListener('click', function () { go(-1); });
		if (next) next.addEventListener('click', function () { go(1); });
		track.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
			if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
		});
		var raf = null;
		track.addEventListener('scroll', function () { if (raf) return; raf = setTimeout(function () { raf = null; update(); }, 60); }, { passive: true });
		window.addEventListener('resize', function () { if (dotsBox) dotsBox.innerHTML = ''; update(); });

		if (auto) {
			var pause = function () { paused = true; clearTimeout(resumeTimer); };
			var resume = function (delay) { clearTimeout(resumeTimer); resumeTimer = setTimeout(function () { paused = false; }, delay || 0); };
			root.addEventListener('mouseenter', pause);
			root.addEventListener('mouseleave', function () { resume(300); });
			root.addEventListener('focusin', pause);
			root.addEventListener('focusout', function () { resume(300); });
			root.addEventListener('touchstart', pause, { passive: true });
			root.addEventListener('touchend', function () { resume(6000); }, { passive: true });
			timer = setInterval(function () {
				if (paused || document.hidden || maxScroll() < 4) return;
				var r = root.getBoundingClientRect();
				if (r.bottom < 0 || r.top > window.innerHeight) return; // off screen
				go(1);
			}, speed);
		}
		update();
		setTimeout(update, 400); // images loaded / fonts swapped
	}
	function initSliders(ctx) { (ctx || document).querySelectorAll('.kk-slider').forEach(initSlider); }

	/* ------------------------------------------------------------------
	 * Live search (icon + overlay, or inline bar)
	 * ---------------------------------------------------------------- */
	function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;'); } // also safe inside attributes

	function searchEngine(input, list, status, all, onResults) {
		var timer = null, ctrl = null, active = -1;
		function setActive(i) {
			var items = list.querySelectorAll('[role=option]');
			items.forEach(function (el, n) { el.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
			active = i;
			if (items[i]) { input.setAttribute('aria-activedescendant', items[i].id); items[i].scrollIntoView({ block: 'nearest' }); } else { input.removeAttribute('aria-activedescendant'); }
		}
		function render(data, q) {
			list.innerHTML = ''; active = -1;
			if (!data.items || !data.items.length) { status.textContent = T('none', q); all.hidden = true; input.setAttribute('aria-expanded', 'false'); onResults(true); return; }
			status.textContent = data.total === 1 ? T('foundOne') : T('found', data.total);
			data.items.forEach(function (it, i) {
				var li = document.createElement('li');
				li.id = list.id + '-' + i;
				li.setAttribute('role', 'option');
				li.setAttribute('aria-selected', 'false');
				li.innerHTML = '<a href="' + esc(it.url) + '" tabindex="-1"><img src="' + esc(it.img) + '" alt="" width="56" height="56" loading="lazy">' +
					'<span class="kk-search__name">' + esc(it.name) + (it.in_stock ? '' : ' <em>(' + esc(T('outOfStock')) + ')</em>') + '</span>' +
					'<span class="kk-search__price">' + esc(it.price) + (it.regular ? ' <del>' + esc(it.regular) + '</del>' : '') + '</span></a>';
				list.appendChild(li);
			});
			input.setAttribute('aria-expanded', 'true');
			all.href = data.all;
			all.textContent = T('seeAll', data.total);
			all.hidden = data.total <= data.items.length;
			onResults(true);
		}
		function search() {
			var q = input.value.trim();
			if (ctrl) ctrl.abort();
			if (q.length < D.minChars) { list.innerHTML = ''; status.textContent = q.length ? T('typeMore', D.minChars) : ''; all.hidden = true; onResults(q.length > 0); return; }
			status.textContent = T('searching');
			onResults(true);
			ctrl = window.AbortController ? new AbortController() : null;
			fetch(D.searchUrl + (D.searchUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q), ctrl ? { signal: ctrl.signal } : {})
				.then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
				.then(function (d) { render(d, q); })
				.catch(function (e) { if (e.name !== 'AbortError') status.textContent = T('error'); });
		}
		input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 250); });
		input.addEventListener('keydown', function (e) {
			var items = list.querySelectorAll('[role=option]');
			if (e.key === 'ArrowDown') { e.preventDefault(); setActive(Math.min(active + 1, items.length - 1)); }
			else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(Math.max(active - 1, -1)); }
			else if (e.key === 'Enter' && active > -1 && items[active]) { e.preventDefault(); window.location = items[active].querySelector('a').href; }
		});
	}

	function initSearch(root) {
		if (root.kkSearch) return;
		root.kkSearch = true;
		if (root.classList.contains('kk-search--bar')) {
			var input = root.querySelector('.kk-search__input');
			var drop = root.querySelector('.kk-search__drop');
			if (!input || !drop) return;
			searchEngine(input, drop.querySelector('.kk-search__results'), drop.querySelector('.kk-search__status'), drop.querySelector('.kk-search__all'), function (show) { drop.hidden = !show; });
			document.addEventListener('click', function (e) { if (!root.contains(e.target)) { drop.hidden = true; input.setAttribute('aria-expanded', 'false'); } });
			input.addEventListener('focus', function () { if (drop.querySelector('.kk-search__results').children.length) drop.hidden = false; });
			root.addEventListener('keydown', function (e) { if (e.key === 'Escape') { drop.hidden = true; input.setAttribute('aria-expanded', 'false'); } });
			return;
		}
		var toggle = root.querySelector('.kk-search__toggle');
		var panel = root.querySelector('.kk-search__panel');
		if (!toggle || !panel) return;
		var inp = panel.querySelector('.kk-search__input');
		document.body.appendChild(panel); // never clipped by header overflow/transform
		function open() { panel.hidden = false; toggle.setAttribute('aria-expanded', 'true'); document.documentElement.classList.add('kk-search-open'); setTimeout(function () { inp.focus(); }, 30); }
		function close() { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); document.documentElement.classList.remove('kk-search-open'); if (toggle.offsetParent) toggle.focus(); }
		root.kkOpen = open;
		searchEngine(inp, panel.querySelector('.kk-search__results'), panel.querySelector('.kk-search__status'), panel.querySelector('.kk-search__all'), function () {});
		toggle.addEventListener('click', function () { if (panel.hidden) open(); else close(); });
		panel.querySelector('.kk-search__close').addEventListener('click', close);
		panel.addEventListener('click', function (e) { if (e.target === panel) close(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
	}

	/** Bengali digits ০-৯ to 0-9 (customers often type their number with a Bengali keyboard). */
	function enDigits(v) { return String(v).replace(/[\u09E6-\u09EF]/g, function (c) { return String(c.charCodeAt(0) - 0x09E6); }); }

	/* ------------------------------------------------------------------
	 * Phone inputs (11-digit mode): digits only, max 11, live hint
	 * ---------------------------------------------------------------- */
	document.addEventListener('input', function (e) {
		var el = e.target;
		if (!el.matches || !el.matches('input[data-kk-phone="bd11"]')) return;
		var digits = enDigits(el.value).replace(/\D+/g, '').slice(0, 11);
		if (el.value !== digits) el.value = digits;
		var ok = /^01[3-9]\d{8}$/.test(digits);
		el.setCustomValidity(digits.length && !ok ? (D.i18n.phone11 || 'Please enter an 11-digit mobile number, e.g. 01712345678.') : '');
		el.classList.toggle('kk-phone-ok', ok);
	});

	/* ------------------------------------------------------------------
	 * Checkout: delivery area -> recalculate
	 * ---------------------------------------------------------------- */
	function initCheckout() {
		if (!$ || !document.querySelector('form.checkout') || document.querySelector('.kk-co-mock')) return;
		var $form = $('form.checkout');
		function sync() { $form.toggleClass('kk-no-area', $form.find('input[name=billing_delivery_area]').length > 0 && !$form.find('input[name=billing_delivery_area]:checked').length); }
		sync();
		$(document.body).on('change', 'input[name=billing_delivery_area]', function () { sync(); $(document.body).trigger('update_checkout'); });
		$(document.body).on('updated_checkout', sync);
	}

	/* ------------------------------------------------------------------
	 * Login / registration popup
	 * ---------------------------------------------------------------- */
	function initPopup() {
		var modal = document.getElementById('kk-auth');
		if (!modal || !window.kkAuth) return;
		var lastFocus = null;
		var tabs = modal.querySelectorAll('.kk-tab');
		function show(which) {
			tabs.forEach(function (t) {
				var on = t.id === 'kk-tab-' + which;
				t.classList.toggle('is-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
			});
		}
		function open(which) {
			lastFocus = document.activeElement;
			modal.hidden = false;
			document.documentElement.classList.add('kk-modal-open');
			if (tabs.length) show(which || 'login');
			var first = modal.querySelector('form:not([hidden]) input');
			if (first) setTimeout(function () { first.focus(); }, 30);
		}
		function close() { modal.hidden = true; document.documentElement.classList.remove('kk-modal-open'); if (lastFocus) lastFocus.focus(); }
		document.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('a[href]');
			if (!a || !D.popup || document.body.classList.contains('logged-in')) return;
			var href = (a.getAttribute('href') || '').replace(/\/+$/, '');
			if (href === String(D.account).replace(/\/+$/, '') || a.hasAttribute('data-kk-login') || a.hasAttribute('data-ss-login')) { e.preventDefault(); open('login'); }
		});
		modal.querySelectorAll('[data-kk-close]').forEach(function (b) { b.addEventListener('click', close); });
		tabs.forEach(function (t) {
			t.addEventListener('click', function () { show(t.id.replace('kk-tab-', '')); });
			t.addEventListener('keydown', function (e) {
				if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { var other = t.id === 'kk-tab-login' ? 'register' : 'login'; show(other); document.getElementById('kk-tab-' + other).focus(); }
			});
		});
		modal.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') close();
			if (e.key !== 'Tab') return;
			var f = Array.prototype.filter.call(modal.querySelectorAll('button, input, a[href]'), function (el) { return el.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
		// Cloudflare Turnstile: its script loads only when the popup is opened (not on every page view).
		var tsLoaded = false;
		function loadTurnstile() {
			if (tsLoaded || !window.kkAuth.ts) return;
			tsLoaded = true;
			window.kkTsReady = function () {
				modal.querySelectorAll('[data-kk-ts]').forEach(function (el) {
					el.setAttribute('data-ts-id', window.turnstile.render(el, { sitekey: window.kkAuth.ts, size: 'flexible' }));
				});
			};
			var sc = document.createElement('script');
			sc.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=kkTsReady';
			sc.async = true; sc.defer = true;
			document.head.appendChild(sc);
		}
		new MutationObserver(function () { if (!modal.hidden) loadTurnstile(); }).observe(modal, { attributes: true, attributeFilter: ['hidden'] });
		modal.querySelectorAll('.kk-auth-form').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = form.querySelector('.kk-form-msg');
				var btn = form.querySelector('button[type=submit]');
				function send() {
					var body = new FormData(form);
					body.append('action', form.getAttribute('data-action'));
					body.append('nonce', window.kkAuth.nonce);
					return fetch(D.ajax, { method: 'POST', body: body, credentials: 'same-origin' });
				}
				msg.textContent = ''; msg.classList.remove('is-ok');
				btn.disabled = true; btn.classList.add('is-loading');
				send()
					.then(function (r) {
						if (r.status !== 403) return r;
						// The page came from a cache and its security code is too old: get a fresh one and try once more.
						var b = new FormData(); b.append('action', 'kk_auth_nonce');
						return fetch(D.ajax, { method: 'POST', body: b, credentials: 'same-origin' })
							.then(function (n) { return n.json(); })
							.then(function (n) { if (n && n.success) window.kkAuth.nonce = n.data.nonce; return send(); });
					})
					.then(function (r) { return r.json(); })
					.then(function (res) {
						if (res && res.success) { msg.classList.add('is-ok'); msg.textContent = T('success'); window.location.reload(); }
						else {
							msg.textContent = (res && res.data && res.data.message) || T('failed'); btn.disabled = false;
							var ts = form.querySelector('[data-ts-id]'); // a Turnstile answer works once: get a new one
							if (ts && window.turnstile) window.turnstile.reset(ts.getAttribute('data-ts-id'));
						}
					})
					.catch(function () { msg.textContent = T('network'); btn.disabled = false; })
					.then(function () { btn.classList.remove('is-loading'); });
			});
		});
	}

	/* ------------------------------------------------------------------
	 * Side cart
	 * ---------------------------------------------------------------- */
	function initDrawer() {
		var drawer = document.getElementById('kk-drawer');
		if (!drawer) return;
		var panel = drawer.querySelector('.kk-drawer__panel');
		var last = null;
		function open() {
			last = document.activeElement;
			drawer.hidden = false;
			document.documentElement.classList.add('kk-drawer-open');
			setTimeout(function () { drawer.classList.add('is-open'); }, 20);
			setTimeout(function () { var c = drawer.querySelector('.kk-drawer__close'); if (c) c.focus(); }, 60);
		}
		function close() {
			drawer.classList.remove('is-open');
			document.documentElement.classList.remove('kk-drawer-open');
			setTimeout(function () { drawer.hidden = true; }, reduce ? 0 : 260);
			if (last && last.focus) last.focus();
		}
		// Header cart icons of Elementor's "Menu Cart" widget and common themes open the side cart (capture phase, before their own handlers).
		if (D.drawerTake) {
			var take = '.elementor-menu-cart__toggle_button, .elementor-menu-cart__toggle .elementor-button, a.cart-contents, .site-header-cart > li > a, .ast-site-header-cart .ast-cart-menu-wrap, .header-cart-link';
			var takeOpen = function (e) {
				var t = e.target.closest && e.target.closest(take);
				if (!t || t.closest('.kk-drawer')) return;
				e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation();
				if (e.type === 'click') open();
			};
			document.addEventListener('click', takeOpen, true);
			document.addEventListener('mouseover', function (e) { if (e.target.closest && e.target.closest(take)) e.stopImmediatePropagation(); }, true); // "open on hover" panels stay shut
		}
		document.addEventListener('click', function (e) {
			var t = e.target.closest && e.target.closest('[data-kk-drawer]');
			if (t) { e.preventDefault(); open(); return; }
			if (e.target.closest && e.target.closest('[data-kk-drawer-close]')) close();
		});
		drawer.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') close();
			if (e.key !== 'Tab') return;
			var f = Array.prototype.filter.call(panel.querySelectorAll('a[href], button, input'), function (el) { return el.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
		if ($ && D.drawerAuto) {
			$(document.body).on('added_to_cart', function (e, fragments, hash, $btn) {
				open();
				// WooCommerce adds a "View cart" link after the button; the open side cart already shows the cart.
				setTimeout(function () {
					var b = $btn && $btn.length ? $btn : null;
					(b ? b.parent().find('.added_to_cart.wc-forward') : $('.added_to_cart.wc-forward')).remove();
				}, 0);
			});
		}
		if (!$ && D.drawerAuto) document.addEventListener('kk:added', function () { open(); });
		if (D.ajaxSingle && D.wcAjax) initAjaxSingle();
	}

	/* Product page "Add to cart" without reloading: adds in the background, then the side cart opens.
	 * Buy Now (and anything we cannot handle) falls back to the normal form submit. */
	function initAjaxSingle() {
		document.addEventListener('submit', function (e) {
			var form = e.target;
			if (!form.matches || !form.matches('form.cart') || form.closest('.grouped_form') || form.classList.contains('grouped_form')) return;
			var sub = e.submitter;
			if (sub && (sub.name === 'kk_buy_now' || sub.classList.contains('kk-buy-now'))) return;
			if (form.querySelector('input[name=kk_buy_now][value="1"]')) return;
			var btn = form.querySelector('.single_add_to_cart_button');
			if (!btn || btn.classList.contains('disabled')) return;
			var fd = new FormData(form);
			var variation = fd.get('variation_id');
			var id = variation && variation !== '0' ? variation : (fd.get('add-to-cart') || btn.value || fd.get('product_id'));
			if (!id) return;
			e.preventDefault();
			var body = new URLSearchParams();
			body.append('product_id', id);
			body.append('quantity', fd.get('quantity') || 1);
			fd.forEach(function (v, k) { if (k.indexOf('attribute_') === 0) body.append(k, v); });
			btn.classList.add('loading');
			fetch(D.wcAjax.replace('%%endpoint%%', 'add_to_cart'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					btn.classList.remove('loading');
					if (!res || res.error) { HTMLFormElement.prototype.submit.call(form); return; }
					btn.classList.add('added');
					if ($) $(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, $(btn)]);
				})
				.catch(function () { btn.classList.remove('loading'); HTMLFormElement.prototype.submit.call(form); });
		});
	}

	/* ------------------------------------------------------------------
	 * Sticky Buy Now bar (product page, phones)
	 * ---------------------------------------------------------------- */
	function initSticky() {
		var bar = document.querySelector('.kk-sticky');
		var form = document.querySelector('form.cart');
		if (!bar || !form) return;
		var bbar = document.querySelector('.kk-bbar');
		var shown = null, wait = null;
		bar.hidden = false;
		function check() {
			wait = null;
			var show = form.getBoundingClientRect().bottom < 0; // the add-to-cart form has scrolled out of view
			if (show === shown) return;
			shown = show;
			bar.classList.toggle('is-visible', show);
			document.body.classList.toggle('kk-has-sticky', show);
			if (bbar) bbar.classList.toggle('is-hidden', show); // the sticky bar takes the bottom bar's place
		}
		window.addEventListener('scroll', function () { if (!wait) wait = setTimeout(check, 80); }, { passive: true });
		window.addEventListener('resize', check);
		check();
		bar.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-kk-sticky]');
			if (!btn) return;
			var addBtn = form.querySelector('.single_add_to_cart_button');
			var buyBtn = form.querySelector('[name=kk_buy_now]');
			var needsChoice = addBtn && (addBtn.classList.contains('disabled') || addBtn.classList.contains('wc-variation-selection-needed'));
			if (needsChoice || !addBtn) {
				form.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
				var sel = form.querySelector('select, input:not([type=hidden])');
				if (sel) setTimeout(function () { sel.focus(); }, 400);
				return;
			}
			if (btn.getAttribute('data-kk-sticky') === 'buy' && buyBtn) buyBtn.click();
			else addBtn.click();
		});
	}

	/* ------------------------------------------------------------------
	 * Mobile Bottom Bar: search item opens the search overlay, optional hide-on-scroll
	 * ---------------------------------------------------------------- */
	function initBottomBar() {
		document.addEventListener('click', function (e) {
			var t = e.target.closest && e.target.closest('[data-kk-search-open]');
			if (!t) return;
			var box = document.querySelector('.kk-bbar-search .kk-search') || document.querySelector('.kk-search:not(.kk-search--bar)');
			if (box && box.kkOpen) { e.preventDefault(); box.kkOpen(); return; }
			var field = document.querySelector('.kk-search--bar .kk-search__input');
			if (field) { e.preventDefault(); field.scrollIntoView({ block: 'center' }); field.focus(); }
			// otherwise the link opens the normal product search page
		});
		var bar = document.querySelector('.kk-bbar--autohide');
		if (!bar) return;
		var lastY = window.pageYOffset, wait = null;
		window.addEventListener('scroll', function () {
			if (wait) return;
			wait = setTimeout(function () {
				wait = null;
				var y = window.pageYOffset;
				if (document.body.classList.contains('kk-has-sticky')) return;
				if (Math.abs(y - lastY) < 8) return;
				bar.classList.toggle('is-hidden', y > lastY && y > 120);
				lastY = y;
			}, 60);
		}, { passive: true });
	}
	/* ------------------------------------------------------------------
	 * Variation buttons (product page): the buttons choose a value in WooCommerce's hidden dropdown
	 * ---------------------------------------------------------------- */
	function initSwatches(ctx) {
		(ctx || document).querySelectorAll('.kk-swatches-wrap').forEach(function (wrap) {
			if (wrap.kkSwatches) return;
			var select = wrap.querySelector('select');
			var group = wrap.querySelector('.kk-swatches');
			var form = wrap.closest('form');
			if (!select || !group) return;
			wrap.kkSwatches = true;
			if (form) {
				form.classList.add('kk-sw-form');
				if (wrap.getAttribute('data-kk-clear') === '0') form.classList.add('kk-sw-noclear');
			}
			var buttons = Array.prototype.slice.call(group.querySelectorAll('.kk-swatch'));
			var row = wrap.closest('tr');
			var labelCell = row && row.querySelector('th, .label');
			var current = null;
			if (labelCell) {
				current = document.createElement('span');
				current.className = 'kk-swatch-current';
				labelCell.appendChild(current);
			}
			var variations = null;
			try { variations = form ? JSON.parse(form.getAttribute('data-product_variations') || 'null') : null; } catch (err) { variations = null; }
			// Is there an in-stock variation with this value, given what is chosen in the other dropdowns?
			function inStock(value) {
				if (!Array.isArray(variations)) return true; // large products load variations later: let WooCommerce decide
				var chosen = {};
				form.querySelectorAll('.variations select').forEach(function (s) { if (s !== select && s.value) chosen[s.name] = s.value; });
				return variations.some(function (v) {
					var at = v.attributes || {};
					if (!v.is_in_stock || (at[select.name] && at[select.name] !== value)) return false;
					for (var k in chosen) { if (at[k] && at[k] !== chosen[k]) return false; }
					return true;
				});
			}
			function sync() {
				var available = {};
				Array.prototype.forEach.call(select.options, function (o) { if (o.value) available[o.value] = !o.disabled; });
				var focusable = null;
				buttons.forEach(function (b) {
					var v = b.getAttribute('data-value');
					var on = select.value === v;
					var ok = available[v] === true && inStock(v);
					b.classList.toggle('is-selected', on);
					b.classList.toggle('is-disabled', !ok);
					b.setAttribute('aria-checked', on ? 'true' : 'false');
					b.setAttribute('aria-disabled', ok ? 'false' : 'true');
					b.tabIndex = -1;
					if (on) focusable = b;
				});
				(focusable || buttons.filter(function (b) { return !b.classList.contains('is-disabled'); })[0] || buttons[0]).tabIndex = 0;
				if (current) {
					var sel = buttons.filter(function (b) { return b.classList.contains('is-selected'); })[0];
					current.textContent = sel ? sel.textContent : '';
				}
			}
			function choose(b) {
				if (!b || b.classList.contains('is-disabled')) return;
				var v = b.getAttribute('data-value');
				select.value = select.value === v ? '' : v; // a second click clears the choice
				if ($) $(select).trigger('change'); else select.dispatchEvent(new Event('change', { bubbles: true }));
				sync();
			}
			group.addEventListener('click', function (e) { choose(e.target.closest('.kk-swatch')); });
			group.addEventListener('keydown', function (e) {
				var i = buttons.indexOf(document.activeElement);
				if (i < 0) return;
				var step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
				if (step) {
					e.preventDefault();
					for (var n = 1; n <= buttons.length; n++) {
						var b = buttons[(i + step * n + buttons.length * n) % buttons.length];
						if (!b.classList.contains('is-disabled')) { b.focus(); break; }
					}
				} else if (e.key === ' ' || e.key === 'Enter') {
					e.preventDefault();
					choose(buttons[i]);
				}
			});
			if ($ && form) $(form).on('woocommerce_update_variation_values reset_data', function () { setTimeout(sync, 0); });
			select.addEventListener('change', sync);
			sync();
		});
	}

	/* ------------------------------------------------------------------
	 * Reviews: "Write a review" button, photo field (preview + limits), photo lightbox
	 * ---------------------------------------------------------------- */
	function initReviews() {
		// "Read more" under a shortened description: open the Description tab and scroll to it.
		document.addEventListener('click', function (e) {
			var more = e.target.closest && e.target.closest('a.kk-more-link');
			if (!more) return;
			var tab = document.querySelector('.wc-tabs a[href="#tab-description"], .woocommerce-tabs a[href="#tab-description"]');
			var panel = document.getElementById('tab-description');
			if (!tab && !panel) return;
			e.preventDefault();
			if (tab) tab.click();
			var target = (tab && tab.closest('.woocommerce-tabs')) || panel;
			target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
		});
		document.addEventListener('click', function (e) {
			var w = e.target.closest && e.target.closest('[data-kk-write-review]');
			if (!w) return;
			var form = document.getElementById('review_form_wrapper');
			if (!form) return;
			e.preventDefault();
			form.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
			var first = form.querySelector('.stars a, #rating, #comment');
			if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, reduce ? 0 : 450);
		});

		document.querySelectorAll('.kk-photo-field').forEach(function (box) {
			var input = box.querySelector('input[type=file]');
			var preview = box.querySelector('.kk-photo-field__preview');
			var max = parseInt(box.getAttribute('data-max'), 10) || 3;
			var maxBytes = (parseInt(box.getAttribute('data-max-mb'), 10) || 3) * 1048576;
			var okTypes = ['image/jpeg', 'image/png', 'image/webp'];
			if (!input || !preview) return;
			var urls = [];
			function show(files, note) {
				urls.forEach(function (u) { URL.revokeObjectURL(u); });
				urls = [];
				preview.innerHTML = '';
				files.forEach(function (f) {
					var u = URL.createObjectURL(f);
					urls.push(u);
					var img = document.createElement('img');
					img.src = u; img.alt = f.name; img.className = 'kk-photo-field__thumb';
					preview.appendChild(img);
				});
				if (note) { var p = document.createElement('p'); p.className = 'kk-photo-field__note'; p.textContent = note; preview.appendChild(p); }
			}
			input.addEventListener('change', function () {
				var all = Array.prototype.slice.call(input.files || []);
				var good = all.filter(function (f) { return okTypes.indexOf(f.type) !== -1 && f.size <= maxBytes; });
				var note = '';
				if (good.length < all.length) note = (D.i18n.photoBad || '').replace('%d', Math.round(maxBytes / 1048576));
				if (good.length > max) { good = good.slice(0, max); note = (D.i18n.photoMax || '').replace('%d', max); }
				if (good.length !== all.length) {
					try { var dt = new DataTransfer(); good.forEach(function (f) { dt.items.add(f); }); input.files = dt.files; }
					catch (err) { if (good.length !== all.length) { input.value = ''; good = []; } }
				}
				show(good, note);
			});
		});

		// Lightbox for review photos (groups: one review, or the customer photos strip).
		var box = null, list = [], at = 0, last = null;
		function build() {
			box = document.createElement('div');
			box.className = 'kk-lightbox';
			box.hidden = true;
			box.setAttribute('role', 'dialog');
			box.setAttribute('aria-modal', 'true');
			box.setAttribute('aria-label', D.i18n.photo || 'Photo');
			box.innerHTML = '<div class="kk-lightbox__backdrop" data-kk-lb-close></div><figure class="kk-lightbox__fig"><img class="kk-lightbox__img" alt=""><figcaption class="kk-lightbox__cap"></figcaption></figure>'
				+ '<button type="button" class="kk-lightbox__btn kk-lightbox__close" data-kk-lb-close aria-label="' + esc(D.i18n.close || 'Close') + '">&times;</button>'
				+ '<button type="button" class="kk-lightbox__btn kk-lightbox__prev" aria-label="' + esc(D.i18n.prev || 'Previous') + '">&#8249;</button>'
				+ '<button type="button" class="kk-lightbox__btn kk-lightbox__next" aria-label="' + esc(D.i18n.next || 'Next') + '">&#8250;</button>';
			document.body.appendChild(box);
			box.addEventListener('click', function (e) {
				if (e.target.closest('[data-kk-lb-close]')) close();
				else if (e.target.closest('.kk-lightbox__prev')) go(-1);
				else if (e.target.closest('.kk-lightbox__next')) go(1);
			});
			box.addEventListener('keydown', function (e) {
				if (e.key === 'Escape') close();
				else if (e.key === 'ArrowLeft') go(-1);
				else if (e.key === 'ArrowRight') go(1);
				else if (e.key === 'Tab') {
					var f = Array.prototype.filter.call(box.querySelectorAll('button'), function (b) { return !b.hidden; });
					if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
					else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
				}
			});
		}
		function render() {
			var a = list[at];
			box.querySelector('.kk-lightbox__img').src = a.href;
			box.querySelector('.kk-lightbox__img').alt = a.getAttribute('aria-label') || '';
			box.querySelector('.kk-lightbox__cap').textContent = list.length > 1 ? (at + 1) + ' / ' + list.length : '';
			box.querySelector('.kk-lightbox__prev').hidden = list.length < 2;
			box.querySelector('.kk-lightbox__next').hidden = list.length < 2;
		}
		function go(d) { if (list.length < 2) return; at = (at + d + list.length) % list.length; render(); }
		function close() { box.hidden = true; document.documentElement.classList.remove('kk-modal-open'); if (last) last.focus(); }
		document.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('a[data-kk-lightbox]');
			if (!a || e.ctrlKey || e.metaKey) return;
			e.preventDefault();
			if (!box) build();
			last = a;
			list = Array.prototype.slice.call(document.querySelectorAll('a[data-kk-lightbox="' + a.getAttribute('data-kk-lightbox') + '"]'));
			at = Math.max(0, list.indexOf(a));
			render();
			box.hidden = false;
			document.documentElement.classList.add('kk-modal-open');
			box.querySelector('.kk-lightbox__close').focus();
		});
	}

	/* ------------------------------------------------------------------
	 * Product cards: Add to cart for variable products with a default variation (AJAX, like simple products)
	 * ---------------------------------------------------------------- */
	function initVariationAdd() {
		if (!$ || !D.varAdd) return; // AJAX add to cart is off in WooCommerce: the link adds the product with a page load
		$(document.body).on('click', '.kk-var-add', function (e) {
			var $b = $(this);
			e.preventDefault();
			if ($b.hasClass('loading')) return;
			$b.removeClass('added').addClass('loading');
			$(document.body).trigger('adding_to_cart', [$b, { product_id: $b.data('product_id') }]);
			$.post(D.varAdd, { product_id: $b.data('product_id'), quantity: $b.data('quantity') || 1 })
				.done(function (res) {
					if (!res || res.error || !res.fragments) { window.location = (res && res.product_url) || $b.attr('href'); return; }
					if (D.cartRedirect) { window.location = D.cartUrl; return; }
					if (!window.wc_add_to_cart_params) $.each(res.fragments, function (key, html) { $(key).replaceWith(html); });
					$(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, $b]);
					if (!window.wc_add_to_cart_params) $b.addClass('added');
				})
				.fail(function () { window.location = $b.attr('href'); })
				.always(function () { $b.removeClass('loading'); });
		});
	}

	/* ------------------------------------------------------------------
	 * Product lists: AJAX page numbers and "Load more"
	 * ---------------------------------------------------------------- */
	function initGridPaging() {
		var pushed = false;
		document.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('.kk-pagination[data-kk-ajax] a.page-numbers, a[data-kk-more]');
			if (!a || !window.fetch || !window.DOMParser || e.ctrlKey || e.metaKey || e.shiftKey) return;
			var wrap = a.closest('.kk-products-wrap[data-kk-grid]');
			if (!wrap) return;
			e.preventDefault();
			if (wrap.classList.contains('is-loading')) return;
			var id = wrap.getAttribute('data-kk-grid');
			var same = document.querySelectorAll('.kk-products-wrap[data-kk-grid="' + id + '"]');
			var index = Array.prototype.indexOf.call(same, wrap);
			var more = a.hasAttribute('data-kk-more');
			var url = a.href;
			wrap.classList.add('is-loading');
			a.setAttribute('aria-busy', 'true');
			fetch(url, { credentials: 'same-origin' }).then(function (r) {
				if (!r.ok) throw new Error(r.status);
				return r.text();
			}).then(function (html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var fresh = doc.querySelectorAll('.kk-products-wrap[data-kk-grid="' + id + '"]')[index];
				var list = wrap.querySelector('ul.kk-products');
				var add = fresh && fresh.querySelector('ul.kk-products');
				if (!fresh || !list || !add) throw new Error('missing');
				if (more) {
					var first = add.firstElementChild;
					while (add.firstElementChild) list.appendChild(add.firstElementChild);
					var oldMore = wrap.querySelector('.kk-loadmore');
					var newMore = fresh.querySelector('.kk-loadmore');
					if (oldMore) { if (newMore) oldMore.parentNode.replaceChild(document.importNode(newMore, true), oldMore); else oldMore.parentNode.removeChild(oldMore); }
					var link = first && (first.querySelector('.kk-pcard__title a') || first.querySelector('a:not([tabindex="-1"])'));
					if (link) link.focus({ preventScroll: true });
				} else {
					list.parentNode.replaceChild(document.importNode(add, true), list);
					['.kk-pagination', '.woocommerce-result-count'].forEach(function (sel) {
						var o = wrap.querySelector(sel), n = fresh.querySelector(sel);
						if (o && n) o.parentNode.replaceChild(document.importNode(n, true), o);
					});
					if (window.history && history.pushState) { history.pushState({ kk: 1 }, '', url); pushed = true; }
					var top = wrap.getBoundingClientRect().top + window.pageYOffset - 90;
					if (top < window.pageYOffset) window.scrollTo({ top: top, behavior: reduce ? 'auto' : 'smooth' });
				}
				if ($) $(document.body).trigger('kk_products_loaded', [wrap]);
			}).catch(function () {
				window.location = url; // fall back to a normal page load
			}).then(function () {
				wrap.classList.remove('is-loading');
				var busy = wrap.querySelector('[aria-busy]');
				if (busy) busy.removeAttribute('aria-busy');
			});
		});
		window.addEventListener('popstate', function () { if (pushed) window.location.reload(); });
	}

	/* ------------------------------------------------------------------
	 * Recently viewed products: remember product pages in this browser, fill "Recently viewed" lists
	 * ---------------------------------------------------------------- */
	var RECENT_KEY = 'kk_recent';
	function recentIds() {
		try { return (JSON.parse(window.localStorage.getItem(RECENT_KEY)) || []).filter(function (n) { return n > 0; }); } catch (err) { return []; }
	}
	function initRecent() {
		var body = document.body;
		var m = body && body.classList.contains('single-product') && /(?:^|\s)postid-(\d+)(?:\s|$)/.exec(body.className);
		if (m) {
			var pid = parseInt(m[1], 10);
			var list = recentIds().filter(function (n) { return n !== pid; });
			list.unshift(pid);
			try { window.localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, 20))); } catch (err) {}
		}
		document.querySelectorAll('.kk-recent[data-kk-recent]').forEach(function (box) {
			var exclude = parseInt(box.getAttribute('data-kk-exclude'), 10) || 0;
			var ids = recentIds().filter(function (n) { return n !== exclude; });
			if (!ids.length || !window.fetch || !D.restUrl) return;
			var url = restUrl('recent', 'ids=' + ids.join(',')) + '&exclude=' + exclude + '&q=' + encodeURIComponent(box.getAttribute('data-kk-recent')) + '&sig=' + encodeURIComponent(box.getAttribute('data-kk-sig'));
			fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (res) {
				if (!res || !res.html) return;
				var tmp = document.createElement('div');
				tmp.innerHTML = res.html;
				var el = tmp.firstElementChild;
				if (!el) return;
				box.parentNode.replaceChild(el, box);
				initSliders(el);
				if ($) $(document.body).trigger('kk_products_loaded', [el]);
			}).catch(function () {});
		});
	}

	/* Long product descriptions: show the first part, then "Read more" / "Show less".
	   The full text stays in the page (search engines read it); without JS everything is visible. */
	function initReadMore(ctx) {
		var R = D.readMore;
		if (!R || !document.body.classList.contains('single-product')) return;
		var boxes = [];
		(ctx || document).querySelectorAll('.woocommerce-Tabs-panel--description, .elementor-widget-woocommerce-product-content').forEach(function (el) {
			// Elementor widget: fold its inner container (newer Elementor may print the content directly).
			var inner = el.classList.contains('elementor-widget') ? (el.querySelector(':scope > .elementor-widget-container') || el) : el;
			if (boxes.indexOf(inner) < 0) boxes.push(inner);
		});
		boxes.forEach(function (box) {
			if (box.querySelector('.kk-rm') || box.closest('.kk-rm')) return;
			var wrap = document.createElement('div');
			wrap.className = 'kk-rm';
			var body = document.createElement('div');
			body.className = 'kk-rm__body';
			body.id = 'kk-rm-' + Math.random().toString(36).slice(2, 8);
			// Keep a leading "Description" tab heading outside the folded part.
			var first = box.firstElementChild;
			var keep = first && /^H[1-6]$/.test(first.tagName) && box.classList.contains('woocommerce-Tabs-panel') ? first : null;
			Array.prototype.slice.call(box.childNodes).forEach(function (n) { if (n !== keep) body.appendChild(n); });
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'kk-rm__btn';
			btn.setAttribute('aria-controls', body.id);
			btn.setAttribute('aria-expanded', 'false');
			btn.hidden = true;
			wrap.appendChild(body);
			wrap.appendChild(btn);
			box.appendChild(wrap);
			var limit = function () { return window.matchMedia('(max-width: 767px)').matches ? R.hm : R.h; };
			var open = false;
			var paint = function () {
				btn.innerHTML = '<span>' + (open ? R.less : R.more) + '</span><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="' + (open ? 'm6 15 6-6 6 6' : 'm6 9 6 6 6-6') + '"/></svg>';
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
				wrap.classList.toggle('is-open', open);
			};
			var check = function () {
				if (!body.offsetParent && !body.getClientRects().length) return; // hidden tab: measure later
				wrap.style.setProperty('--kk-rm-h', limit() + 'px');
				// Only fold when there is clearly more to read (at least ~80px more than the visible part).
				var tall = body.scrollHeight > limit() + 80;
				wrap.classList.toggle('is-folded', tall);
				btn.hidden = !tall;
			};
			btn.addEventListener('click', function () {
				open = !open;
				paint();
				if (!open) {
					var top = wrap.getBoundingClientRect().top;
					if (top < 0) window.scrollBy({ top: top - 120, behavior: 'smooth' });
				}
			});
			paint();
			check();
			if (window.ResizeObserver) new ResizeObserver(check).observe(wrap);
			window.addEventListener('load', check);
		});
		// "Read more" link next to the price: open the description fully.
		document.querySelectorAll('.kk-more-link').forEach(function (a) {
			a.addEventListener('click', function () {
				var b = document.querySelector('.kk-rm:not(.is-open) .kk-rm__btn:not([hidden])');
				if (b) setTimeout(function () { b.click(); }, 50);
			});
		});
	}

	/* Wishlist: list kept in localStorage; for logged-in customers also on the account (merged on first load). */
	var WL_KEY = 'kk_wishlist';
	var WL = D.wishlist;
	function wlGet() { try { return (JSON.parse(window.localStorage.getItem(WL_KEY)) || []).map(Number).filter(function (n) { return n > 0; }); } catch (e) { return []; } }
	function wlSet(ids) { try { window.localStorage.setItem(WL_KEY, JSON.stringify(ids.slice(0, 100))); } catch (e) {} }
	function wlRest(path, method, body) {
		var h = { 'Content-Type': 'application/json' };
		if (WL.nonce) h['X-WP-Nonce'] = WL.nonce;
		return fetch(D.restUrl + path, { method: method || 'GET', credentials: 'same-origin', headers: h, body: body ? JSON.stringify(body) : undefined }).then(function (r) { return r.ok ? r.json() : null; });
	}
	function wlPaint() {
		var ids = wlGet();
		document.querySelectorAll('[data-kk-wl]').forEach(function (b) {
			var on = ids.indexOf(parseInt(b.getAttribute('data-kk-wl'), 10)) > -1;
			b.classList.toggle('is-on', on);
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			b.setAttribute('aria-label', on ? WL.added : WL.add);
			var t = b.querySelector('.kk-wl-btn__text');
			if (t) t.textContent = on ? t.getAttribute('data-added') : t.getAttribute('data-add');
		});
		document.querySelectorAll('[data-kk-wl-count]').forEach(function (c) { c.textContent = ids.length; c.hidden = !ids.length; });
	}
	function wlToast(msg) {
		var t = document.querySelector('.kk-wl-toast');
		if (!t) { t = document.createElement('div'); t.className = 'kk-wl-toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
		t.textContent = msg;
		t.classList.add('is-on');
		clearTimeout(wlToast.t);
		wlToast.t = setTimeout(function () { t.classList.remove('is-on'); }, 2200);
	}
	function wlToggle(id) {
		var ids = wlGet(), i = ids.indexOf(id), op = i > -1 ? 'remove' : 'add';
		if (i > -1) ids.splice(i, 1); else ids.unshift(id);
		wlSet(ids);
		wlPaint();
		wlToast(op === 'add' ? WL.saved : WL.gone);
		if (WL.user) wlRest('wishlist', 'POST', { ids: ids });
		wlRest('wishlist/ping', 'POST', { id: id, op: op });
		document.querySelectorAll('[data-kk-wl-page]').forEach(function (p) { if (!p.getAttribute('data-shared')) wlRenderPage(p, op === 'remove' ? id : 0); });
	}
	function wlRenderPage(page, removed) {
		var shared = page.getAttribute('data-shared');
		var ids = shared ? shared.split(',').map(Number) : wlGet();
		var list = page.querySelector('.kk-wl-page__list'), empty = page.querySelector('.kk-wl-page__empty'), bar = page.querySelector('.kk-wl-page__bar');
		if (removed && !shared) {
			var card = list.querySelector('.kk-pcard[data-product-id="' + removed + '"]');
			var li = card && card.closest('li');
			if (li) li.parentNode.removeChild(li);
		}
		var show = function (has) {
			empty.hidden = has; bar.hidden = !has;
			if (!has) list.innerHTML = '';
			var c = page.querySelector('.kk-wl-page__count');
			if (c) c.textContent = ids.length === 1 ? WL.one : WL.count.replace('%d', ids.length);
			var s = page.querySelector('.kk-wl-share');
			if (s) s.href = 'https://wa.me/?text=' + encodeURIComponent(WL.share.replace('%s', WL.site) + ' ' + (WL.page || location.href.split('?')[0]) + '?kk_wl=' + ids.join(','));
		};
		if (!ids.length) { show(false); return; }
		if (removed) { show(true); return; }
		fetch(restUrl('wishlist/products', 'ids=' + ids.join(',')) + '&cols=' + encodeURIComponent(page.getAttribute('data-cols') || '{}'), { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (res) {
				if (!res || !res.html) { show(false); return; }
				if (!shared && res.ids && res.ids.length !== ids.length) { ids = res.ids; wlSet(ids); wlPaint(); } // drop deleted products
				list.innerHTML = res.html;
				show(true);
				wlPaint();
				initSliders(list);
				if ($) $(document.body).trigger('kk_products_loaded', [list]);
			}).catch(function () { show(false); });
	}
	function initWishlist() {
		if (!WL || !window.fetch) return;
		document.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-kk-wl]');
			if (!b) return;
			e.preventDefault();
			e.stopPropagation();
			wlToggle(parseInt(b.getAttribute('data-kk-wl'), 10));
		}, true);
		var start = function () { wlPaint(); document.querySelectorAll('[data-kk-wl-page]').forEach(function (p) { wlRenderPage(p); }); };
		if (WL.user) {
			// Logged in: merge this browser's list into the account once per session, then use the account list.
			var merged = false;
			try { merged = window.sessionStorage.getItem('kk_wl_merged') === '1'; } catch (e) {}
			wlRest('wishlist', merged ? 'GET' : 'POST', merged ? null : { ids: wlGet(), merge: 1 }).then(function (res) {
				if (res && res.ids) { wlSet(res.ids); try { window.sessionStorage.setItem('kk_wl_merged', '1'); } catch (e) {} }
				start();
			}).catch(start);
		} else {
			start();
		}
		// Product cards loaded later (AJAX pages, recently viewed): paint their hearts too.
		if ($) $(document.body).on('kk_products_loaded', wlPaint);
	}

	/* Product questions: send the form without reloading; filter the list while typing. */
	function initQa() {
		document.querySelectorAll('[data-kk-qa-form]').forEach(function (form) {
			if (form.kkReady) return;
			form.kkReady = true;
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = form.querySelector('.kk-qa__msg'), btn = form.querySelector('.kk-qa__submit');
				var data = {};
				new FormData(form).forEach(function (v, k) { data[k] = v; });
				msg.className = 'kk-qa__msg';
				msg.textContent = '';
				btn.disabled = true;
				fetch(D.restUrl + 'question', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
					.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
					.then(function (res) {
						btn.disabled = false;
						if (res.ok && res.j && res.j.ok) {
							msg.className = 'kk-qa__msg is-ok';
							msg.textContent = res.j.message || '';
							form.querySelector('textarea').value = '';
						} else {
							msg.className = 'kk-qa__msg is-error';
							msg.textContent = (res.j && res.j.message) || D.i18n.failed;
						}
					}).catch(function () { btn.disabled = false; msg.className = 'kk-qa__msg is-error'; msg.textContent = D.i18n.network; });
			});
		});
		document.querySelectorAll('[data-kk-qa-search]').forEach(function (input) {
			var box = input.closest('[data-kk-qa]');
			input.addEventListener('input', function () {
				var q = input.value.trim().toLowerCase(), shown = 0;
				box.querySelectorAll('.kk-qa__item').forEach(function (li) {
					var hit = !q || li.textContent.toLowerCase().indexOf(q) > -1;
					li.hidden = !hit;
					if (hit) shown++;
				});
				var none = box.querySelector('.kk-qa__none');
				if (none) none.hidden = shown > 0;
			});
		});
	}


	/* ------------------------------------------------------------------
	 * Cart without load on the server:
	 *  - "Add to cart" / "Buy Now" buttons ([data-kk-add] / [data-kk-buy]) hold no add-to-cart link, so bots that
	 *    follow links never touch the cart. A click POSTs to ?wc-ajax=kk_add: Add to cart answers with the new cart
	 *    (side cart, count, free-delivery bar) in the same request; Buy Now answers with the checkout address.
	 *  - Cart count / side cart on page load: nothing is asked while the visitor has no cart (no WooCommerce
	 *    "items in cart" cookie: new visitors and bots). With a cart, the last answer is reused from this tab
	 *    until the cart changes; only then is the server asked once.
	 * ---------------------------------------------------------------- */
	var FRAG_KEY = 'kk_cart_fragments_2'; // _2 (3.15.1): the side cart got its own fragment
	function cookie(name) { var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)'); return m ? decodeURIComponent(m[1]) : ''; }
	function fragStore(hash, fragments) { try { if (fragments) sessionStorage.setItem(FRAG_KEY, JSON.stringify({ hash: hash || '', f: fragments })); } catch (e) { /* private mode */ } }
	function fragRead() { try { return JSON.parse(sessionStorage.getItem(FRAG_KEY) || 'null'); } catch (e) { return null; } }
	function applyFragments(f) {
		if (!f) return;
		Object.keys(f).forEach(function (sel) {
			var nodes;
			try { nodes = document.querySelectorAll(sel); } catch (e) { return; }
			Array.prototype.forEach.call(nodes, function (n) {
				var tpl = document.createElement('template');
				tpl.innerHTML = String(f[sel]).trim(); // HTML made by WooCommerce / this plugin on the server
				var el = tpl.content.firstElementChild;
				if (el) n.replaceWith(el);
			});
		});
	}
	function initCart() {
		if ($) {
			$(document.body).on('added_to_cart removed_from_cart', function (e, fragments, hash) {
				fragStore(hash, fragments);
				if (!window.wc_add_to_cart_params) applyFragments(fragments); // WooCommerce does it itself when its script is on the page
			});
		}
		// Buttons.
		document.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('[data-kk-add],[data-kk-buy]');
			if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return; // new tab: the product page
			e.preventDefault();
			if (a.classList.contains('loading')) return;
			var buy = a.hasAttribute('data-kk-buy');
			var id = a.getAttribute(buy ? 'data-kk-buy' : 'data-kk-add');
			if (!D.wcAjax || !window.fetch) { window.location = a.href; return; }
			a.classList.remove('added'); a.classList.add('loading'); a.setAttribute('aria-busy', 'true');
			if ($ && !buy) $(document.body).trigger('adding_to_cart', [$(a), { product_id: id }]);
			var body = new URLSearchParams();
			body.append('product_id', id);
			body.append('quantity', a.getAttribute('data-quantity') || 1);
			if (buy) body.append('buy', 1);
			fetch(D.wcAjax.replace('%%endpoint%%', 'kk_add'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || res.error) { window.location = (res && res.product_url) || a.href; return; }
					if (res.redirect) { window.location = res.redirect; return; } // Buy Now → checkout (the button keeps spinning)
					a.classList.remove('loading'); a.removeAttribute('aria-busy');
					if (D.cartRedirect) { window.location = D.cartUrl; return; }
					a.classList.add('added');
					if ($) $(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, $(a)]);
					else { fragStore(res.cart_hash, res.fragments); applyFragments(res.fragments); document.dispatchEvent(new CustomEvent('kk:added')); }
				})
				.catch(function () { window.location = a.href; });
		});
		// Page load: ask the server only when this visitor has a cart and this tab has no fresh copy.
		if (!cookie('woocommerce_items_in_cart')) { try { sessionStorage.removeItem(FRAG_KEY); } catch (e) {} return; }
		if (!document.querySelector('.kk-cart__count, .kk-bbar__count, #kk-drawer, .kk-fd')) return;
		var hash = cookie('woocommerce_cart_hash'), saved = fragRead();
		if (saved && hash && saved.hash === hash) { applyFragments(saved.f); return; }
		if (!D.wcAjax || !window.fetch) return;
		fetch(D.wcAjax.replace('%%endpoint%%', 'get_refreshed_fragments'), { method: 'POST', credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) { if (res && res.fragments) { fragStore(res.cart_hash, res.fragments); applyFragments(res.fragments); } })
			.catch(function () { /* the page keeps what it shows */ });
	}

	onReady(function () {
		initQa();
		initWishlist();
		initReadMore();
		initSliders();
		initSwatches();
		initReviews();
		initVariationAdd();
		initCart();
		initGridPaging();
		initRecent();
		document.querySelectorAll('.kk-search').forEach(initSearch);
		initCheckout();
		initPopup();
		initDrawer();
		initSticky();
		initBottomBar();
	});

	// Elementor editor: widgets are re-rendered without a page load.
	function hookElementor() {
		if (!window.elementorFrontend || !elementorFrontend.hooks || hookElementor.done) return;
		hookElementor.done = true;
		elementorFrontend.hooks.addAction('frontend/element_ready/global', function ($scope) {
			var el = $scope && $scope[0];
			if (!el) return;
			initSliders(el);
			// Editor only: a re-rendered product form needs WooCommerce's variation script again.
			if ($ && $.fn.wc_variation_form && elementorFrontend.isEditMode && elementorFrontend.isEditMode()) {
				$(el).find('.variations_form').each(function () { $(this).wc_variation_form(); });
			}
			initSwatches(el);
			el.querySelectorAll('.kk-search').forEach(initSearch);
		});
	}
	if ($) $(window).on('elementor/frontend/init', hookElementor);
	hookElementor();
})();
