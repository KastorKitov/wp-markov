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

	// The conditionally-required company fields (matched by id substring).
	var COMPANY_KEYS = [ 'invoice-company', 'invoice-mol', 'invoice-idn' ];

	// WooCommerce appends this to optional-field labels. We swap it for a
	// "mandatory" marker because these fields are required once the box is
	// ticked. Cover the English label WC ships and the Bulgarian variant.
	var OPTIONAL_TEXTS = [ '(optional)', '(незадължително)' ];
	var REQUIRED_TEXT = '(задължително)';

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

	// Relabel the company fields so they read as mandatory. Idempotent: once a
	// label is processed it gets data-kastor-required, so re-running on React
	// re-renders only touches freshly-rendered labels — no mutation feedback loop.
	function markRequired() {
		for ( var k = 0; k < COMPANY_KEYS.length; k++ ) {
			var inputs = document.querySelectorAll(
				'input[id*="' + COMPANY_KEYS[ k ] + '"]'
			);
			for ( var i = 0; i < inputs.length; i++ ) {
				var wrapper =
					inputs[ i ].closest( '.wc-block-components-text-input' ) ||
					inputs[ i ].parentElement;
				if ( ! wrapper ) {
					continue;
				}
				var label = wrapper.querySelector( 'label' );
				if ( ! label || label.getAttribute( 'data-kastor-required' ) ) {
					continue;
				}

				// Strip WooCommerce's "(optional)" wherever it sits in the label.
				var nodes = label.childNodes;
				for ( var n = 0; n < nodes.length; n++ ) {
					var node = nodes[ n ];
					if ( node.nodeType === 3 ) {
						var text = node.nodeValue;
						for ( var o = 0; o < OPTIONAL_TEXTS.length; o++ ) {
							text = text.replace( OPTIONAL_TEXTS[ o ], '' );
						}
						node.nodeValue = text.replace( /\s+$/, '' );
					} else if (
						node.nodeType === 1 &&
						OPTIONAL_TEXTS.indexOf(
							( node.textContent || '' ).trim()
						) !== -1
					) {
						node.parentNode.removeChild( node );
						n--;
					}
				}

				var mark = document.createElement( 'span' );
				mark.className = 'kastor-required-mark';
				mark.textContent = ' ' + REQUIRED_TEXT;
				label.appendChild( mark );
				label.setAttribute( 'data-kastor-required', '1' );
			}
		}
	}

	function sync() {
		document.body.classList.toggle( 'kastor-invoice-on', isRequested() );
		markRequired();
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
