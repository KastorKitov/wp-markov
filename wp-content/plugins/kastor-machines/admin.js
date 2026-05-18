(function () {
	'use strict';

	/* ------------------------------------------------------------------ */
	/* Parameters metabox: Add / Remove rows                                */
	/* ------------------------------------------------------------------ */

	function initParams(wrap) {
		var rows = wrap.querySelector('[data-kastor-rows]');
		var tpl = wrap.querySelector('[data-kastor-template]');
		var addBtn = wrap.querySelector('[data-kastor-add]');

		if (!rows || !tpl || !addBtn) {
			return;
		}

		addBtn.addEventListener('click', function () {
			var clone = tpl.content.firstElementChild.cloneNode(true);
			rows.appendChild(clone);
			var firstInput = clone.querySelector('input');
			if (firstInput) {
				firstInput.focus();
			}
		});

		wrap.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-kastor-remove]');
			if (!btn) {
				return;
			}
			var row = btn.closest('[data-kastor-row]');
			if (!row) {
				return;
			}
			var allRows = rows.querySelectorAll('[data-kastor-row]');
			if (allRows.length <= 1) {
				row.querySelectorAll('input').forEach(function (input) {
					input.value = '';
				});
				return;
			}
			row.remove();
		});
	}


	/* ------------------------------------------------------------------ */
	/* Gallery metabox: WP media picker + reorder + remove                  */
	/* ------------------------------------------------------------------ */

	function initGallery(wrap) {
		if (typeof window.wp === 'undefined' || !window.wp.media) {
			// wp.media isn't available — admin.js was loaded outside a post screen.
			return;
		}

		var list = wrap.querySelector('[data-kastor-gallery-list]');
		var input = wrap.querySelector('[data-kastor-gallery-input]');
		var addBtn = wrap.querySelector('[data-kastor-gallery-add]');

		if (!list || !input || !addBtn) {
			return;
		}

		var frame = null;

		function syncInput() {
			var ids = Array.prototype.map.call(
				list.querySelectorAll('.kastor-gallery-item'),
				function (li) { return li.getAttribute('data-id'); }
			).filter(Boolean);
			input.value = ids.join(',');
		}

		function makeItem(id, thumbUrl) {
			var li = document.createElement('li');
			li.className = 'kastor-gallery-item';
			li.setAttribute('data-id', id);
			li.setAttribute('draggable', 'true');

			var img = document.createElement('img');
			img.src = thumbUrl;
			img.alt = '';
			li.appendChild(img);

			var removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'kastor-gallery-remove';
			removeBtn.setAttribute('data-kastor-gallery-remove', '');
			removeBtn.setAttribute('aria-label', 'Премахни');
			removeBtn.textContent = '×'; // ×
			li.appendChild(removeBtn);

			return li;
		}

		function openPicker() {
			if (frame) {
				frame.open();
				return;
			}

			frame = window.wp.media({
				title: 'Изберете изображения за галерия',
				button: { text: 'Добави в галерия' },
				library: { type: 'image' },
				multiple: 'add'
			});

			frame.on('select', function () {
				var selection = frame.state().get('selection');
				// Existing IDs to avoid duplicates.
				var existing = {};
				list.querySelectorAll('.kastor-gallery-item').forEach(function (li) {
					existing[li.getAttribute('data-id')] = true;
				});

				selection.each(function (attachment) {
					var att = attachment.toJSON();
					if (existing[att.id]) {
						return;
					}
					var thumb = (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) || att.url;
					list.appendChild(makeItem(att.id, thumb));
				});

				syncInput();
			});

			frame.open();
		}

		addBtn.addEventListener('click', openPicker);

		// Remove button per item.
		list.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-kastor-gallery-remove]');
			if (!btn) {
				return;
			}
			var li = btn.closest('.kastor-gallery-item');
			if (li) {
				li.remove();
				syncInput();
			}
		});

		// Drag & drop reorder (HTML5 native).
		var dragged = null;

		list.addEventListener('dragstart', function (e) {
			var li = e.target.closest('.kastor-gallery-item');
			if (!li) {
				return;
			}
			dragged = li;
			li.classList.add('is-dragging');
			e.dataTransfer.effectAllowed = 'move';
			// Required for Firefox.
			try { e.dataTransfer.setData('text/plain', li.getAttribute('data-id')); } catch (_) {}
		});

		list.addEventListener('dragend', function () {
			if (dragged) {
				dragged.classList.remove('is-dragging');
			}
			dragged = null;
			list.querySelectorAll('.kastor-gallery-item').forEach(function (li) {
				li.classList.remove('is-drop-target');
			});
			syncInput();
		});

		list.addEventListener('dragover', function (e) {
			e.preventDefault();
			if (!dragged) {
				return;
			}
			var target = e.target.closest('.kastor-gallery-item');
			if (!target || target === dragged) {
				return;
			}
			list.querySelectorAll('.kastor-gallery-item').forEach(function (li) {
				if (li !== target) li.classList.remove('is-drop-target');
			});
			target.classList.add('is-drop-target');

			var rect = target.getBoundingClientRect();
			var midX = rect.left + rect.width / 2;
			if (e.clientX < midX) {
				list.insertBefore(dragged, target);
			} else {
				list.insertBefore(dragged, target.nextSibling);
			}
		});
	}


	/* ------------------------------------------------------------------ */
	/* Boot                                                                 */
	/* ------------------------------------------------------------------ */

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-kastor-params]').forEach(initParams);
		document.querySelectorAll('[data-kastor-highlights]').forEach(initParams); // same row-add/remove behaviour
		document.querySelectorAll('[data-kastor-gallery]').forEach(initGallery);
	});
})();
