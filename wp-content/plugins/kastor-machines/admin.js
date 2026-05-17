(function () {
	'use strict';

	function init(wrap) {
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
			// Never remove the very last row — leave one blank instead.
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

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-kastor-params]').forEach(init);
	});
})();