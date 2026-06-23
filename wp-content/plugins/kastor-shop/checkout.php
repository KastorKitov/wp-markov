<?php
/**
 * Kastor Shop — Checkout (block) customizations.
 *
 * Loaded from kastor-shop.php. Adds an optional "Желая фактура" (request an
 * invoice) checkbox to the block checkout. When ticked, five company fields
 * appear: Име на фирма / МОЛ / Булстат на фирма / ИН по ЗДДС или (ЕИК/ЕГН) /
 * Адрес на фирмата. The fields are captured with WooCommerce's Additional
 * Checkout Fields API (so they save to the order and show in admin + emails
 * automatically). Conditional show/hide is done in checkout.js; conditional
 * "required" is enforced server-side below for every field except VAT/EIK,
 * which stays optional.
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
const KASTOR_SHOP_FIELD_INVOICE_BULSTAT = 'kastor/invoice-bulstat';
const KASTOR_SHOP_FIELD_INVOICE_VAT     = 'kastor/invoice-vat';
const KASTOR_SHOP_FIELD_INVOICE_ADDRESS = 'kastor/invoice-address';


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

	// Company details, in display order. All registered as optional so the block
	// checkout doesn't flag them before submit; checkout.js shows/hides them with
	// the checkbox. The validation hook below makes them required when the box is
	// ticked — EXCEPT the VAT/EIK field, which stays genuinely optional.
	$company_fields = array(
		KASTOR_SHOP_FIELD_INVOICE_COMPANY => 'Име на фирма',
		KASTOR_SHOP_FIELD_INVOICE_MOL     => 'МОЛ',
		KASTOR_SHOP_FIELD_INVOICE_BULSTAT => 'Булстат на фирма',
		KASTOR_SHOP_FIELD_INVOICE_VAT     => 'ИН по ЗДДС или (ЕИК/ЕГН)',
		KASTOR_SHOP_FIELD_INVOICE_ADDRESS => 'Адрес на фирмата',
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

	// The block checkout fires this hook on every draft-order update (PUT/PATCH)
	// while the user edits the form — not only when the order is placed (POST).
	// Without this guard the "company fields are required" errors popped up the
	// instant the "Желая фактура" box was ticked. Mirror WooCommerce core, which
	// skips required-field checks on partial requests, and enforce only on the
	// final place-order POST.
	$method = isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] )
		? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) )
		: ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' );
	if ( in_array( $method, array( 'PUT', 'PATCH' ), true ) ) {
		return;
	}

	// Box not ticked → nothing to require.
	if ( empty( $fields[ KASTOR_SHOP_FIELD_INVOICE_REQUEST ] ) ) {
		return;
	}

	// VAT/EIK is intentionally absent — it stays optional even when ticked.
	$required = array(
		KASTOR_SHOP_FIELD_INVOICE_COMPANY => 'Моля въведете име на фирма за фактурата.',
		KASTOR_SHOP_FIELD_INVOICE_MOL     => 'Моля въведете МОЛ за фактурата.',
		KASTOR_SHOP_FIELD_INVOICE_BULSTAT => 'Моля въведете Булстат на фирмата за фактурата.',
		KASTOR_SHOP_FIELD_INVOICE_ADDRESS => 'Моля въведете адрес на фирмата за фактурата.',
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


/* --------------------------------------------------------------------------
 * 4. Hide the "Фирма" (company) field on the block checkout.
 *    WooCommerce stores this field's visibility in the option
 *    woocommerce_checkout_company_field ('optional' | 'required' | 'hidden').
 *    Forcing it to 'hidden' removes the field from BOTH the checkout form and
 *    the Store API schema (so no empty company value is collected), across the
 *    billing and shipping address forms. We override at the option layer —
 *    the same approach as the privacy-text override in kastor-shop.php — so it
 *    survives without editing the checkout page in the block editor.
 *
 *    To bring the field back, remove these two filters (or return 'optional').
 * -------------------------------------------------------------------------- */

add_filter( 'option_woocommerce_checkout_company_field', 'kastor_shop_hide_company_field', 99 );
add_filter( 'default_option_woocommerce_checkout_company_field', 'kastor_shop_hide_company_field', 99 );

function kastor_shop_hide_company_field( $value ) {
	return 'hidden';
}


/* --------------------------------------------------------------------------
 * 5. "Банков превод" (bank transfer) earns a 2% discount.
 *
 *    The block checkout does NOT recalculate cart totals when the shopper
 *    switches payment method, so a plain woocommerce_cart_calculate_fees hook
 *    would only ever take effect at order-placement time and the live order
 *    summary would never reflect it. WooCommerce's Store API "update callback"
 *    is the supported way around this: checkout.js calls extensionCartUpdate()
 *    on every payment-method change, which (a) runs the callback below to record
 *    the method in the session, and (b) forces a cart recalculation so the
 *    discount line appears / disappears live.
 *
 *    Configurable via constants (define in wp-config or a snippet to override):
 *      KASTOR_SHOP_DISCOUNT_GATEWAY — gateway id that earns the discount.
 *      KASTOR_SHOP_DISCOUNT_RATE    — fraction off the subtotal (0.02 = 2%).
 * -------------------------------------------------------------------------- */

if ( ! defined( 'KASTOR_SHOP_DISCOUNT_GATEWAY' ) ) {
	define( 'KASTOR_SHOP_DISCOUNT_GATEWAY', 'bacs' );
}
if ( ! defined( 'KASTOR_SHOP_DISCOUNT_RATE' ) ) {
	define( 'KASTOR_SHOP_DISCOUNT_RATE', 0.02 );
}

// Session key holding the shopper's currently-selected payment method, kept in
// sync from checkout.js via the Store API update callback below.
const KASTOR_SHOP_SESSION_PAYMENT = 'kastor_chosen_payment_method';

add_action( 'woocommerce_init', 'kastor_shop_register_payment_update_callback' );
function kastor_shop_register_payment_update_callback() {
	if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
		return; // WooCommerce too old / Store API unavailable.
	}

	woocommerce_store_api_register_update_callback( array(
		'namespace' => 'kastor-shop-payment',
		'callback'  => static function ( $data ) {
			if ( ! WC()->session ) {
				return;
			}
			$method = isset( $data['payment_method'] ) ? wc_clean( wp_unslash( $data['payment_method'] ) ) : '';
			WC()->session->set( KASTOR_SHOP_SESSION_PAYMENT, $method );
		},
	) );
}

add_action( 'woocommerce_cart_calculate_fees', 'kastor_shop_payment_method_discount' );
function kastor_shop_payment_method_discount( $cart ) {
	if ( ! WC()->session ) {
		return;
	}

	$method = WC()->session->get( KASTOR_SHOP_SESSION_PAYMENT );
	if ( KASTOR_SHOP_DISCOUNT_GATEWAY !== $method ) {
		return;
	}

	// Base the discount on the products subtotal incl. tax, so it matches the
	// prices the customer sees. Non-taxable fee = a clean flat reduction.
	$base     = (float) $cart->get_subtotal() + (float) $cart->get_subtotal_tax();
	$discount = round( $base * (float) KASTOR_SHOP_DISCOUNT_RATE, 2 );

	if ( $discount > 0 ) {
		$percent = (int) round( (float) KASTOR_SHOP_DISCOUNT_RATE * 100 );
		$cart->add_fee(
			sprintf( 'Отстъпка при банков превод (-%d%%)', $percent ),
			-$discount,
			false
		);
	}
}
