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

	// The conditionally-required invoice fields (matched by id substring). The
	// VAT/EIK field is intentionally NOT listed — it stays genuinely optional.
	var COMPANY_KEYS = [
		'invoice-company',
		'invoice-mol',
		'invoice-bulstat',
		'invoice-address',
	];

	// WooCommerce appends an optional marker to optional-field labels. We strip
	// it from the required fields and replace it with a red "*". Cover the
	// English label WC ships, the Bulgarian gettext variant, and our own
	// localized "(по избор)" (so re-runs after the localizer stay clean).
	var OPTIONAL_TEXTS = [ '(optional)', '(незадължително)', '(по избор)' ];
	var REQUIRED_TEXT = '*';

	// --- Merge the email ("Contact information") step into the address step so
	// they read as one "Данни за доставка" section. We promote the contact
	// heading to MERGED_TITLE and hide the address step's own heading (via CSS,
	// keyed off the classes added below). Match the headings WC currently
	// renders — the custom Bulgarian billing title plus English fallbacks —
	// case-insensitively so a WC/translation change is less likely to break it.
	var MERGED_TITLE = 'Данни за доставка';
	var CONTACT_TITLES = [
		'contact information',
		'информация за контакт',
		'контактна информация',
	];
	var ADDRESS_TITLES = [
		'адрес за фактуриране и плащане',
		'адрес за фактуриране',
		'billing address',
		'billing and shipping address',
	];

	// True when `text` equals or starts with any entry in `list`. The
	// startsWith case tolerates a trailing step-counter / whitespace that some
	// WC versions tuck inside the heading element.
	function startsWithAny( text, list ) {
		for ( var i = 0; i < list.length; i++ ) {
			if ( text === list[ i ] || text.indexOf( list[ i ] ) === 0 ) {
				return true;
			}
		}
		return false;
	}

	function mergeContactIntoAddress() {
		var titles = document.querySelectorAll(
			'.wc-block-components-checkout-step__title'
		);
		for ( var i = 0; i < titles.length; i++ ) {
			var el = titles[ i ];
			var step = el.closest( '.wc-block-components-checkout-step' );
			if ( ! step ) {
				continue;
			}
			var text = ( el.textContent || '' ).trim().toLowerCase();

			if ( startsWithAny( text, CONTACT_TITLES ) ) {
				step.classList.add( 'kastor-merged-contact' );
				// Relabel only when needed so we don't feed the MutationObserver
				// a pointless childList change every frame.
				if ( ( el.textContent || '' ).trim() !== MERGED_TITLE ) {
					el.textContent = MERGED_TITLE;
				}
			} else if ( startsWithAny( text, ADDRESS_TITLES ) ) {
				// Hide the address step's heading AND its description line — the
				// promoted contact heading already titles the merged section. We
				// hide the elements directly (rather than via a structural CSS
				// rule) so it works regardless of how deeply WC nests the title.
				step.classList.add( 'kastor-merged-address' );
				el.classList.add( 'kastor-hidden' );
				var desc = step.querySelector(
					'.wc-block-components-checkout-step__description'
				);
				if ( desc ) {
					desc.classList.add( 'kastor-hidden' );
				}
			}
		}
	}

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

	// The "Желая фактура" checkbox is a yes/no toggle; strip the optional marker
	// ("(optional)" / "(по избор)") WooCommerce appends to its label so it reads
	// as a clean "Желая фактура". Runs before localizeOptionalMarkers() so the
	// checkbox never ends up showing "(по избор)".
	function cleanRequestCheckboxLabel() {
		var boxes = getCheckboxes();
		for ( var i = 0; i < boxes.length; i++ ) {
			var label =
				boxes[ i ].closest( 'label' ) ||
				boxes[ i ].closest( '.wc-block-components-checkbox' ) ||
				boxes[ i ].parentElement;
			if ( ! label ) {
				continue;
			}
			var walker = document.createTreeWalker( label, NodeFilter.SHOW_TEXT, null );
			var node;
			while ( ( node = walker.nextNode() ) ) {
				var v = node.nodeValue;
				if ( ! v ) {
					continue;
				}
				var changed = v;
				for ( var o = 0; o < OPTIONAL_TEXTS.length; o++ ) {
					changed = changed.split( OPTIONAL_TEXTS[ o ] ).join( '' );
				}
				if ( changed !== v ) {
					node.nodeValue = changed
						.replace( /\s{2,}/g, ' ' )
						.replace( /\s+$/, '' );
				}
			}
		}
	}

	// WooCommerce's additional-checkout-fields render their optional marker as a
	// literal English "(optional)" that isn't localized (core address fields
	// already show "(по избор)"). markRequired() strips it from the company
	// fields; anything still showing "(optional)" is a genuinely optional field
	// (e.g. the "Желая фактура" checkbox), so localize it to match the rest.
	function localizeOptionalMarkers() {
		var root = document.querySelector(
			'.wc-block-checkout, .wp-block-woocommerce-checkout'
		);
		if ( ! root ) {
			return;
		}
		var walker = document.createTreeWalker( root, NodeFilter.SHOW_TEXT, null );
		var node;
		while ( ( node = walker.nextNode() ) ) {
			var v = node.nodeValue;
			if ( v && v.indexOf( '(optional)' ) !== -1 ) {
				node.nodeValue = v.replace( /\(optional\)/g, '(по избор)' );
			}
		}
	}

	function sync() {
		document.body.classList.toggle( 'kastor-invoice-on', isRequested() );
		markRequired();
		cleanRequestCheckboxLabel();
		localizeOptionalMarkers();
		mergeContactIntoAddress();
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
