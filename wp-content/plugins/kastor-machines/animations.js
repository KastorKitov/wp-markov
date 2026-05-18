/**
 * Scroll-reveal: adds `.is-revealed` to elements with [data-kastor-reveal]
 * the first time they enter the viewport. CSS handles the actual animation
 * (initial opacity:0 + transform → final state on .is-revealed).
 *
 * Respects prefers-reduced-motion: if the user has reduced motion on,
 * elements are revealed instantly with no observer overhead.
 */
(function () {
	'use strict';

	var prefersReducedMotion = window.matchMedia &&
		window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function revealAll() {
		document.querySelectorAll('[data-kastor-reveal]').forEach(function (el) {
			el.classList.add('is-revealed');
		});
	}

	function init() {
		if (prefersReducedMotion || typeof window.IntersectionObserver === 'undefined') {
			revealAll();
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-revealed');
					observer.unobserve(entry.target);
				}
			});
		}, {
			threshold: 0.15,
			rootMargin: '0px 0px -60px 0px'
		});

		document.querySelectorAll('[data-kastor-reveal]').forEach(function (el) {
			observer.observe(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
