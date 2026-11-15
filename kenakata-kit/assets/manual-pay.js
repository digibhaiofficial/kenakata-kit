/* Kenakata Kit: bKash / Nagad / Rocket by hand (checkout). Delegated events: WooCommerce re-renders the payment box. */
(function () {
	'use strict';
	var T = window.kkMP || {};
	function closest(el, sel) { while (el && el.nodeType === 1) { if (el.matches(sel)) { return el; } el = el.parentElement; } return null; }

	document.addEventListener('change', function (e) {
		var t = e.target;
		if (t.name === 'kk_mp_wallet') {
			var box = closest(t, '.kk-mp');
			if (box) { [].forEach.call(box.querySelectorAll('.kk-mp__how'), function (h) { h.hidden = h.getAttribute('data-wallet') !== t.value; }); }
		}
		if (t.id === 'kk_mp_file' && t.files && t.files[0]) {
			var wrap = closest(t, '.kk-mp');
			var state = wrap.querySelector('.kk-mp__shot-state');
			var hidden = wrap.querySelector('input[name="kk_mp_shot"]');
			var fd = new FormData();
			fd.append('shot', t.files[0]);
			hidden.value = '';
			state.textContent = T.uploading || '…';
			state.className = 'kk-mp__shot-state';
			fetch(wrap.getAttribute('data-upload'), { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.token) { hidden.value = res.token; state.textContent = T.done || '✓'; state.className = 'kk-mp__shot-state is-ok'; }
					else { state.textContent = (res && res.error) || T.failed; state.className = 'kk-mp__shot-state is-bad'; t.value = ''; }
				}, function () { state.textContent = T.failed; state.className = 'kk-mp__shot-state is-bad'; t.value = ''; });
		}
	});

	document.addEventListener('input', function (e) {
		if (e.target.id === 'kk_mp_trx') {
			var v = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
			if (v !== e.target.value) { e.target.value = v; }
		}
	});

	document.addEventListener('click', function (e) {
		var b = closest(e.target, '.kk-mp__copy');
		if (!b) { return; }
		e.preventDefault();
		var text = b.getAttribute('data-copy');
		var done = function () { var old = b.textContent; b.textContent = T.copied || '✓'; setTimeout(function () { b.textContent = old; }, 1500); };
		if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done, function () {}); }
		else { var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (x) { /* ignore */ } document.body.removeChild(ta); }
	});
}());
