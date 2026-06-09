/**
 * Live filter for product grids.
 *
 * Listens to [data-kastor-shop-filter-input] and hides product cards whose
 * title doesn't contain the search query. Pagination still works on the
 * server side; this is purely client-side filtering of the products that
 * happen to be on the current page.
 */
(function () {
	'use strict';

	function normalise(s) {
		// Lowercase + strip accents/diacritics so "JCC" matches "jcc" and
		// "сита" matches "Сита".
		return (s || '')
			.toString()
			.normalize('NFD')
			.replace(/[̀-ͯ]/g, '')
			.toLowerCase()
			.trim();
	}

	function init(wrap) {
		var input = wrap.querySelector('[data-kastor-shop-filter-input]');
		var empty = wrap.querySelector('[data-kastor-shop-filter-empty]');

		if (!input) {
			return;
		}

		// The product grid lives outside the filter wrap, in `.woocommerce ul.products`.
		var products = document.querySelectorAll(
			'.woocommerce ul.products li.product, ul.products li.product'
		);

		if (!products.length) {
			return;
		}

		// Pre-compute and cache the title of each product so we don't query
		// the DOM on every keystroke.
		var entries = [];
		products.forEach(function (li) {
			var titleEl = li.querySelector(
				'.woocommerce-loop-product__title, h2, h3, .product-title'
			);
			entries.push({
				el: li,
				text: normalise(titleEl ? titleEl.textContent : li.textContent)
			});
		});

		function apply() {
			var q = normalise(input.value);
			var visible = 0;

			entries.forEach(function (e) {
				var match = q === '' || e.text.indexOf(q) !== -1;
				e.el.style.display = match ? '' : 'none';
				if (match) {
					visible++;
				}
			});

			if (empty) {
				empty.hidden = visible !== 0;
			}
		}

		input.addEventListener('input', apply);
		input.addEventListener('search', apply); // when the user clicks the X in a search input
	}

	function boot() {
		document.querySelectorAll('[data-kastor-shop-filter]').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
