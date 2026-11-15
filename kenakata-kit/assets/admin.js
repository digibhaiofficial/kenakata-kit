/* Kenakata Kit admin: colour pickers, sliders, switches, show/hide rules, dashboard switches, copy buttons, live preview. */
jQuery(function ($) {
	'use strict';
	var A = window.kkAdmin || {};
	var $form = $('.kk-form');
	var $preview = $('.kk-preview');
	var $dirty = $('.kk-form__dirty');

	/* Live preview: setting key -> CSS variable (+ unit) */
	var VARS = {
		color_primary: ['--kk-primary'], color_primary_hover: ['--kk-primary-hover'], color_accent: ['--kk-accent'], color_accent_hover: ['--kk-accent-hover'],
		color_badge: ['--kk-badge'], color_price: ['--kk-price'], color_text: ['--kk-text'], color_muted: ['--kk-muted'], color_border: ['--kk-border'],
		color_surface_soft: ['--kk-soft'], color_surface_brand: ['--kk-soft-primary'], color_surface_accent: ['--kk-soft-accent'],
		radius: ['--kk-radius', 'px'], font_family: ['--kk-font'], btn_height: ['--kk-btn-height', 'px'], btn_radius: ['--kk-btn-radius', 'px'],
		btn_font_size: ['--kk-btn-font-size', 'px'], btn_font_weight: ['--kk-btn-weight'], input_height: ['--kk-input-height', 'px'], input_radius: ['--kk-input-radius', 'px'],
		card_image_ratio: ['--kk-card-ratio'], card_image_fit: ['--kk-card-fit'], card_title_lines: ['--kk-card-lines'],
		card_title_size: ['--kk-card-title-size', 'px'], card_price_size: ['--kk-card-price-size', 'px']
	};

	function keyOf(el) {
		var m = /kk_settings\[([a-z0-9_]+)\]/.exec(el.name || '');
		return m ? m[1] : '';
	}
	function valueOf(key) {
		var $el = $form.find('[name="kk_settings[' + key + ']"]').not('[type=hidden]');
		if (!$el.length) return null;
		if ($el.is(':checkbox')) return $el.is(':checked') ? 1 : 0;
		if ($el.is(':radio')) return $el.filter(':checked').val();
		return $el.val();
	}
	function updatePreview(key, val) {
		if (!$preview.length) return;
		var el = $preview[0];
		if (VARS[key]) {
			var v = String(val == null ? '' : val).trim();
			if (key === 'font_family' && v === '') v = 'inherit';
			el.style.setProperty(VARS[key][0], v + (VARS[key][1] && v !== '' ? VARS[key][1] : ''));
			return;
		}
		switch (key) {
			case 'btn_uppercase': el.style.setProperty('--kk-btn-transform', val ? 'uppercase' : 'none'); break;
			case 'card_style': $preview.find('.kk-pcard').removeClass('kk-pcard--bordered kk-pcard--shadow kk-pcard--flat').addClass('kk-pcard--' + val); break;
			case 'card_align': $preview.find('.kk-pcard').toggleClass('kk-pcard--align-center', val === 'center'); el.style.setProperty('--kk-card-align', val); break;
			case 'card_show_brand': $preview.toggleClass('kk-hide-brand', !val); break;
			case 'card_show_rating': $preview.toggleClass('kk-hide-rating', !val); break;
			case 'card_show_badge': $preview.toggleClass('kk-hide-badge', !val); break;
			case 'card_badge_text': $preview.find('.kk-pcard__badge').text(String(val || 'Sale').replace('{percent}', '12').replace('{amount}', '200' + '\u09F3')); break;
			case 'card_cart_text': $preview.find('.kk-pcard__btn--cart span').text(val); break;
			case 'buynow_text': $preview.find('.kk-pcard__btn--buy span').text(val); break;
			case 'card_actions_layout':
				$preview.removeClass('kk-hide-buttons kk-only-buy kk-only-cart kk-stack')
					.addClass({ none: 'kk-hide-buttons', buynow_only: 'kk-only-buy', cart_only: 'kk-only-cart', stack: 'kk-stack' }[val] || '');
				break;
		}
	}
	/*
	 * "Unsaved changes": compare the form with how it looked when the page opened, instead of counting change events
	 * (colour pickers, sliders and the bottom bar editor fire events by themselves while starting up). Colours are
	 * compared without case, so "#FFFFFF" and "#ffffff" count as the same.
	 */
	function snapshot() {
		if (!$form.length) return '';
		return $form.find('input[name], select[name], textarea[name]').not('[type=submit], [type=button], [name=_wpnonce], [name=_wp_http_referer]').map(function () {
			var v;
			if (this.type === 'checkbox' || this.type === 'radio') v = this.checked ? '1' : '0';
			else v = String(this.value == null ? '' : this.value).trim();
			if ($(this).hasClass('kk-color')) v = v.toLowerCase();
			return this.name + '=' + v;
		}).get().join('&');
	}
	var initial = null;
	function isDirty() { return initial !== null && snapshot() !== initial; }
	function markDirty() {
		if (initial === null) return;
		setTimeout(function () { $dirty.prop('hidden', !isDirty()); }, 0);
	}
	// Take the starting picture after every widget on the page has finished setting itself up.
	function takeSnapshot() { setTimeout(function () { initial = snapshot(); }, 50); }
	if (document.readyState === 'complete') takeSnapshot(); else $(window).on('load', takeSnapshot);

	/* Colour pickers */
	$('.kk-color').each(function () {
		var input = this;
		$(input).wpColorPicker({
			change: function (e, ui) { updatePreview(keyOf(input), ui.color.toString()); markDirty(); },
			clear: function () { updatePreview(keyOf(input), $(input).data('default-color')); markDirty(); }
		});
	});

	/* Range <-> number */
	$('.kk-range').each(function () {
		var $range = $(this).find('input[type=range]');
		var $num = $(this).find('input[type=number]');
		$range.on('input', function () { $num.val(this.value).trigger('input'); });
		$num.on('input change', function () { $range.val(this.value); });
	});

	/* Switch label text */
	$form.on('change', '.kk-switch input[type=checkbox]', function () {
		$(this).siblings('.kk-switch__text').text(this.checked ? A.on : A.off);
	});

	/* show_if rules */
	function applyRules() {
		$form.find('[data-show-if]').each(function () {
			var rule = String($(this).data('show-if'));
			var eq = rule.indexOf('=');
			var on = eq > 0 ? String(valueOf(rule.slice(0, eq))) === rule.slice(eq + 1) : !!Number(valueOf(rule)); // "key" = switch is on, "key=value" = choice equals value
			$(this).prop('hidden', !on);
		});
	}
	applyRules();

	$form.on('input change', 'input, select, textarea', function () {
		var key = keyOf(this);
		if (!key) return;
		updatePreview(key, valueOf(key));
		applyRules();
		markDirty();
	});
	$form.on('submit', function () { $dirty.prop('hidden', true); $(window).off('beforeunload.kk'); });
	$(window).on('beforeunload.kk', function () { if (isDirty()) return true; });

	// Initial preview state from saved values.
	if ($preview.length) {
		['card_style', 'card_align', 'card_show_brand', 'card_show_rating', 'card_show_badge', 'card_badge_text', 'card_actions_layout'].forEach(function (k) {
			var v = valueOf(k);
			if (v !== null) updatePreview(k, v);
		});
	}

	/* Dashboard switches (AJAX) */
	var $status = $('.kk-module-status');
	var queue = $.Deferred().resolve().promise(); // one save at a time, so quick clicks never overwrite each other
	$('.kk-switch--ajax input').on('change', function () {
		var input = this;
		var $module = $(input).closest('.kk-module');
		var on = input.checked ? 1 : 0;
		$status.removeClass('is-error').text('…');
		queue = queue.then(function () {
			return $.post(A.ajax, { action: 'kk_toggle', nonce: A.nonce, key: $(input).data('module'), on: on }).then(null, function () { return $.Deferred().resolve(null).promise(); });
		});
		queue.done(function (res) {
				if (res && res.success) {
					$module.toggleClass('is-on', input.checked);
					$status.text('✓ ' + $module.find('.kk-module__text a').text() + ': ' + (input.checked ? A.on : A.off) + ' (' + A.saved + ')');
				} else {
					input.checked = !input.checked;
					$status.addClass('is-error').text((res && res.data && res.data.message) || A.error);
				}
			})
			.fail(function () { input.checked = !input.checked; $status.addClass('is-error').text(A.error); });
	});

	/* Plugin language */
	$('.kk-lang').on('change', function () {
		$.post(A.ajax, { action: 'kk_set_lang', nonce: A.nonce, lang: this.value }).always(function () { window.location.reload(); });
	});

	/* Copy buttons */
	$(document).on('click', '.kk-copy', function () {
		var $b = $(this), text = $b.data('copy');
		var done = function () { $b.attr('data-copied', A.copied).addClass('is-copied'); setTimeout(function () { $b.removeClass('is-copied'); }, 1600); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var t = document.createElement('textarea');
			t.value = text; document.body.appendChild(t); t.select();
			try { document.execCommand('copy'); done(); } catch (e) {}
			document.body.removeChild(t);
		}
	});

	/* ------------------------------------------------------------------
	 * Bottom Bar: items editor + phone preview
	 * ---------------------------------------------------------------- */
	var $items = $('.kk-bbar-items');
	var $phone = $('.kk-phone');
	if ($items.length) {
		var $json = $('.kk-bbar-json');
		var esc = function (s) { return $('<div>').text(s == null ? '' : s).html(); };
		var collect = function () {
			return $items.children('.kk-bbar-item').map(function () {
				var $r = $(this);
				return {
					id: $r.data('id'),
					on: $r.find('.kk-bbi-on').is(':checked') ? 1 : 0,
					label: $r.find('.kk-bbi-label').val(),
					icon: $r.find('.kk-bbi-icon').val(),
					link: $r.find('.kk-bbi-link').val(),
					url: $r.find('.kk-bbi-url').val()
				};
			}).get();
		};
		var renderPhone = function () {
			if (!$phone.length) return;
			var list = collect().filter(function (it) { return it.on; });
			var featured = valueOf('bbar_featured') || '';
			var style = valueOf('bbar_style') || 'pill';
			var labels = Number(valueOf('bbar_labels')) === 1;
			var html = list.map(function (it, i) {
				var cls = 'kk-bbar__item' + (i === 0 ? ' is-active' : '') + (featured === it.id ? ' kk-bbar__item--featured' : '');
				var badge = it.link === 'cart' ? '<span class="kk-bbar__count">2</span>' : '';
				return '<a class="' + cls + '" href="#" tabindex="-1"><span class="kk-bbar__icon">' + (A.icons[it.icon] || '') + badge + '</span>' + (labels ? '<span class="kk-bbar__label">' + esc(it.label) + '</span>' : '') + '</a>';
			}).join('');
			$phone.find('.kk-phone__bar').html(list.length ? '<nav class="kk-bbar kk-bbar--' + style + (labels ? '' : ' kk-bbar--no-labels') + '">' + html + '</nav>' : '');
		};
		var BBVARS = {
			bbar_height: ['--kk-bbar-h', 'px'], bbar_icon_size: ['--kk-bbar-icon', 'px'], bbar_label_size: ['--kk-bbar-label', 'px'],
			bbar_bg: ['--kk-bbar-bg'], bbar_border: ['--kk-bbar-border'], bbar_icon_color: ['--kk-bbar-icon-c'], bbar_text_color: ['--kk-bbar-text-c'],
			bbar_active_color: ['--kk-bbar-active'], bbar_active_bg: ['--kk-bbar-act-bg'], bbar_badge_bg: ['--kk-bbar-badge']
		};
		var SHADOWS = { none: 'none', soft: '0 -6px 22px -14px rgba(16,23,20,.35)', strong: '0 -10px 30px -10px rgba(16,23,20,.45)' };
		var applyVars = function () {
			if (!$phone.length) return;
			Object.keys(BBVARS).forEach(function (k) {
				var v = valueOf(k);
				if (v !== null && v !== '') $phone[0].style.setProperty(BBVARS[k][0], v + (BBVARS[k][1] || ''));
			});
			$phone[0].style.setProperty('--kk-bbar-shadow', SHADOWS[valueOf('bbar_shadow')] || SHADOWS.soft);
		};
		var sync = function () {
			var list = collect();
			$json.val(JSON.stringify(list));
			var on = list.filter(function (it) { return it.on; }).length;
			$('.kk-bbar-warn').text(A.tooMany).prop('hidden', on <= 5);
			$items.children('.kk-bbar-item').each(function () {
				var $r = $(this);
				$r.toggleClass('is-on', $r.find('.kk-bbi-on').is(':checked'));
				$r.find('.kk-bbar-item__icon').html(A.icons[$r.find('.kk-bbi-icon').val()] || '');
				$r.find('.kk-bbi-url-wrap').prop('hidden', $r.find('.kk-bbi-link').val() !== 'custom');
			});
			renderPhone();
			markDirty();
		};
		$items.sortable({ handle: '.kk-bbar-item__handle', axis: 'y', update: sync });
		$items.on('change input', 'input, select', sync);
		$items.on('click', '.kk-bbi-up, .kk-bbi-down', function () {
			var $r = $(this).closest('.kk-bbar-item');
			if ($(this).hasClass('kk-bbi-up')) $r.prev().before($r); else $r.next().after($r);
			$(this).trigger('focus');
			sync();
		});
		$('.kk-bbar-reset').on('click', function () {
			var defs = JSON.parse($('.kk-bbar-defaults').text() || '[]');
			defs.forEach(function (d) {
				var $r = $items.children('[data-id="' + d.id + '"]');
				$r.find('.kk-bbi-on').prop('checked', !!d.on);
				$r.find('.kk-bbi-label').val(d.label);
				$r.find('.kk-bbi-icon').val(d.icon);
				$r.find('.kk-bbi-link').val(d.link);
				$r.find('.kk-bbi-url').val(d.url);
				$items.append($r);
			});
			sync();
		});
		$form.on('input change', 'input, select', function () {
			var k = keyOf(this);
			if (k.indexOf('bbar_') === 0) { applyVars(); renderPhone(); }
		});
		$('.kk-color').each(function () {
			var input = this;
			if (keyOf(input).indexOf('bbar_') !== 0) return;
			$(input).wpColorPicker('option', 'change', function (e, ui) { $(input).val(ui.color.toString()); applyVars(); markDirty(); });
		});
		applyVars();
		renderPhone();
	}

	/* Confirm resets */
	$('.kk-reset').on('submit', function () { return window.confirm($(this).data('confirm')); });
});

/* Courier booking: "Test connection" (asks the courier for the balance; books nothing). */
document.addEventListener('click', function (e) {
	var btn = e.target.closest ? e.target.closest('[data-kk-courier-test]') : null;
	if (!btn) { return; }
	e.preventDefault();
	var out = btn.parentNode.querySelector('.kk-test-result');
	btn.disabled = true;
	out.className = 'kk-test-result';
	out.textContent = '…';
	fetch(btn.getAttribute('data-kk-courier-test'), { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': btn.getAttribute('data-nonce') } })
		.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
		.then(function (res) {
			out.className = 'kk-test-result ' + (res.ok ? 'is-ok' : 'is-bad');
			out.textContent = (res.d && res.d.message) || '';
		})
		.catch(function () { out.className = 'kk-test-result is-bad'; out.textContent = 'Network error'; })
		.then(function () { btn.disabled = false; });
});
