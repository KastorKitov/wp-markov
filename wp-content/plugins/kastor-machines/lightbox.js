/**
 * Lightbox — click any image marked with [data-kastor-lightbox] to open a
 * fullscreen overlay with the full-size version. Esc / click outside to close;
 * ← / → to navigate between images in the same group.
 *
 * Image list is scoped to the closest [data-kastor-carousel] (so each
 * machine page gets its own list); standalone images open as a 1-image group.
 */
(function () {
	'use strict';

	var overlay = null;
	var imgEl = null;
	var captionEl = null;
	var counterEl = null;
	var images = [];     // [{ src, alt }]
	var currentIdx = 0;
	var lastFocused = null;

	function buildOverlay() {
		overlay = document.createElement('div');
		overlay.className = 'kastor-lightbox';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Преглед на изображение');
		overlay.innerHTML =
			'<button type="button" class="kastor-lightbox__close" aria-label="Затвори"></button>' +
			'<button type="button" class="kastor-lightbox__nav kastor-lightbox__nav--prev" aria-label="Предишно"></button>' +
			'<div class="kastor-lightbox__stage">' +
				'<img class="kastor-lightbox__img" alt="" />' +
			'</div>' +
			'<button type="button" class="kastor-lightbox__nav kastor-lightbox__nav--next" aria-label="Следващо"></button>' +
			'<div class="kastor-lightbox__counter" aria-live="polite"></div>';

		document.body.appendChild(overlay);

		imgEl = overlay.querySelector('.kastor-lightbox__img');
		counterEl = overlay.querySelector('.kastor-lightbox__counter');

		overlay.addEventListener('click', function (e) {
			if (e.target === overlay || e.target.classList.contains('kastor-lightbox__stage')) {
				close();
				return;
			}
			if (e.target.closest('.kastor-lightbox__close')) {
				close();
				return;
			}
			if (e.target.closest('.kastor-lightbox__nav--prev')) {
				show(currentIdx - 1);
				return;
			}
			if (e.target.closest('.kastor-lightbox__nav--next')) {
				show(currentIdx + 1);
				return;
			}
		});

		document.addEventListener('keydown', function (e) {
			if (!overlay.classList.contains('is-open')) return;
			if (e.key === 'Escape') { close(); e.preventDefault(); }
			else if (e.key === 'ArrowLeft')  { show(currentIdx - 1); e.preventDefault(); }
			else if (e.key === 'ArrowRight') { show(currentIdx + 1); e.preventDefault(); }
		});
	}

	function open(idx, list, trigger) {
		if (!overlay) buildOverlay();
		if (!list || !list.length) return;

		lastFocused = trigger || document.activeElement;
		images = list;
		show(idx);
		overlay.classList.add('is-open');
		// Hide nav arrows when only one image.
		overlay.classList.toggle('is-single', images.length <= 1);
		document.body.style.overflow = 'hidden';
	}

	function close() {
		if (!overlay) return;
		overlay.classList.remove('is-open');
		document.body.style.overflow = '';
		if (imgEl) imgEl.src = '';
		if (lastFocused && typeof lastFocused.focus === 'function') {
			lastFocused.focus();
		}
	}

	function show(idx) {
		if (!images.length) return;
		currentIdx = ((idx % images.length) + images.length) % images.length;
		imgEl.src = images[currentIdx].src;
		imgEl.alt = images[currentIdx].alt || '';
		if (counterEl) {
			counterEl.textContent = (currentIdx + 1) + ' / ' + images.length;
		}
	}

	function collectImages(trigger) {
		// Scope to the carousel that contains the trigger if any; else the
		// trigger itself is the only image.
		var scope = trigger.closest('[data-kastor-carousel]');
		var anchors;
		if (scope) {
			anchors = Array.prototype.slice.call(
				scope.querySelectorAll('.kastor-machine__swiper [data-kastor-lightbox]')
			);
			if (!anchors.length) {
				anchors = Array.prototype.slice.call(scope.querySelectorAll('[data-kastor-lightbox]'));
			}
		} else {
			anchors = [trigger];
		}

		var seen = Object.create(null);
		var list = [];
		var triggerIdx = 0;
		var triggerHref = trigger.getAttribute('href');

		anchors.forEach(function (a) {
			var src = a.getAttribute('href');
			if (!src || seen[src]) return;
			seen[src] = true;
			var img = a.querySelector('img');
			list.push({ src: src, alt: img ? (img.alt || '') : '' });
			if (src === triggerHref) {
				triggerIdx = list.length - 1;
			}
		});

		return { list: list, idx: triggerIdx };
	}

	document.addEventListener('click', function (e) {
		var a = e.target.closest('[data-kastor-lightbox]');
		if (!a) return;
		e.preventDefault();

		var collected = collectImages(a);
		open(collected.idx, collected.list, a);
	});
})();
