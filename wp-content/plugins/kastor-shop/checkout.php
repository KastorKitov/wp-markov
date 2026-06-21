<?php
/**
 * Kastor Shop — Checkout (block) customizations.
 *
 * Loaded from kastor-shop.php. Adds an optional "Желая фактура" (request an
 * invoice) checkbox to the block checkout. When ticked, three company fields
 * appear: Име на фирма / МОЛ / ИДН номер. The fields are captured with
 * WooCommerce's Additional Checkout Fields API (so they save to the order and
 * show in admin + emails automatically). Conditional show/hide is done in
 * checkout.js; conditional "required" is enforced server-side below.
 *
 * @package KastorShop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where the invoice block renders in the block checkout.
 *
 * 'order'   — "Additional information" area: the fields render ONCE, save to
 *             the order, and sit right after the address area. Correct home for
 *             invoice data. (default)
 * 'address' — Inside the address form. NOTE: block checkout duplicates address
 *             custom fields across billing AND shipping, and binds the data to
 *             an address record (so invoice info would attach to the shipping
 *             address). Only switch to this if you accept that behaviour.
 *
 * Define KASTOR_SHOP_INVOICE_LOCATION elsewhere (wp-config / snippet) to override.
 */
if ( ! defined( 'KASTOR_SHOP_INVOICE_LOCATION' ) ) {
	define( 'KASTOR_SHOP_INVOICE_LOCATION', 'order' );
}

/** Field ids — namespace/name. The substrings after the slash are what the
 *  frontend JS matches on, so keep them in sync with checkout.js. */
const KASTOR_SHOP_FIELD_INVOICE_REQUEST = 'kastor/invoice-request';
const KASTOR_SHOP_FIELD_INVOICE_COMPANY = 'kastor/invoice-company';
const KASTOR_SHOP_FIELD_INVOICE_MOL     = 'kastor/invoice-mol';
const KASTOR_SHOP_FIELD_INVOICE_IDN     = 'kastor/invoice-idn';


/* --------------------------------------------------------------------------
 * 1. Register the invoice checkbox + company fields.
 *    Must run on woocommerce_init (the API isn't available before it).
 * -------------------------------------------------------------------------- */

add_action( 'woocommerce_init', 'kastor_shop_register_invoice_fields' );
function kastor_shop_register_invoice_fields() {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return; // WooCommerce < 8.9 — API unavailable.
	}

	$location = KASTOR_SHOP_INVOICE_LOCATION;

	// The trigger checkbox. Optional — leaving it unchecked is a valid order.
	woocommerce_register_additional_checkout_field( array(
		'id'       => KASTOR_SHOP_FIELD_INVOICE_REQUEST,
		'label'    => 'Желая фактура',
		'location' => $location,
		'type'     => 'checkbox',
		'required' => false,
	) );

	// Company details. Registered as optional; checkout.js shows/hides them and
	// the validation hook below makes them required ONLY when the box is ticked.
	$company_fields = array(
		KASTOR_SHOP_FIELD_INVOICE_COMPANY => 'Име на фирма',
		KASTOR_SHOP_FIELD_INVOICE_MOL     => 'МОЛ',
		KASTOR_SHOP_FIELD_INVOICE_IDN     => 'ИДН номер',
	);

	foreach ( $company_fields as $id => $label ) {
		woocommerce_register_additional_checkout_field( array(
			'id'                => $id,
			'label'             => $label,
			'location'          => $location,
			'type'              => 'text',
			'required'          => false,
			'sanitize_callback' => static function ( $value ) {
				return trim( (string) $value );
			},
		) );
	}
}


/* --------------------------------------------------------------------------
 * 2. Conditional validation — if "Желая фактура" is ticked, the three company
 *    fields become required. The hook fires per checkout location and receives
 *    every field value for that location, so we can cross-check the checkbox.
 * -------------------------------------------------------------------------- */

add_action(
	'woocommerce_blocks_validate_location_' . KASTOR_SHOP_INVOICE_LOCATION . '_fields',
	'kastor_shop_validate_invoice_fields',
	10,
	3
);
function kastor_shop_validate_invoice_fields( $errors, $fields, $group = '' ) {
	if ( ! ( $errors instanceof WP_Error ) ) {
		return;
	}

	// Box not ticked → nothing to require.
	if ( empty( $fields[ KASTOR_SHOP_FIELD_INVOICE_REQUEST ] ) ) {
		return;
	}

	$required = array(
		KASTOR_SHOP_FIELD_INVOICE_COMPANY => 'Моля въведете име на фирма за фактурата.',
		KASTOR_SHOP_FIELD_INVOICE_MOL     => 'Моля въведете МОЛ за фактурата.',
		KASTOR_SHOP_FIELD_INVOICE_IDN     => 'Моля въведете ИДН номер за фактурата.',
	);

	foreach ( $required as $id => $message ) {
		if ( empty( trim( (string) ( $fields[ $id ] ?? '' ) ) ) ) {
			// Suffix the group ('billing'/'shipping') for address location so two
			// instances don't collide on the same error code.
			$code = 'kastor_invoice_' . sanitize_key( $id ) . ( $group ? "_{$group}" : '' );
			$errors->add( $code, $message );
		}
	}
}


/* --------------------------------------------------------------------------
 * 3. Enqueue the show/hide script + brand styling — checkout page only.
 * -------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'kastor_shop_enqueue_checkout_assets', 20 );
function kastor_shop_enqueue_checkout_assets() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	wp_enqueue_style(
		'kastor-shop-checkout',
		KASTOR_SHOP_URL . 'checkout.css',
		array(),
		KASTOR_SHOP_VERSION
	);

	wp_enqueue_script(
		'kastor-shop-checkout',
		KASTOR_SHOP_URL . 'checkout.js',
		array(),
		KASTOR_SHOP_VERSION,
		true
	);
}
