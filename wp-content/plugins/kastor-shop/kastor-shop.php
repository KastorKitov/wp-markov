<?php
/**
 * Plugin Name: Kastor Shop
 * Description: Visual styling for WooCommerce pages (shop / category list / single product) to match the Kastor brand. Does NOT replace WooCommerce functionality — only adds CSS and a small intro-text hook.
 * Version: 0.1.0
 * Author: Kastor
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KASTOR_SHOP_VERSION', '0.2.0' );
define( 'KASTOR_SHOP_URL', plugin_dir_url( __FILE__ ) );
define( 'KASTOR_SHOP_PATH', plugin_dir_path( __FILE__ ) );


/* --------------------------------------------------------------------------
 * 1. Enqueue stylesheet on WooCommerce pages only
 * -------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'kastor_shop_enqueue_assets', 20 );
function kastor_shop_enqueue_assets() {
	// Enqueue site-wide. The CSS only targets WooCommerce-specific classes
	// (`.woocommerce ul.products`, `.product-category`, etc.) so it's a no-op
	// on pages without those elements. Loading it everywhere is the simplest
	// way to also cover custom pages (Elementor / Kadence / shortcodes) that
	// render WooCommerce grids but where WC's `is_shop()` etc. return false.
	wp_enqueue_style(
		'kastor-shop',
		KASTOR_SHOP_URL . 'shop.css',
		array(),
		KASTOR_SHOP_VERSION
	);
	wp_enqueue_script(
		'kastor-shop',
		KASTOR_SHOP_URL . 'shop.js',
		array(),
		KASTOR_SHOP_VERSION,
		true
	);
}


/* --------------------------------------------------------------------------
 * 2. Strip the "( )" around the category count.
 *    WooCommerce wraps the number in parentheses by default; we render a
 *    naked number so the badge looks cleaner.
 * -------------------------------------------------------------------------- */

add_filter( 'woocommerce_subcategory_count_html', 'kastor_shop_strip_count_parens', 10, 2 );
function kastor_shop_strip_count_parens( $html, $category ) {
	return '<mark class="count">' . esc_html( number_format_i18n( $category->count ) ) . '</mark>';
}


/* --------------------------------------------------------------------------
 * 3. Intro line above the category grid
 *    Filterable, so a snippet can override the text without editing the plugin.
 * -------------------------------------------------------------------------- */

add_action( 'woocommerce_archive_description', 'kastor_shop_render_category_intro', 5 );
function kastor_shop_render_category_intro() {
	if ( ! is_shop() && ! is_product_category() ) {
		return;
	}

	// Only render when the current page lists subcategories (the "pick a
	// machine" view). On pages that list products, suppress.
	if ( function_exists( 'woocommerce_get_loop_display_mode' ) ) {
		$display = woocommerce_get_loop_display_mode();
		if ( 'products' === $display ) {
			return;
		}
	}

	$text = apply_filters(
		'kastor_shop_category_intro_text',
		'Изберете машина, за която са ви нужни части'
	);

	if ( $text === '' ) {
		return;
	}

	echo '<p class="kastor-shop__intro">' . esc_html( $text ) . '</p>';
}


/* --------------------------------------------------------------------------
 * 4. Live filter input above the products grid
 *    Renders only on pages that actually list products (not subcategory
 *    pages). The filter is handled client-side in shop.js.
 * -------------------------------------------------------------------------- */

add_action( 'woocommerce_before_shop_loop', 'kastor_shop_render_filter_input', 25 );
function kastor_shop_render_filter_input() {
	if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
		return;
	}

	if ( function_exists( 'woocommerce_get_loop_display_mode' ) ) {
		$display = woocommerce_get_loop_display_mode();
		if ( 'subcategories' === $display ) {
			return; // No products on this page to filter.
		}
	}

	$placeholder = apply_filters(
		'kastor_shop_filter_placeholder',
		'Търси част по име...'
	);
	?>
	<div class="kastor-shop__filter" data-kastor-shop-filter>
		<label for="kastor-shop-filter" class="screen-reader-text"><?php echo esc_html( $placeholder ); ?></label>
		<svg class="kastor-shop__filter-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
			<circle cx="11" cy="11" r="7"/>
			<line x1="21" y1="21" x2="16.65" y2="16.65"/>
		</svg>
		<input
			id="kastor-shop-filter"
			class="kastor-shop__filter-input"
			type="search"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			autocomplete="off"
			data-kastor-shop-filter-input
		/>
		<p class="kastor-shop__filter-empty" data-kastor-shop-filter-empty hidden>Няма съвпадения.</p>
	</div>
	<?php
}
