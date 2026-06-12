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


	/* -------- Hide cart button after WC AJAX "added to cart" event -------- */

	function setupAddedToCartHandler() {
		if (typeof window.jQuery === 'undefined') return;

		window.jQuery(document.body).on('added_to_cart',
			function (event, fragments, cart_hash, $button) {
				if ($button && $button.length) {
					$button[0].style.setProperty('display', 'none', 'important');
					var row = $button[0].closest('.kastor-shop__card-actions');
					if (row) row.classList.add('kastor-shop__has-added');
				}
			}
		);
	}


	function init() {
		buildSidebarLayout();
		cacheProducts();
		rewriteSaleBadges();
		wrapCartAndWishlist();
		setupAddedToCartHandler();
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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
