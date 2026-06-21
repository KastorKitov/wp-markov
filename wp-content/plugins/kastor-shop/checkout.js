/* ------------------------------------------------------------------------ */
/* Kastor Shop — checkout invoice toggle                                      */
/*                                                                            */
/* WooCommerce's block checkout has no native conditional-field support, so   */
/* we toggle the three company fields (Име на фирма / МОЛ / ИДН номер) based  */
/* on the "Желая фактура" checkbox.                                           */
/*                                                                            */
/* The block checkout is a React app that re-renders on its own, which would  */
/* wipe inline display changes. So we work via a single class on <body>       */
/* (.kastor-invoice-on) that checkout.css keys off, and re-apply it on every  */
/* mutation. Fields are matched by an id SUBSTRING so we don't depend on how  */
/* WooCommerce prefixes additional-field ids across versions.                 */
/* ------------------------------------------------------------------------ */
( function () {
	'use strict';

	var REQUEST_KEY = 'invoice-request'; // matches kastor/invoice-request

	function getCheckboxes() {
		// Checkbox whose id contains our field key. Covers any WC prefix
		// (e.g. "kastor/invoice-request", "billing-kastor-invoice-request").
		return document.querySelectorAll(
			'input[type="checkbox"][id*="' + REQUEST_KEY + '"]'
		);
	}

	function isRequested() {
		var boxes = getCheckboxes();
		for ( var i = 0; i < boxes.length; i++ ) {
			if ( boxes[ i ].checked ) {
				return true;
			}
		}
		return false;
	}

	function sync() {
		document.body.classList.toggle( 'kastor-invoice-on', isRequested() );
	}

	// React re-renders the checkout form; re-sync whenever the DOM changes.
	// We observe childList/subtree only (not attributes), so toggling the body
	// class never re-triggers the observer — no feedback loop.
	var scheduled = false;
	function scheduleSync() {
		if ( scheduled ) {
			return;
		}
		scheduled = true;
		window.requestAnimationFrame( function () {
			scheduled = false;
			sync();
		} );
	}

	function init() {
		sync();

		// Delegated change handler — works even when the checkbox is re-created.
		document.addEventListener( 'change', function ( e ) {
			var t = e.target;
			if (
				t &&
				t.matches &&
				t.matches( 'input[type="checkbox"][id*="' + REQUEST_KEY + '"]' )
			) {
				sync();
			}
		} );

		var observer = new MutationObserver( scheduleSync );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
