/* Kenakata Kit: save a checkout the customer started but did not finish (checkout page only; shop turns it on). */
(function () {
	'use strict';
	var cfg = window.kkIC;
	if (!cfg || !cfg.url || !window.fetch) { return; }
	var KEY = 'kk_ic', timer = null, last = '';
	function q(sel) {
		for (var i = 0; i < sel.length; i++) {
			var e = document.querySelector(sel[i]);
			if (e && e.value && String(e.value).trim()) { return String(e.value).trim(); }
		}
		return '';
	}
	function token() { try { return window.localStorage.getItem(KEY) || ''; } catch (e) { return ''; } }
	function collect() {
		return {
			token: token(),
			phone: q(['#billing_phone', '#billing-phone', '#shipping-phone', 'input[type=tel]']),
			name: [q(['#billing_first_name', '#billing-first_name', '#shipping-first_name']), q(['#billing_last_name', '#billing-last_name', '#shipping-last_name'])].join(' ').trim(),
			email: q(['#billing_email', '#email']),
			address: q(['#billing_address_1', '#billing-address_1', '#shipping-address_1']),
			city: q(['#billing_city', '#billing-city', '#shipping-city', '#billing_delivery_area'])
		};
	}
	function valid(p) { return /^(?:\+?88)?01[3-9]\d{8}$/.test(String(p).replace(/[\s-]/g, '')); }
	function send() {
		var d = collect();
		if (!valid(d.phone)) { return; }
		var body = JSON.stringify(d);
		if (body === last) { return; }
		last = body;
		fetch(cfg.url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) { if (j && j.token) { try { window.localStorage.setItem(KEY, j.token); } catch (e) { /* private mode */ } } })
			.catch(function () { /* never bother the customer */ });
	}
	function queue() { window.clearTimeout(timer); timer = window.setTimeout(send, 1500); }
	function inCheckout(el) { return el && el.closest && el.closest('form.checkout, .wc-block-checkout, .wp-block-woocommerce-checkout'); }
	document.addEventListener('input', function (e) { if (inCheckout(e.target)) { queue(); } }, true);
	document.addEventListener('change', function (e) { if (inCheckout(e.target)) { queue(); } }, true);
	document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { send(); } });
	window.addEventListener('pagehide', send);
	queue(); // values the browser filled in by itself
}());
