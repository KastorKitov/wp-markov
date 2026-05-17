/**
 * Specs metabox controller — multi-model comparison table.
 *
 * Holds a state object: { models: string[], rows: Row[], note: string }
 *   Row = { group: string, label: string, unit: string, values: string[] }
 *
 * Adding/removing models or rows triggers a re-render; typing in text inputs
 * updates state in place (no re-render) to preserve focus.
 *
 * On form submit, the current state is JSON-stringified into the hidden
 * <input data-kastor-specs-input>, which is read by the PHP save handler.
 */
(function () {
	'use strict';

	function init(wrap) {
		var initEl = wrap.querySelector('[data-kastor-specs-init]');
		var hidden = wrap.querySelector('[data-kastor-specs-input]');
		var root = wrap.querySelector('[data-kastor-specs-root]');
		var noteEl = wrap.querySelector('[data-kastor-specs-note]');

		if (!initEl || !hidden || !root || !noteEl) {
			return;
		}

		var state;
		try {
			state = JSON.parse(initEl.textContent || '{}');
		} catch (e) {
			state = {};
		}
		if (!state || typeof state !== 'object') { state = {}; }
		if (!Array.isArray(state.models)) { state.models = []; }
		if (!Array.isArray(state.rows))   { state.rows   = []; }
		if (typeof state.note !== 'string') { state.note = ''; }

		// Normalise each row to have the right number of value cells.
		function normaliseRow(row) {
			if (!row || typeof row !== 'object') { row = {}; }
			if (typeof row.group !== 'string') { row.group = ''; }
			if (typeof row.label !== 'string') { row.label = ''; }
			if (typeof row.unit  !== 'string') { row.unit  = ''; }
			if (!Array.isArray(row.values))    { row.values = []; }
			while (row.values.length < state.models.length) { row.values.push(''); }
			while (row.values.length > state.models.length) { row.values.pop(); }
			return row;
		}
		state.rows = state.rows.map(normaliseRow);

		noteEl.value = state.note;

		function syncHidden() {
			hidden.value = JSON.stringify(state);
		}

		/* -------- Builders -------- */

		function el(tag, attrs, children) {
			var node = document.createElement(tag);
			if (attrs) {
				Object.keys(attrs).forEach(function (k) {
					if (k === 'class') node.className = attrs[k];
					else if (k === 'text') node.textContent = attrs[k];
					else if (k.indexOf('data-') === 0) node.setAttribute(k, attrs[k]);
					else node[k] = attrs[k];
				});
			}
			if (children) {
				children.forEach(function (c) { if (c) node.appendChild(c); });
			}
			return node;
		}

		function buildModelsSection() {
			var wrap = el('div', { class: 'kastor-specs-models' });
			wrap.appendChild(el('h4', { text: 'Модели (колони)', class: 'kastor-specs-subhead' }));

			var pills = el('div', { class: 'kastor-specs-pills' });
			state.models.forEach(function (m, idx) {
				var pill = el('div', { class: 'kastor-specs-pill' });
				var input = el('input', {
					type: 'text',
					value: m,
					placeholder: 'напр. JCC 03',
					'data-kastor-model-idx': String(idx)
				});
				input.addEventListener('input', function () {
					state.models[idx] = input.value;
					// Update the header in the table too.
					var th = root.querySelector('thead th[data-model-header="' + idx + '"]');
					if (th) { th.textContent = input.value || '(модел ' + (idx + 1) + ')'; }
					syncHidden();
				});
				var rm = el('button', {
					type: 'button',
					class: 'kastor-specs-pill-remove',
					'data-kastor-remove-model': String(idx),
					'aria-label': 'Премахни модел',
					text: '×'
				});
				pill.appendChild(input);
				pill.appendChild(rm);
				pills.appendChild(pill);
			});

			wrap.appendChild(pills);

			var addBtn = el('button', {
				type: 'button',
				class: 'button button-secondary',
				'data-kastor-add-model': '1',
				text: '+ Добави модел'
			});
			wrap.appendChild(addBtn);

			return wrap;
		}

		function buildTable() {
			var wrap = el('div', { class: 'kastor-specs-table-wrap' });
			wrap.appendChild(el('h4', { text: 'Редове', class: 'kastor-specs-subhead' }));

			if (state.models.length === 0) {
				wrap.appendChild(el('p', {
					class: 'description',
					text: 'Първо добавете поне един модел по-горе, за да можете да въвеждате стойности.'
				}));
				return wrap;
			}

			var table = el('table', { class: 'kastor-specs-table' });

			// Header
			var thead = el('thead');
			var hr = el('tr');
			hr.appendChild(el('th', { class: 'col-group', text: 'Група' }));
			hr.appendChild(el('th', { class: 'col-label', text: 'Параметър' }));
			hr.appendChild(el('th', { class: 'col-unit',  text: 'Единици' }));
			state.models.forEach(function (m, idx) {
				var th = el('th', { class: 'col-value', text: m || '(модел ' + (idx + 1) + ')' });
				th.setAttribute('data-model-header', String(idx));
				hr.appendChild(th);
			});
			hr.appendChild(el('th', { class: 'col-remove', text: '' }));
			thead.appendChild(hr);
			table.appendChild(thead);

			// Body
			var tbody = el('tbody');
			state.rows.forEach(function (row, rowIdx) {
				var tr = el('tr');
				tr.setAttribute('data-row-idx', String(rowIdx));

				function makeInput(field, placeholder) {
					var input = el('input', {
						type: 'text',
						value: row[field] || '',
						placeholder: placeholder || ''
					});
					input.addEventListener('input', function () {
						state.rows[rowIdx][field] = input.value;
						syncHidden();
					});
					return input;
				}

				var tdG = el('td', { class: 'col-group' }); tdG.appendChild(makeInput('group', 'напр. Осн. размери')); tr.appendChild(tdG);
				var tdL = el('td', { class: 'col-label' }); tdL.appendChild(makeInput('label', 'напр. Дължина (a)')); tr.appendChild(tdL);
				var tdU = el('td', { class: 'col-unit'  }); tdU.appendChild(makeInput('unit',  'напр. мм.'));        tr.appendChild(tdU);

				state.models.forEach(function (_m, mIdx) {
					var td = el('td', { class: 'col-value' });
					var input = el('input', {
						type: 'text',
						value: row.values[mIdx] || '',
						placeholder: '—'
					});
					input.addEventListener('input', function () {
						state.rows[rowIdx].values[mIdx] = input.value;
						syncHidden();
					});
					td.appendChild(input);
					tr.appendChild(td);
				});

				var tdR = el('td', { class: 'col-remove' });
				tdR.appendChild(el('button', {
					type: 'button',
					class: 'button kastor-specs-row-remove',
					'data-kastor-remove-row': String(rowIdx),
					'aria-label': 'Премахни ред',
					text: '×'
				}));
				tr.appendChild(tdR);

				tbody.appendChild(tr);
			});
			table.appendChild(tbody);
			wrap.appendChild(table);

			var addRow = el('p', {});
			addRow.appendChild(el('button', {
				type: 'button',
				class: 'button button-secondary',
				'data-kastor-add-row': '1',
				text: '+ Добави ред'
			}));
			wrap.appendChild(addRow);

			return wrap;
		}

		function render() {
			root.innerHTML = '';
			root.appendChild(buildModelsSection());
			root.appendChild(buildTable());
			syncHidden();
		}

		/* -------- Mutations -------- */

		function addModel() {
			state.models.push('');
			state.rows.forEach(function (r) { r.values.push(''); });
			render();
		}

		function removeModel(idx) {
			if (idx < 0 || idx >= state.models.length) { return; }
			state.models.splice(idx, 1);
			state.rows.forEach(function (r) { r.values.splice(idx, 1); });
			render();
		}

		function addRow() {
			var row = { group: '', label: '', unit: '', values: [] };
			for (var i = 0; i < state.models.length; i++) { row.values.push(''); }
			state.rows.push(row);
			render();
		}

		function removeRow(idx) {
			if (idx < 0 || idx >= state.rows.length) { return; }
			state.rows.splice(idx, 1);
			render();
		}

		/* -------- Event delegation -------- */

		root.addEventListener('click', function (e) {
			var t;
			if ((t = e.target.closest('[data-kastor-add-model]'))) { addModel(); return; }
			if ((t = e.target.closest('[data-kastor-add-row]')))   { addRow();   return; }
			if ((t = e.target.closest('[data-kastor-remove-model]'))) {
				removeModel(parseInt(t.getAttribute('data-kastor-remove-model'), 10));
				return;
			}
			if ((t = e.target.closest('[data-kastor-remove-row]'))) {
				removeRow(parseInt(t.getAttribute('data-kastor-remove-row'), 10));
				return;
			}
		});

		noteEl.addEventListener('input', function () {
			state.note = noteEl.value;
			syncHidden();
		});

		render();
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-kastor-specs]').forEach(init);
	});
})();
