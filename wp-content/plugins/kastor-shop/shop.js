/**
 * Client-side filtering + sidebar layout for WooCommerce product grids.
 *
 * Pulls together three filter sources:
 *   - Text search        ([data-kastor-shop-filter-input])
 *   - Price range        ([data-kastor-shop-price-min], [data-kastor-shop-price-max])
 *   - Type checkboxes    ([data-kastor-shop-type])
 *
 * Each product card carries hidden meta ([data-kastor-shop-product-meta]
 * with data-price and data-types) injected by the PHP side.
 *
 * Also performs a one-time DOM rewrap: the sidebar rendered before the
 * products grid is moved into a `.kastor-shop__layout` flex container so
 * CSS can place it on the left and the grid on the right. The grid + the
 * surrounding ordering/result-count/pagination elements are pulled in by
 * explicit selectors so themes that wrap WooCommerce output in their own
 * containers (like Blocksy) still get the right layout.
 */
(function () {
	'use strict';

	// Flip to true to see cache + filter diagnostics in DevTools console.
	var DEBUG = true;

	var products = [];
	var emptyMsg = null;

	function log() {
		if (!DEBUG || !window.console) return;
		try { console.log.apply(console, ['[kastor-shop]'].concat([].slice.call(arguments))); }
		catch (e) { /* noop */ }
	}

	function normalize(s) {
		return (s || '')
			.toString()
			.normalize('NFD')
			.replace(/[̀-ͯ]/g, '')
			.toLowerCase()
			.trim();
	}


	/* -------- DOM rewrap: sidebar + products → flex layout -------- */

	function buildSidebarLayout() {
		var sidebar = document.querySelector('[data-kastor-shop-sidebar]');
		if (!sidebar) {
			log('no sidebar element');
			return;
		}
		if (sidebar.closest('.kastor-shop__layout')) {
			log('layout already built');
			return;
		}

		var grid = document.querySelector(
			'.woocommerce ul.products, ul.products, ul[class*="products"]'
		);
		if (!grid) {
			log('no products grid found — aborting layout');
			return;
		}

		// Build the two-column layout in place of the sidebar.
		var layout = document.createElement('div');
		layout.className = 'kastor-shop__layout';
		var main = document.createElement('div');
		main.className = 'kastor-shop__main';

		// Explicitly grab each main-column element by selector. More robust
		// than walking sibling order, which breaks when the theme wraps WC
		// output in its own container.
		var maybeAppend = function (el) {
			if (el && el !== sidebar) main.appendChild(el);
		};

		maybeAppend(document.querySelector('.woocommerce-notices-wrapper'));
		maybeAppend(document.querySelector('[data-kastor-shop-filter]'));
		maybeAppend(document.querySelector('.woocommerce-result-count'));
		maybeAppend(document.querySelector('.woocommerce-ordering'));
		maybeAppend(grid);
		maybeAppend(document.querySelector('.woocommerce-pagination, nav.woocommerce-pagination'));

		sidebar.parentNode.insertBefore(layout, sidebar);
		layout.appendChild(sidebar);
		layout.appendChild(main);

		log('layout built');
	}


	/* -------- Product cache -------- */

	function cacheProducts() {
		var nodes = document.querySelectorAll(
			'.woocommerce ul.products li.product, ' +
			'ul.products li.product, ' +
			'ul.products > li, ' +
			'ul[class*="products"] > li'
		);

		products = Array.prototype.map.call(nodes, function (li) {
			var titleEl = li.querySelector(
				'.woocommerce-loop-product__title, h2, h3, .product-title, .wd-entities-title'
			);
			var meta = li.querySelector('[data-kastor-shop-product-meta]');
			var priceRaw = meta ? meta.getAttribute('data-price') : '';
			var typesRaw = meta ? meta.getAttribute('data-types') : '';
			return {
				el: li,
				title: normalize(titleEl ? titleEl.textContent : li.textContent),
				price: priceRaw !== '' ? parseFloat(priceRaw) : NaN,
				types: typesRaw ? typesRaw.split(',').filter(Boolean) : [],
				hasMeta: !!meta
			};
		});

		log('cached', products.length, 'products',
			products.length ? '— sample: ' + JSON.stringify({
				title: products[0].title,
				price: products[0].price,
				types: products[0].types,
				hasMeta: products[0].hasMeta
			}) : '');
	}


	/* -------- Filter application -------- */

	function applyFilters() {
		var searchInput = document.querySelector('[data-kastor-shop-filter-input]');
		var q = searchInput ? normalize(searchInput.value) : '';

		var minInput = document.querySelector('[data-kastor-shop-price-min]');
		var maxInput = document.querySelector('[data-kastor-shop-price-max]');
		var min = minInput && minInput.value !== '' ? parseFloat(minInput.value) : -Infinity;
		var max = maxInput && maxInput.value !== '' ? parseFloat(maxInput.value) : Infinity;
		if (isNaN(min)) min = -Infinity;
		if (isNaN(max)) max = Infinity;

		var selectedTypes = Array.prototype.map.call(
			document.querySelectorAll('[data-kastor-shop-type]:checked'),
			function (c) { return c.value; }
		);

		var visible = 0;

		products.forEach(function (p) {
			var textMatch = q === '' || p.title.indexOf(q) !== -1;

			var priceMatch = isNaN(p.price) ||
				(p.price >= min && p.price <= max);

			var typeMatch = selectedTypes.length === 0 ||
				selectedTypes.some(function (s) {
					return p.types.indexOf(s) !== -1;
				});

			var match = textMatch && priceMatch && typeMatch;
			p.el.style.display = match ? '' : 'none';
			if (match) visible++;
		});

		if (emptyMsg) {
			emptyMsg.hidden = visible !== 0;
		}

		log('applied — q="' + q + '" min=' + min + ' max=' + max +
			' types=[' + selectedTypes.join(',') + '] visible=' + visible);
	}


	/* -------- Boot -------- */

	function bindEvents() {
		var inputs = document.querySelectorAll(
			'[data-kastor-shop-filter-input], ' +
			'[data-kastor-shop-price-min], [data-kastor-shop-price-max], ' +
			'[data-kastor-shop-type]'
		);
		log('binding events on', inputs.length, 'inputs');
		inputs.forEach(function (el) {
			el.addEventListener('input', applyFilters);
			el.addEventListener('change', applyFilters);
			el.addEventListener('search', applyFilters);
		});

		var reset = document.querySelector('[data-kastor-shop-filter-reset]');
		if (reset) {
			reset.addEventListener('click', function () {
				var clears = document.querySelectorAll(
					'[data-kastor-shop-filter-input], ' +
					'[data-kastor-shop-price-min], ' +
					'[data-kastor-shop-price-max]'
				);
				clears.forEach(function (el) { el.value = ''; });
				document.querySelectorAll('[data-kastor-shop-type]:checked').forEach(function (el) {
					el.checked = false;
				});
				applyFilters();
			});
		}
	}

	/* -------- Sale-badge percentage rewrite --------
	 * Some themes (Blocksy) bypass WooCommerce's `woocommerce_sale_flash`
	 * filter by overriding the loop/sale-flash.php template, so a PHP-side
	 * filter never fires. We pick the badge up client-side and replace its
	 * text based on whatever <del>/<ins> prices are rendered on the card.
	 */

	function parsePrice(el) {
		if (!el) return NaN;
		// "1 230,50 €" → "1230.50"
		var raw = (el.textContent || '')
			.replace(/[\s ]/g, '')        // strip spaces, NBSPs
			.replace(/[^\d.,-]/g, '')          // drop currency symbols
			.replace(/\.(?=\d{3}(\D|$))/g, '') // strip thousands dots
			.replace(',', '.');
		var n = parseFloat(raw);
		return isFinite(n) ? n : NaN;
	}

	function rewriteSaleBadges() {
		var cards = document.querySelectorAll(
			'.woocommerce ul.products li.product, ul.products li.product, ul.products > li'
		);
		cards.forEach(function (card) {
			var badge = card.querySelector('.onsale, .ct-product-badge, [class*="sale"]');
			if (!badge) return;

			var priceWrap = card.querySelector('.price');
			if (!priceWrap) return;

			var del = priceWrap.querySelector('del');
			var ins = priceWrap.querySelector('ins');
			if (!del || !ins) return;

			var regular = parsePrice(del);
			var sale    = parsePrice(ins);
			if (!isFinite(regular) || !isFinite(sale) || regular <= 0 || sale >= regular) {
				return;
			}

			var pct = Math.round(((regular - sale) / regular) * 100);
			if (pct > 0) {
				badge.textContent = '-' + pct + '%';
				badge.classList.add('kastor-shop-sale-rewritten');
			}
		});
	}


	/* -------- Wrap "Add to cart" + TI Wishlist heart in one flex row -------- */

	function wrapCartAndWishlist() {
		var cards = document.querySelectorAll(
			'.woocommerce ul.products li.product, ul.products li.product, ul.products > li'
		);
		cards.forEach(function (card) {
			var cartBtn = card.querySelector(
				'a.button.add_to_cart_button, ' +
				'a.button.product_type_simple, ' +
				'.button.add_to_cart_button'
			);
			var wishlist = card.querySelector('.tinv-wishlist, .tinvwl-wishlist-loop, [class*="tinvwl"]');

			if (!cartBtn || !wishlist) return;
			if (cartBtn.parentElement && cartBtn.parentElement.classList.contains('kastor-shop__card-actions')) return;

			var actions = document.createElement('div');
			actions.className = 'kastor-shop__card-actions';
			cartBtn.parentNode.insertBefore(actions, cartBtn);
			actions.appendChild(cartBtn);
			actions.appendChild(wishlist);
		});
	}


	/* -------- Close PhotoSwipe lightbox on scroll / wheel, with fade -------- */

	function setupLightboxScrollClose() {
		var fading = false;

		function fadeAndClose() {
			if (fading) return;
			var pswp = document.querySelector('.pswp.pswp--open');
			if (!pswp) return;

			fading = true;
			pswp.style.transition = 'opacity 0.3s ease';
			pswp.style.opacity = '0';

			setTimeout(function () {
				var closeBtn = pswp.querySelector('.pswp__button--close');
				if (closeBtn) closeBtn.click();
				// Clean inline styles so the next open starts at opacity 1.
				setTimeout(function () {
					pswp.style.opacity = '';
					pswp.style.transition = '';
					fading = false;
				}, 50);
			}, 300);
		}

		// Mouse wheel → fade + close.
		document.addEventListener('wheel', fadeAndClose, { passive: true });

		// Scroll keys → fade + close too.
		document.addEventListener('keydown', function (e) {
			if (e.key === 'PageDown' || e.key === 'PageUp' ||
				e.key === ' ' || e.key === 'Home' || e.key === 'End') {
				fadeAndClose();
			}
		});

		// Also make the X button fade rather than vanish abruptly.
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.pswp__button--close');
			if (!btn) return;
			var pswp = btn.closest('.pswp.pswp--open');
			if (!pswp) return;
			if (fading) return; // already fading
			e.preventDefault();
			e.stopImmediatePropagation();
			fadeAndClose();
		}, true);
	}


	/* -------- Append BGN equivalent next to every EUR price in the cart /
	 * checkout block. Server-side filter handles product/category prices,
	 * but Block-rendered totals/subtotals are JS-only so we patch here.    */

	var BGN_RATE = 1.95583;

	function formatBgn(amount) {
		// Match WC's display format: 2 decimals, comma as decimal separator.
		return amount.toFixed(2).replace('.', ',');
	}

	function appendBgnToPrices(root) {
		root = root || document.body;
		var lang = (document.documentElement.lang || 'bg').toLowerCase();
		var suffix = lang.indexOf('bg') === 0 ? 'лв' : 'BGN';

		// Cast a wide net — WC Cart/Checkout Block uses several different class
		// names depending on version. Match anything that looks like a money element.
		var rawPrices = root.querySelectorAll(
			'.woocommerce-Price-amount.amount, ' +
			'.wc-block-formatted-money-amount, ' +
			'.wc-block-components-formatted-money-amount, ' +
			'.wc-block-components-totals-item__value, ' +
			'.wc-block-components-product-price__value, ' +
			'[class*="formatted-money-amount"], ' +
			'[class*="totals-item__value"]'
		);

		// Keep only the innermost matches — when both a wrapper and its child
		// match (e.g. .__value containing .formatted-money-amount), processing
		// both would append two BGN blocks for the same price.
		var rawArr = Array.prototype.slice.call(rawPrices);
		var prices = rawArr.filter(function (el) {
			for (var i = 0; i < rawArr.length; i++) {
				if (rawArr[i] !== el && el.contains(rawArr[i])) return false;
			}
			return true;
		});

		prices.forEach(function (el) {
			var raw = el.textContent || '';

			// Skip if not EUR.
			if (raw.indexOf('€') === -1 && raw.indexOf('EUR') === -1) return;
			// Skip if we're inside an already-rendered BGN span.
			if (el.closest('.kastor-shop__price-bgn')) return;

			// If a PHP-rendered (non-JS) BGN sibling exists, leave it alone —
			// PHP filter is the authoritative source on product/single pages.
			var next = el.nextElementSibling;
			if (next && next.classList &&
				next.classList.contains('kastor-shop__price-bgn') &&
				!next.classList.contains('kastor-shop__price-bgn-js')) return;

			// If we already rendered BGN for THIS exact text on THIS element,
			// skip — prevents an infinite re-render loop from the observer.
			if (el.dataset.kastorBgnText === raw) return;

			// Stale BGN sibling from a previous render?  Remove before
			// re-appending (Cart Block changes price text in place after a qty
			// update, so the previously-added BGN sibling is now wrong).
			if (next && next.classList && next.classList.contains('kastor-shop__price-bgn-js')) {
				next.remove();
			}

			// Strip currency, thousands separators, normalize decimal point.
			var cleaned = raw
				.replace(/[\s ]/g, '')
				.replace(/[^\d.,-]/g, '')
				.replace(/\.(?=\d{3}(\D|$))/g, '')
				.replace(',', '.');
			var amount = parseFloat(cleaned);
			if (!isFinite(amount) || amount <= 0) return;

			var bgn = amount * BGN_RATE;
			var span = document.createElement('span');
			span.className = 'kastor-shop__price-bgn kastor-shop__price-bgn-js';
			span.innerHTML =
				' (<span class="kastor-shop__price-bgn-amount">' + formatBgn(bgn) +
				'</span> <span class="kastor-shop__price-bgn-suffix">' + suffix + '</span>)';

			el.dataset.kastorBgnText = raw;
			el.parentNode.insertBefore(span, el.nextSibling);
		});
	}

	function setupBgnObserver() {
		appendBgnToPrices();

		// Observe body so we catch the Cart/Checkout Block whenever it renders,
		// even if the block root mounts later than DOMContentLoaded.
		var debounceTimer;
		var obs = new MutationObserver(function () {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				appendBgnToPrices();
			}, 150);
		});
		obs.observe(document.body, { childList: true, subtree: true, characterData: true });
	}


	/* -------- Cart Block quantity +/- throttle.
	 * Cart Block fires an AJAX request on every +/- click.  When the user
	 * mashes the button, responses can return out of order — the older
	 * response (e.g. "qty=2") arrives AFTER the newer one ("qty=4"), and
	 * the UI reverts to the stale value.  Fix: block subsequent clicks
	 * until the current request resolves (detected by the qty input value
	 * actually changing), with a hard 1.5s timeout as fallback.        */

	function throttleQuantityButtons() {
		var buttons = document.querySelectorAll(
			'.wc-block-components-quantity-selector__button, ' +
			'[class*="quantity-selector__button"]'
		);

		buttons.forEach(function (btn) {
			if (btn.dataset.kastorThrottled === '1') return;
			btn.dataset.kastorThrottled = '1';

			// Locate the sibling input so we can watch for value changes.
			var selector = btn.closest('[class*="quantity-selector"]') || btn.parentNode;
			var input = selector ? selector.querySelector('input') : null;

			btn.addEventListener('click', function (e) {
				if (btn.dataset.kastorLocked === '1') {
					e.stopPropagation();
					e.stopImmediatePropagation();
					e.preventDefault();
					return false;
				}
				btn.dataset.kastorLocked = '1';
				btn.style.opacity = '0.5';

				var unlocked = false;
				var unlock = function () {
					if (unlocked) return;
					unlocked = true;
					btn.dataset.kastorLocked = '0';
					btn.style.opacity = '';
				};

				// Unlock when the input value actually changes (Cart Block
				// has finished its optimistic + reconciled update).
				if (input) {
					var startVal = input.value;
					var poll = setInterval(function () {
						if (input.value !== startVal) {
							clearInterval(poll);
							unlock();
						}
					}, 50);
					setTimeout(function () { clearInterval(poll); unlock(); }, 3000);
				} else {
					// No input found — fall back to a flat 800ms cooldown.
					setTimeout(unlock, 2200);
				}
			}, true); // capture phase so we run before Cart Block's handler
		});
	}

	function setupQuantityThrottle() {
		throttleQuantityButtons();

		// Cart Block re-renders rows after every change — re-bind throttle.
		var debounceTimer;
		var obs = new MutationObserver(function () {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(throttleQuantityButtons, 150);
		});
		obs.observe(document.body, { childList: true, subtree: true });
	}


	/* -------- Translate the few English strings WooCommerce Cart/Checkout
	 * Block leaves untranslated (they come from JS bundles, not gettext). */

	function translateCartBlockStrings() {
		if (!document.body.classList.contains('woocommerce-cart') &&
			!document.body.classList.contains('woocommerce-checkout') &&
			!document.body.classList.contains('woocommerce-page') &&
			!document.body.classList.contains('woocommerce-account') &&
			!document.querySelector('.ct-woo-authorized') &&
			!document.querySelector('.ct-woo-unauthorized')) return;

		var map = {
			'Add coupons':          'Добави купон',
			'Add a coupon':         'Добави купон',
			'Enter code':           'Въведи код',
			'Apply':                'Приложи',
			'Free shipping':        'Безплатна доставка',
			'Estimated total':      'Очаквана сума',
			'Proceed to Checkout':  'Към плащане',
			'Proceed to checkout':  'Към плащане',
			'Subtotal':             'Междинна сума',
			'Total':                'Общо',
			'Shipping':             'Доставка',
			'Discount':             'Отстъпка',
			'Coupon code':          'Код за отстъпка',
			'Remove item':          'Премахни',
			'Cart totals':          'Обща сума на количката',
			'Use same address for billing': 'Използвай същия адрес за фактуриране',
			'Payment options':      'Опции за плащане',
			'Payment methods':      'Методи на плащане',
			'Place Order':          'Завърши поръчката',
			'Card':                 'Карта',
			'Your cart is currently empty!': 'Количката ви е празна!',
			'Your cart is currently empty.': 'Количката ви е празна.',
			'Your cart is currently empty':  'Количката ви е празна',
			'New in store':         'Части от които може би имате нужда',
			'Browse store':         'Към магазина',
			'Return to shop':       'Към магазина'
		};

		// Partial-match replacements: regex applied to every text node before
		// exact-match map runs.  Use for strings that interpolate a value
		// (e.g. "Запазване на 10,00 €" — keeps the amount, swaps the label).
		var partials = [
			[/Запазване на/g, 'Спестяваш'],
			// WC accessibility live-region announcement when qty changes.
			[/The quantity of "([^"]+)" was changed to (\d+)\.?/g,
				'Количеството на „$1" беше променено на $2.'],
			// Same string without quotes (some WC versions render it differently).
			[/The quantity of (\S+) was changed to (\d+)\.?/g,
				'Количеството на $1 беше променено на $2.'],
			// Temporary-password notice after registration ("Your account
			// with <domain> is using a temporary password...").
			[/Your account with ([^\s]+) is using a temporary password\. We emailed you a link to change your password\.?/g,
				'Вашият акаунт в $1 използва временна парола. Изпратихме ви имейл с линк за нейната смяна.']
		];

		function walk(node) {
			if (!node) return;
			if (node.nodeType === Node.TEXT_NODE) {
				var t = node.nodeValue;
				if (!t) return;
				for (var p = 0; p < partials.length; p++) {
					if (partials[p][0].test(t)) {
						node.nodeValue = t.replace(partials[p][0], partials[p][1]);
						t = node.nodeValue;
					}
				}
				var trimmed = t.trim();
				if (map.hasOwnProperty(trimmed)) {
					node.nodeValue = t.replace(trimmed, map[trimmed]);
				}
				return;
			}
			if (node.nodeType !== Node.ELEMENT_NODE) return;
			// Avoid descending into <script>, <style>, <textarea>, <input>.
			var tag = node.tagName;
			if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA' || tag === 'INPUT') return;
			// Also translate the input's placeholder attribute if it matches.
			if (tag === 'BUTTON' || tag === 'A') {
				var aria = node.getAttribute('aria-label');
				if (aria && map[aria]) node.setAttribute('aria-label', map[aria]);
			}
			for (var i = 0; i < node.childNodes.length; i++) {
				walk(node.childNodes[i]);
			}
		}

		// Initial pass.
		walk(document.body);

		// Cart/Checkout Blocks re-render on every state change.  Watch the
		// block roots and re-translate on each mutation.
		var roots = document.querySelectorAll(
			'.wp-block-woocommerce-cart, ' +
			'.wp-block-woocommerce-checkout, ' +
			'.wc-block-cart, ' +
			'.wc-block-checkout, ' +
			'.wc-block-components-totals-wrapper'
		);

		if (!roots.length) return;
		var obs = new MutationObserver(function (mutations) {
			mutations.forEach(function (m) {
				if (m.type === 'childList') {
					m.addedNodes.forEach(walk);
				}
				if (m.type === 'characterData') {
					walk(m.target);
				}
			});
		});
		roots.forEach(function (r) {
			obs.observe(r, { childList: true, subtree: true, characterData: true });
		});
	}


	/* -------- Move wishlist into the cart-actions grid on single product -------- */

	function moveWishlistIntoCartActions() {
		if (!document.body.classList.contains('single-product')) return;

		var actions = document.querySelector('.ct-cart-actions');
		if (!actions) return;

		var wishlist = document.querySelector(
			'.summary .tinv-wishlist, .summary .tinvwl-wishlist-loop, .summary [class*="tinvwl"]'
		);
		if (!wishlist) return;
		if (actions.contains(wishlist)) return;

		actions.appendChild(wishlist);
	}


	/* -------- "Купи сега" — add to cart + redirect to checkout -------- */

	function setupBuyNowButton() {
		document.querySelectorAll('[data-kastor-shop-buy-now]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var productId = btn.getAttribute('data-product-id');
				if (!productId) return;

				var form = btn.closest('form.cart');
				var qtyInput = form ? form.querySelector('input.qty, input[name="quantity"]') : null;
				var qty = qtyInput && qtyInput.value ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1;

				// Wait for any in-flight WC scripts, then navigate.
				var url = '/checkout/?add-to-cart=' + encodeURIComponent(productId) +
					'&quantity=' + encodeURIComponent(qty);
				window.location.href = url;
			});
		});
	}


	/* -------- Inject a simple "Начало" link below the archive title -------- */

	function injectBackToHomeLink() {
		// Only on WC archive pages.
		var b = document.body;
		if (!b.classList.contains('woocommerce') && !b.classList.contains('archive')) return;
		if (b.classList.contains('single-product')) return;

		// Find the main banner/page-title element. Try the common Blocksy/WC
		// selectors in order of specificity.
		var title =
			document.querySelector('.ct-page-title-wrapper h1') ||
			document.querySelector('.ct-banner-title-wrapper h1') ||
			document.querySelector('.ct-hero-section h1') ||
			document.querySelector('.entry-header h1') ||
			document.querySelector('.woocommerce-products-header__title') ||
			document.querySelector('header.page-header h1') ||
			document.querySelector('h1.entry-title');

		if (!title) return;
		// Don't double-add.
		if (title.parentNode.querySelector('.kastor-shop__back-home')) return;

		var link = document.createElement('a');
		link.href = '/';
		link.className = 'kastor-shop__back-home';
		link.textContent = 'Начало';

		// Wrap the title + link in a small flex container so the link always
		// sits directly underneath the title with a controlled gap, regardless
		// of Blocksy's outer banner layout.
		var group = document.createElement('div');
		group.className = 'kastor-shop__title-group';
		title.parentNode.insertBefore(group, title);

		// On product-category pages, prepend "Части за" above the category
		// name so the title reads "Части за СЕМЕЧИСТАЧНА МАШИНА GIGANT K531".
		if (document.body.classList.contains('tax-product_cat')) {
			var prefix = document.createElement('span');
			prefix.className = 'kastor-shop__title-prefix';
			prefix.textContent = 'Части за';
			group.appendChild(prefix);
		}

		group.appendChild(title);
		group.appendChild(link);
	}


	/* -------- Add-to-cart AJAX handler: hide button + 3 feedback effects ---
	 * Fires when WC's "added_to_cart" jQuery event resolves. Triggers:
	 *  - "added ✓" green flash on the button row (CSS class .kastor-shop__flash)
	 *  - Cart icon bounce in the header (CSS class .kastor-shop__cart-bounce)
	 *  - Brief brand-blue ring around the product card (.kastor-shop__card-glow)
	 * All three are pure CSS animations triggered by class add → auto-remove. */

	function flashCartFeedback($button) {
		var btn = $button && $button.length ? $button[0] : null;
		if (!btn) return;

		// 2. Checkmark + green flash on the button's row.
		var row = btn.closest('.kastor-shop__card-actions');
		if (row) {
			row.classList.add('kastor-shop__has-added');
			// Add a temporary "just-added" class for the flash animation.
			var card = btn.closest('li.product, .product');
			if (card) {
				card.classList.add('kastor-shop__card-glow');
				setTimeout(function () {
					card.classList.remove('kastor-shop__card-glow');
				}, 700);
			}
			row.classList.add('kastor-shop__flash');
			setTimeout(function () {
				row.classList.remove('kastor-shop__flash');
			}, 900);
		}

		// 3. Cart icon bounce in the header. Try Blocksy's class first,
		//    then any generic "cart" icon link in the header.
		var cartTargets = document.querySelectorAll(
			'.ct-header-cart, ' +
			'header .ct-header-cart, ' +
			'header [class*="header-cart"], ' +
			'header a[href*="cart"]'
		);
		cartTargets.forEach(function (el) {
			el.classList.add('kastor-shop__cart-bounce');
			setTimeout(function () {
				el.classList.remove('kastor-shop__cart-bounce');
			}, 700);
		});
	}

	function setupAddedToCartHandler() {
		if (typeof window.jQuery === 'undefined') return;

		window.jQuery(document.body).on('added_to_cart',
			function (event, fragments, cart_hash, $button) {
				if ($button && $button.length) {
					// 2a. Hide the original cart button (so the green flash
					//     reveals the new "Преглед на количката" link).
					$button[0].style.setProperty('display', 'none', 'important');
					var row = $button[0].closest('.kastor-shop__card-actions');
					if (row) row.classList.add('kastor-shop__has-added');
				}
				// Fire the feedback regardless of button presence.
				flashCartFeedback($button);
			}
		);
	}


	/* -------- Restyle the footer "Продукти" + "Свържете се с нас" blocks ----
	 * The Gutenberg block editor wrapped each heading + list item in a
	 * <mark style="color:#XXX"> with an inline color, which beats external
	 * CSS even with !important in some cascades. The most reliable fix is
	 * to set the same property as a NEW inline style (with !important)
	 * directly on each element after page load. */

	// Shared state so equalizeFooterHeadings() uses the exact same heading
	// element that restyleFooterBlocks() identified (including heuristic
	// matches that aren't h1-h6 elements).
	var _footerHeadings = {};

	function restyleFooterBlocks() {
		var cols = document.querySelectorAll(
			'footer.ct-footer [data-column="widget-area-1"], ' +
			'footer.ct-footer [data-column="widget-area-3"]'
		);
		if (!cols.length) return;

		// Reset cached headings on every full run.
		_footerHeadings = {};

		// Helper: is this element an icon container? Icons use their own font
		// family (FontAwesome / Eicons / etc.) — overriding to Poppins makes
		// the glyph disappear because Poppins has no mapping for that codepoint.
		function isIconElement(el) {
			if (!el || !el.tagName) return false;
			var tag = el.tagName.toLowerCase();
			if (tag === 'svg' || tag === 'path' || tag === 'g' || tag === 'circle' ||
				tag === 'rect' || tag === 'use' || tag === 'symbol' || tag === 'defs') return true;
			if (tag === 'i') return true;
			var cls = (el.className && el.className.baseVal !== undefined)
				? el.className.baseVal // SVG-in-HTML returns SVGAnimatedString
				: (typeof el.className === 'string' ? el.className : '');
			if (/\b(fa-|fas|far|fab|fal|eicon|dashicons|icon-|kt-icon)/i.test(cls)) return true;
			// Inside any icon wrapper?
			if (el.closest('.elementor-icon, .elementor-icon-list-icon, .ct-icon-container, .kt-svg-icon-wrap')) return true;
			return false;
		}

		// IMPORTANT: don't touch the parent's align-items — doing so pushes
		// the logo column (widget-area-2) and the map column (widget-area-4)
		// to the top, which the user wants kept centered.
		// Each of our two target columns sets `align-self: start` instead,
		// which only affects them individually inside the grid track.

		cols.forEach(function (col) {
			// Pin only THIS column to the top of its grid cell so its
			// heading lines up with the other target column's heading.
			col.style.setProperty('align-self', 'start', 'important');
			col.style.setProperty('vertical-align', 'top', 'important');
			col.style.setProperty('padding-top', '24px', 'important');
			col.style.setProperty('padding-bottom', '24px', 'important');

			// Override Blocksy's ".ct-footer .ct-widget:not(:first-child) {
			// margin-top: var(--widgets-gap, 40px) }" rule — it adds a fat 40px
			// gap between every widget after the first, which pushes the
			// contact rows down. We swap it for a tight, even 14px gap that
			// matches the row spacing inside the lists. */
			col.querySelectorAll('.ct-widget, .widget_block, .widget').forEach(function (w, idx) {
				if (idx === 0) {
					w.style.setProperty('margin-top', '0', 'important');
					w.style.setProperty('padding-top', '0', 'important');
				} else {
					w.style.setProperty('margin-top', '14px', 'important');
					w.style.setProperty('padding-top', '0', 'important');
				}
			});

			// 1. Force EVERY element in the column to white + transparent bg.
			//    Set font-family Poppins ONLY on non-icon elements so icon
			//    glyphs keep rendering.
			col.querySelectorAll('*').forEach(function (el) {
				if (el.tagName === 'SCRIPT' || el.tagName === 'STYLE') return;
				var icon = isIconElement(el);
				el.style.setProperty('color', '#ffffff', 'important');
				el.style.setProperty('background-color', 'transparent', 'important');
				el.style.setProperty('background', 'transparent', 'important');

				if (icon) {
					el.style.setProperty('fill', '#ffffff', 'important');
				} else {
					el.style.setProperty('font-family', "'Poppins', sans-serif", 'important');
					el.style.setProperty('text-decoration', 'none', 'important');
				}
			});

			// 2. Headings — broad selector list.
			var headingSelectors =
				'h1, h2, h3, h4, h5, h6, ' +
				'.wp-block-heading, ' +
				'.elementor-heading-title, ' +
				'.widget-title, .widgettitle, ' +
				'.elementor-widget-heading .elementor-widget-container > *';

			var seen = [];
			col.querySelectorAll(headingSelectors).forEach(function (h) { seen.push(h); });

			// 2b. Heuristic — if a .ct-widget contains short text without any
			//    of the heading selectors above (e.g. a custom HTML block),
			//    treat its first text-bearing direct child as the heading.
			col.querySelectorAll('.ct-widget, .widget_block, .widget').forEach(function (widget) {
				if (widget.querySelector(headingSelectors)) return; // already has one
				var first = widget.firstElementChild;
				if (!first) return;
				var txt = (first.textContent || '').trim();
				// Short text, single line, no list / icon structure inside.
				if (txt.length > 0 && txt.length < 80 &&
					!first.querySelector('ul, ol, li, .elementor-icon-list-items')) {
					seen.push(first);
				}
			});

			// Cache the first detected heading per column for the equalizer.
			if (seen.length) {
				var key = col.getAttribute('data-column'); // "widget-area-1" or "-3"
				_footerHeadings[key] = seen[0];
			}

			seen.forEach(function (h) {
				h.style.setProperty('font-size', '1.15rem', 'important');
				h.style.setProperty('font-weight', '800', 'important');
				h.style.setProperty('text-transform', 'uppercase', 'important');
				h.style.setProperty('letter-spacing', '0.1em', 'important');
				h.style.setProperty('text-align', 'center', 'important');
				h.style.setProperty('margin-top', '0', 'important');
				h.style.setProperty('margin-bottom', '20px', 'important');
				h.style.setProperty('margin-left', '0', 'important');
				h.style.setProperty('margin-right', '0', 'important');
				h.style.setProperty('padding-top', '0', 'important');
				h.style.setProperty('padding-bottom', '10px', 'important');
				h.style.setProperty('border-bottom', '2px solid rgba(255,255,255,0.35)', 'important');
				h.style.setProperty('line-height', '1.3', 'important');
				h.style.setProperty('display', 'block', 'important');

				// Walk up and zero out every ancestor's top spacing inside the
				// column until we hit the column boundary.
				var ancestor = h.parentElement;
				while (ancestor && ancestor !== col) {
					ancestor.style.setProperty('margin-top', '0', 'important');
					ancestor.style.setProperty('padding-top', '0', 'important');
					ancestor = ancestor.parentElement;
				}

				h.querySelectorAll('mark, span, strong, b, em').forEach(function (inner) {
					if (isIconElement(inner)) return;
					inner.style.setProperty('font-size', '1.15rem', 'important');
					inner.style.setProperty('font-weight', '800', 'important');
					inner.style.setProperty('letter-spacing', '0.1em', 'important');
				});
			});

			// 3. Body text — anything NOT inside a heading.
			col.querySelectorAll('p, li, a, .elementor-icon-list-text').forEach(function (t) {
				if (isIconElement(t)) return;
				if (seen.indexOf(t) !== -1) return;
				// Skip if it lives inside one of our recognized headings.
				for (var i = 0; i < seen.length; i++) {
					if (seen[i].contains(t)) return;
				}
				t.style.setProperty('font-weight', '500', 'important');
				t.style.setProperty('font-size', '1rem', 'important');
				t.style.setProperty('line-height', '1.6', 'important');
				t.style.setProperty('margin', '0', 'important');

				t.querySelectorAll('mark, span, strong, b').forEach(function (inner) {
					if (isIconElement(inner)) return;
					inner.style.setProperty('font-size', '1rem', 'important');
					inner.style.setProperty('font-weight', '500', 'important');
				});
			});

			// 3b. Force a consistent vertical gap between rows in both lists.
			//     widget-area-1 uses <ul><li> Gutenberg list; widget-area-3
			//     uses Elementor icon-list. Apply the same row gap to both. */
			col.querySelectorAll('ul.wp-block-list, .wp-block-list, .elementor-icon-list-items').forEach(function (list) {
				list.style.setProperty('display', 'flex', 'important');
				list.style.setProperty('flex-direction', 'column', 'important');
				list.style.setProperty('gap', '14px', 'important');
				list.style.setProperty('margin', '0', 'important');
				list.style.setProperty('padding', '0', 'important');
				list.style.setProperty('list-style', 'none', 'important');
				list.style.setProperty('align-items', 'center', 'important');
			});
			col.querySelectorAll('ul.wp-block-list li, .elementor-icon-list-item').forEach(function (li) {
				li.style.setProperty('margin', '0', 'important');
				li.style.setProperty('padding', '0', 'important');
				li.style.setProperty('list-style', 'none', 'important');
			});

			// 4. Icons — white color, sized; DO NOT touch font-family.
			col.querySelectorAll(
				'.elementor-icon-list-icon i, ' +
				'.elementor-icon-list-icon svg, ' +
				'.elementor-icon i, ' +
				'.elementor-icon svg, ' +
				'i[class*="fa-"], i[class*="eicon"], i[class*="dashicons"]'
			).forEach(function (i) {
				i.style.setProperty('color', '#ffffff', 'important');
				i.style.setProperty('fill', '#ffffff', 'important');
				i.style.setProperty('font-size', '1.2rem', 'important');
				// Width/height only on SVG (icon-fonts size themselves via font-size).
				if (i.tagName.toLowerCase() === 'svg') {
					i.style.setProperty('width', '22px', 'important');
					i.style.setProperty('height', '22px', 'important');
				}
			});
		});
	}

	/* Final safety net: after the footer has fully rendered, measure both
	 * heading positions. If they still don't line up (because some upstream
	 * CSS pushed one down by a few pixels), apply a negative margin-top to
	 * whichever is lower so they end up on the same Y. */

	function equalizeFooterHeadings() {
		// Use the EXACT heading elements that restyleFooterBlocks identified
		// (might be h1-h6, .wp-block-heading, OR a heuristic match like
		// a paragraph the first-text-bearing-child rule picked up).
		var h1 = _footerHeadings['widget-area-1'];
		var h3 = _footerHeadings['widget-area-3'];

		// Fallback to querySelector if cache is empty (e.g. equalize fired
		// before restyle on first paint).
		if (!h1) {
			var col1 = document.querySelector('footer.ct-footer [data-column="widget-area-1"]');
			if (col1) h1 = col1.querySelector('h1, h2, h3, h4, h5, h6, .wp-block-heading');
		}
		if (!h3) {
			var col3 = document.querySelector('footer.ct-footer [data-column="widget-area-3"]');
			if (col3) h3 = col3.querySelector('h1, h2, h3, h4, h5, h6, .wp-block-heading, .elementor-heading-title, .widget-title');
		}
		if (!h1 || !h3) return;

		// Clear any prior transform so we measure the true natural offset.
		h1.style.removeProperty('transform');
		h3.style.removeProperty('transform');

		// Force a reflow so the cleared transforms take effect before measuring.
		void h1.offsetHeight;

		var top1 = h1.getBoundingClientRect().top;
		var top3 = h3.getBoundingClientRect().top;
		var diff = Math.round(top3 - top1);

		if (Math.abs(diff) < 2) return;

		// Use translateY (transform) — restyleFooterBlocks doesn't touch
		// the transform property, so the correction survives re-runs.
		if (diff > 0) {
			h3.style.setProperty('transform', 'translateY(' + (-diff) + 'px)', 'important');
		} else {
			h1.style.setProperty('transform', 'translateY(' + diff + 'px)', 'important');
		}
	}

	function setupFooterRestyle() {
		restyleFooterBlocks();
		equalizeFooterHeadings();
		// Re-run after async footer renders (Elementor / Customizer preview / etc.)
		setTimeout(function () { restyleFooterBlocks(); equalizeFooterHeadings(); }, 400);
		setTimeout(function () { restyleFooterBlocks(); equalizeFooterHeadings(); }, 1500);

		// Re-equalize on window resize (layout reflows can change offsets).
		var resizeDebounce;
		window.addEventListener('resize', function () {
			clearTimeout(resizeDebounce);
			resizeDebounce = setTimeout(equalizeFooterHeadings, 100);
		});

		// Watch the footer for mutations (Customizer live preview re-renders it).
		var footer = document.querySelector('footer.ct-footer');
		if (footer) {
			var debounce;
			var obs = new MutationObserver(function () {
				clearTimeout(debounce);
				debounce = setTimeout(function () {
					restyleFooterBlocks();
					equalizeFooterHeadings();
				}, 150);
			});
			obs.observe(footer, { childList: true, subtree: true });
		}
	}

	function init() {
		buildSidebarLayout();
		cacheProducts();
		rewriteSaleBadges();
		wrapCartAndWishlist();
		setupAddedToCartHandler();
		setupBuyNowButton();
		moveWishlistIntoCartActions();
		setupLightboxScrollClose();
		translateCartBlockStrings();
		setupBgnObserver();
		setupQuantityThrottle();
		setupFooterRestyle();
		injectBackToHomeLink();
		emptyMsg = document.querySelector('[data-kastor-shop-filter-empty]');
		// Bind events even if products is empty — the user might toggle
		// inputs and we don't want them silently inert. applyFilters
		// will simply do nothing meaningful with zero products.
		bindEvents();

		// TI Wishlist re-renders the button asynchronously after its own
		// AJAX init; re-wrap once that's done so the heart still ends up
		// next to the cart button.
		setTimeout(wrapCartAndWishlist, 300);
		setTimeout(wrapCartAndWishlist, 1200);
		setTimeout(moveWishlistIntoCartActions, 300);
		setTimeout(moveWishlistIntoCartActions, 1200);

		// Cart/Checkout Block boots asynchronously — re-translate after JS init.
		setTimeout(translateCartBlockStrings, 300);
		setTimeout(translateCartBlockStrings, 1200);
		setTimeout(translateCartBlockStrings, 2500);

		// Same for BGN appender (Cart Block renders prices via JS).
		setTimeout(appendBgnToPrices, 300);
		setTimeout(appendBgnToPrices, 1200);
		setTimeout(appendBgnToPrices, 2500);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
