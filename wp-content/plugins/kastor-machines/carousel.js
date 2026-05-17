(function () {
	'use strict';

	function init() {
		if (typeof window.Swiper === 'undefined') {
			return;
		}

		document.querySelectorAll('[data-kastor-carousel]').forEach(function (root) {
			var mainEl = root.querySelector('.kastor-machine__swiper');
			var thumbsEl = root.querySelector('.kastor-machine__thumbs');
			if (!mainEl) {
				return;
			}

			var thumbsSwiper = null;
			if (thumbsEl) {
				thumbsSwiper = new window.Swiper(thumbsEl, {
					slidesPerView: 'auto',
					spaceBetween: 8,
					watchSlidesProgress: true,
					freeMode: true
				});
			}

			new window.Swiper(mainEl, {
				loop: false,
				slidesPerView: 1,
				spaceBetween: 0,
				keyboard: { enabled: true },
				navigation: {
					nextEl: root.querySelector('.kastor-machine__nav-next'),
					prevEl: root.querySelector('.kastor-machine__nav-prev')
				},
				pagination: thumbsSwiper ? false : {
					el: root.querySelector('.kastor-machine__pagination'),
					clickable: true
				},
				thumbs: thumbsSwiper ? { swiper: thumbsSwiper } : undefined
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
