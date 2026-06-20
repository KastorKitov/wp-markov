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

define( 'KASTOR_SHOP_VERSION', '0.16.2' );
define( 'KASTOR_SHOP_URL', plugin_dir_url( __FILE__ ) );
define( 'KASTOR_SHOP_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Slug of the product attribute used by the sidebar "type" filter.
 * Default expects an attribute created in WooCommerce with slug "part-type"
 * (stored internally as `pa_part-type`). To use a different attribute, define
 * KASTOR_SHOP_TYPE_ATTRIBUTE in wp-config.php or another plugin.
 */
if ( ! defined( 'KASTOR_SHOP_TYPE_ATTRIBUTE' ) ) {
	define( 'KASTOR_SHOP_TYPE_ATTRIBUTE', 'pa_part-type' );
}

/**
 * How many products to render per archive page. Because the sidebar filter
 * runs client-side, it can only filter products that are already on the
 * current page — so we bump WooCommerce's default of 20 up to a value that
 * comfortably fits a whole parts catalogue on one page. Increase further if
 * a category ever grows beyond this; switch to an AJAX filter if it grows
 * into the hundreds.
 */
if ( ! defined( 'KASTOR_SHOP_PRODUCTS_PER_PAGE' ) ) {
	define( 'KASTOR_SHOP_PRODUCTS_PER_PAGE', 100 );
}


/* --------------------------------------------------------------------------
 * 1. Enqueue stylesheet on WooCommerce pages only
 * -------------------------------------------------------------------------- */

add_action( 'wp_enqueue_scripts', 'kastor_shop_enqueue_assets', 20 );
function kastor_shop_enqueue_assets() {
	// Poppins from Google Fonts — needed because the archive/category title
	// is styled to match Elementor-built pages, but WC pages don't auto-load
	// Poppins. Loading it here makes the shop title match Услуги/Проекти.
	wp_enqueue_style(
		'kastor-shop-poppins',
		'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
		array(),
		null
	);

	// Enqueue site-wide. The CSS only targets WooCommerce-specific classes
	// (`.woocommerce ul.products`, `.product-category`, etc.) so it's a no-op
	// on pages without those elements. Loading it everywhere is the simplest
	// way to also cover custom pages (Elementor / Kadence / shortcodes) that
	// render WooCommerce grids but where WC's `is_shop()` etc. return false.
	wp_enqueue_style(
		'kastor-shop',
		KASTOR_SHOP_URL . 'shop.css',
		array( 'kastor-shop-poppins' ),
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
 * 2a. Sale badge → show the discount percentage instead of just "SALE".
 *     For variable products, takes the biggest discount across variations.
 * -------------------------------------------------------------------------- */

add_filter( 'woocommerce_sale_flash', 'kastor_shop_sale_percentage_badge', 10, 3 );
function kastor_shop_sale_percentage_badge( $html, $post, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $html;
	}

	$percent = 0;

	if ( $product->is_type( 'variable' ) ) {
		// Walk the variations and use the largest discount.
		foreach ( $product->get_children() as $child_id ) {
			$child = wc_get_product( $child_id );
			if ( ! $child || ! $child->is_on_sale() ) {
				continue;
			}
			$regular = (float) $child->get_regular_price();
			$sale    = (float) $child->get_sale_price();
			if ( $regular > 0 && $sale >= 0 && $regular > $sale ) {
				$p = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
				if ( $p > $percent ) {
					$percent = $p;
				}
			}
		}
	} else {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale >= 0 && $regular > $sale ) {
			$percent = (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
		}
	}

	if ( $percent <= 0 ) {
		// Couldn't compute (e.g. price-on-request); fall back to the default badge.
		return $html;
	}

	return '<span class="onsale">-' . $percent . '%</span>';
}


/* --------------------------------------------------------------------------
 * 2b. Show more products per page so the client-side filter sees them all.
 * -------------------------------------------------------------------------- */

add_filter( 'loop_shop_per_page', 'kastor_shop_per_page', 20 );
function kastor_shop_per_page( $cols ) {
	return (int) KASTOR_SHOP_PRODUCTS_PER_PAGE;
}


/* --------------------------------------------------------------------------
 * 2c. Swap the shop-loop "Read more" / "Още" button to "Детайли" for
 *     products that can't be added directly to cart (no price set,
 *     external/grouped, variable without a default variation, etc.).
 *     Uses WC's dedicated filter so we don't touch any other "Read more"
 *     text elsewhere on the site (blog excerpts, etc.).
 * -------------------------------------------------------------------------- */

add_filter( 'woocommerce_product_add_to_cart_text', 'kastor_shop_read_more_label', 20, 2 );
function kastor_shop_read_more_label( $text, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $text;
	}
	// Only swap when WC is rendering the "no purchase possible" CTA — i.e.
	// the original text is the localized "Read more". Compare against both
	// English source and the Bulgarian translation already in use.
	$swap = array( 'Read more', 'Още', 'Прочети повече', 'Виж повече' );
	if ( in_array( $text, $swap, true ) ) {
		return 'Виж повече';
	}
	return $text;
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


/* --------------------------------------------------------------------------
 * 5. Sidebar with price + type filters
 *    Rendered before the products grid; shop.js then moves it into a
 *    left-column layout (DOM rewrap in JS, since WooCommerce templates don't
 *    give us a stable wrapper we can split server-side).
 * -------------------------------------------------------------------------- */

add_action( 'woocommerce_before_shop_loop', 'kastor_shop_render_sidebar', 20 );
function kastor_shop_render_sidebar() {
	if ( ! is_shop() && ! is_product_category() && ! is_product_tag() ) {
		return;
	}
	if ( function_exists( 'woocommerce_get_loop_display_mode' ) ) {
		$display = woocommerce_get_loop_display_mode();
		if ( 'subcategories' === $display ) {
			return;
		}
	}

	$type_terms = array();
	if ( taxonomy_exists( KASTOR_SHOP_TYPE_ATTRIBUTE ) ) {
		$fetched = get_terms( array(
			'taxonomy'   => KASTOR_SHOP_TYPE_ATTRIBUTE,
			'hide_empty' => true,
		) );
		if ( ! is_wp_error( $fetched ) ) {
			$type_terms = $fetched;
		}
	}
	?>
	<aside class="kastor-shop__sidebar" data-kastor-shop-sidebar aria-label="Филтри">

		<div class="kastor-shop__filter-group">
			<h3 class="kastor-shop__filter-title">Цена</h3>
			<div class="kastor-shop__price-inputs">
				<label>
					<span>От</span>
					<input type="number" min="0" step="1" placeholder="0" inputmode="numeric" data-kastor-shop-price-min />
				</label>
				<label>
					<span>До</span>
					<input type="number" min="0" step="1" placeholder="∞" inputmode="numeric" data-kastor-shop-price-max />
				</label>
			</div>
		</div>

		<?php if ( ! empty( $type_terms ) ) : ?>
			<div class="kastor-shop__filter-group">
				<h3 class="kastor-shop__filter-title">Тип част</h3>
				<ul class="kastor-shop__filter-list">
					<?php foreach ( $type_terms as $term ) : ?>
						<li>
							<label>
								<input type="checkbox" value="<?php echo esc_attr( $term->slug ); ?>" data-kastor-shop-type />
								<span><?php echo esc_html( $term->name ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<button type="button" class="kastor-shop__filter-reset" data-kastor-shop-filter-reset>Изчисти филтрите</button>
	</aside>
	<?php
}


/* --------------------------------------------------------------------------
 * 6. Inject price + type slugs as hidden meta on each product card so JS can
 *    filter without an extra round-trip.
 * -------------------------------------------------------------------------- */

add_action( 'woocommerce_after_shop_loop_item', 'kastor_shop_inject_filter_meta', 5 );
function kastor_shop_inject_filter_meta() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}

	$price = $product->get_price();
	$types = array();
	if ( taxonomy_exists( KASTOR_SHOP_TYPE_ATTRIBUTE ) ) {
		$fetched = wp_get_post_terms( $product->get_id(), KASTOR_SHOP_TYPE_ATTRIBUTE, array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $fetched ) ) {
			$types = $fetched;
		}
	}

	printf(
		'<span class="kastor-shop-product-meta" data-kastor-shop-product-meta data-price="%s" data-types="%s" hidden></span>',
		esc_attr( $price !== '' ? (string) $price : '' ),
		esc_attr( implode( ',', (array) $types ) )
	);
}


/* --------------------------------------------------------------------------
 * 6a. Show a BGN equivalent next to every EUR price.
 *     1 EUR = 1.95583 BGN (the fixed rate from the BNB peg).
 *     Renders e.g. "20,00 €  (39,12 лв)" — the BGN bit in light gray.
 *     The suffix is "лв" for Bulgarian site, "BGN" otherwise.
 * -------------------------------------------------------------------------- */

if ( ! defined( 'KASTOR_SHOP_EUR_TO_BGN_RATE' ) ) {
	define( 'KASTOR_SHOP_EUR_TO_BGN_RATE', 1.95583 );
}

add_filter( 'woocommerce_get_price_html', 'kastor_shop_append_bgn_price', 100, 2 );
function kastor_shop_append_bgn_price( $price_html, $product ) {
	if ( ! $product instanceof WC_Product ) {
		return $price_html;
	}

	// Avoid stacking: if we've already appended a BGN block, bail.
	if ( strpos( $price_html, 'kastor-shop__price-bgn' ) !== false ) {
		return $price_html;
	}

	// Sale prices render as "<del>30€</del><ins>20€</ins>" — two distinct
	// price elements. Appending a single trailing BGN here produces a third,
	// orphaned amount. Let the client-side appender handle each <del>/<ins>
	// individually for those cases.
	if ( strpos( $price_html, '<del' ) !== false || strpos( $price_html, '<ins' ) !== false ) {
		return $price_html;
	}

	$price = $product->get_price();
	if ( $price === '' || ! is_numeric( $price ) || (float) $price <= 0 ) {
		return $price_html;
	}

	$bgn        = (float) $price * (float) KASTOR_SHOP_EUR_TO_BGN_RATE;
	$bgn_amount = number_format_i18n( $bgn, 2 );

	$locale = get_locale();
	$suffix = ( strpos( strtolower( $locale ), 'bg' ) === 0 ) ? 'лв' : 'BGN';

	$bgn_block = sprintf(
		'<span class="kastor-shop__price-bgn"> (<span class="kastor-shop__price-bgn-amount">%s</span> <span class="kastor-shop__price-bgn-suffix">%s</span>)</span>',
		esc_html( $bgn_amount ),
		esc_html( $suffix )
	);

	return $price_html . $bgn_block;
}


/* --------------------------------------------------------------------------
 * 6b. Patch the few English strings that WooCommerce's Cart Block leaves
 *     untranslated even when the site is in Bulgarian. These strings come
 *     from JS bundles whose .json files don't ship Bulgarian, so we catch
 *     them via gettext on the server side instead.
 * -------------------------------------------------------------------------- */

add_filter( 'gettext', 'kastor_shop_translate_cart_strings', 999, 3 );
add_filter( 'gettext_with_context', 'kastor_shop_translate_cart_strings_ctx', 999, 4 );

function kastor_shop_translate_cart_strings_map() {
	return array(
		'Add coupons'                  => 'Добави купон',
		'Add a coupon'                 => 'Добави купон',
		'Enter code'                   => 'Въведи код',
		'Apply'                        => 'Приложи',
		'Free shipping'                => 'Безплатна доставка',
		'Estimated total'              => 'Очаквана сума',
		'Proceed to Checkout'          => 'Към плащане',
		'Proceed to checkout'          => 'Към плащане',
		'Subtotal'                     => 'Междинна сума',
		'Total'                        => 'Общо',
		'Shipping'                     => 'Доставка',
		'Discount'                     => 'Отстъпка',
		'Coupon code'                  => 'Код за отстъпка',
		'Remove item'                  => 'Премахни',

		// My Account / register form
		'A link to set a new password will be sent to your email address.' =>
			'Линк за задаване на нова парола ще бъде изпратен на вашия имейл адрес.',
		'Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our %s.' =>
			'Вашите лични данни ще бъдат използвани за подобряване на вашето изживяване в този уебсайт, за управление на достъпа до акаунта ви и за други цели, описани в нашата %s.',
		'privacy policy'               => 'политика на поверителност',
		'Register'                     => 'Регистриране',
		'Log in'                       => 'Влизане',
		'Login'                        => 'Влизане',
		'Username or email address'    => 'Потребителско име или имейл адрес',
		'Email address'                => 'Имейл адрес',
		'Password'                     => 'Парола',
		'Remember me'                  => 'Запомняне',
		'Lost your password?'          => 'Изгубена парола?',

		// My Account navigation tabs (right column on dashboard)
		'Dashboard'                    => 'Табло',
		'Orders'                       => 'Поръчки',
		'Downloads'                    => 'Сваляния',
		'Addresses'                    => 'Адреси',
		'Address'                      => 'Адрес',
		'Payment methods'              => 'Методи на плащане',
		'Account details'              => 'Данни на акаунта',
		'Wishlist'                     => 'Желани',
		'Log out'                      => 'Изход',
		'Logout'                       => 'Изход',

		// Orders sub-page
		'No order has been made yet.'  => 'Все още не е направена поръчка.',
		'Browse products'              => 'Преглед на продукти',
		'Order'                        => 'Поръчка',
		'Date'                         => 'Дата',
		'Status'                       => 'Статус',
		'Actions'                      => 'Действия',
		'View'                         => 'Преглед',
		'Pay'                          => 'Плати',
		'Cancel'                       => 'Отказ',

		// Addresses sub-page
		'The following addresses will be used on the checkout page by default.' =>
			'Следните адреси ще се използват по подразбиране на страницата за плащане.',
		'Billing address'              => 'Адрес за фактуриране',
		'Shipping address'             => 'Адрес за доставка',
		'You have not set up this type of address yet.' =>
			'Все още не сте задали този тип адрес.',
		'Add'                          => 'Добави',
		'Edit'                         => 'Редактирай',

		// Account details sub-page
		'First name'                   => 'Име',
		'Last name'                    => 'Фамилия',
		'Display name'                 => 'Показвано име',
		'This will be how your name will be displayed in the account section and in reviews' =>
			'Така ще се показва вашето име в секцията с акаунта и в коментарите',
		'Password change'              => 'Смяна на парола',
		'Current password (leave blank to leave unchanged)' =>
			'Текуща парола (оставете празно, ако не искате да се променя)',
		'New password (leave blank to leave unchanged)' =>
			'Нова парола (оставете празно, ако не искате да се променя)',
		'Confirm new password'         => 'Потвърдете новата парола',
		'Save changes'                 => 'Запази промените',

		// Downloads sub-page
		'No downloads available yet.'  => 'Все още няма налични сваляния.',

		// WooCommerce emails — customer-new-account.php template.
		// Cover both straight (') and curly (’ U+2019) apostrophe variants
		// because WP's wptexturize may convert one to the other depending
		// on the WC version and context.
		'Thanks for creating an account on %1$s. Here\'s a copy of your user details.' =>
			'Благодарим ви за регистрацията на %1$s. Ето копие на вашите потребителски данни.',
		'Thanks for creating an account on %s. Here\'s a copy of your user details.' =>
			'Благодарим ви за регистрацията на %s. Ето копие на вашите потребителски данни.',
		'Thanks for creating an account on %1$s. Here’s a copy of your user details.' =>
			'Благодарим ви за регистрацията на %1$s. Ето копие на вашите потребителски данни.',
		'Thanks for creating an account on %s. Here’s a copy of your user details.' =>
			'Благодарим ви за регистрацията на %s. Ето копие на вашите потребителски данни.',
		'You can access your account area to view orders, change your password, and more via the link below:' =>
			'Можете да достъпите своя профил, за да преглеждате поръчки, да смените паролата си и още, чрез линка по-долу:',

		// Generic email footer / common phrases
		'Username: %s'                 => 'Потребителско име: %s',
		'Set your new password.'       => 'Настройте новата си парола.',
		'My account'                   => 'Моят профил',
		'Thanks for shopping with us.' => 'Благодарим ви, че пазарувате при нас.',
		'We look forward to seeing you soon.' => 'Ще се радваме скоро да се видим отново.',
		'Hi %s,'                       => 'Здравейте, %s,',

		// Order emails
		'Your %1$s order has been received!' =>
			'Вашата поръчка %1$s беше получена!',
		'Your %s order has been received!' =>
			'Вашата поръчка %s беше получена!',
		'Just to let you know — we\'ve received your order #%s, and it is now being processed:' =>
			'Само да ви уведомим — получихме вашата поръчка #%s и тя вече се обработва:',
		'Your order has been received and is now being processed. Your order details are shown below for your reference:' =>
			'Вашата поръчка беше получена и вече се обработва. Данните на поръчката са показани по-долу за справка:',
		'Just to let you know — your payment has been confirmed, and order #%s is now being processed:' =>
			'Само да ви уведомим — плащането ви е потвърдено и поръчка #%s вече се обработва:',
		'Hi there. Your recent order on %s has been completed.' =>
			'Здравейте. Вашата скорошна поръчка в %s беше изпълнена.',
		'Your order is now complete.' => 'Вашата поръчка е изпълнена.',
		'Order details'                => 'Данни на поръчката',
		'Customer details'             => 'Данни на клиента',
		'Email'                        => 'Имейл',
		'Tel:'                         => 'Тел.:',
		'Note:'                        => 'Бележка:',

		// "Reminder of what you've bought" line (customer downloads / processing order)
		'Here\'s a reminder of what you\'ve bought:' =>
			'Ето напомняне за това, което сте поръчали:',
		'Here’s a reminder of what you’ve bought:' =>
			'Ето напомняне за това, което сте поръчали:',

		// WooCommerce Subscriptions — payment retry / authorize renewal
		'The automatic payment to renew your subscription with %s has failed. To reactivate the subscription, please login and authorize the renewal from your account page: %s' =>
			'Автоматичното плащане за подновяване на абонамента ви в %s не успя. За да активирате абонамента отново, моля влезте и оторизирайте подновяването от страницата на профила ви: %s',
		'The automatic payment to renew your subscription with %1$s has failed. To reactivate the subscription, please login and authorize the renewal from your account page: %2$s' =>
			'Автоматичното плащане за подновяване на абонамента ви в %1$s не успя. За да активирате абонамента отново, моля влезте и оторизирайте подновяването от страницата на профила ви: %2$s',
		'Authorize the payment »'      => 'Оторизирай плащането »',
		'Authorize the payment &raquo;' => 'Оторизирай плащането »',

		// WooCommerce Pre-Orders extension
		'Your pre-order is now available, but payment cannot be completed automatically.' =>
			'Вашата предварителна поръчка вече е достъпна, но плащането не може да бъде завършено автоматично.',
		'Authorize the payment now »'  => 'Оторизирай плащането сега »',
		'Authorize the payment now &raquo;' => 'Оторизирай плащането сега »',

		// Cancelled-order email (both customer + admin versions, straight/curly apostrophe)
		'We\'re getting in touch to let you know that your order #%s has been cancelled.' =>
			'Свързваме се с вас, за да ви уведомим, че поръчката ви #%s беше отказана.',
		'We’re getting in touch to let you know that your order #%s has been cancelled.' =>
			'Свързваме се с вас, за да ви уведомим, че поръчката ви #%s беше отказана.',
		'We\'re getting in touch to let you know that order #%1$s has been cancelled.' =>
			'Свързваме се с вас, за да ви уведомим, че поръчка #%1$s беше отказана.',
		'We’re getting in touch to let you know that order #%1$s has been cancelled.' =>
			'Свързваме се с вас, за да ви уведомим, че поръчка #%1$s беше отказана.',
		'Notification to let you know — order #%1$s belonging to %2$s has been cancelled:' =>
			'Уведомление — поръчка #%1$s, принадлежаща на %2$s, беше отказана:',
	);
}

function kastor_shop_translate_cart_strings( $translated, $original, $domain ) {
	$map = kastor_shop_translate_cart_strings_map();
	if ( isset( $map[ $original ] ) ) {
		return $map[ $original ];
	}
	return $translated;
}

function kastor_shop_translate_cart_strings_ctx( $translated, $original, $context, $domain ) {
	$map = kastor_shop_translate_cart_strings_map();
	if ( isset( $map[ $original ] ) ) {
		return $map[ $original ];
	}
	return $translated;
}

/* --------------------------------------------------------------------------
 * 6c. WooCommerce privacy-policy snippets on register/checkout.
 *     These strings live in wp_options (woocommerce_registration_privacy_
 *     policy_text and woocommerce_checkout_privacy_policy_text). WC uses
 *     get_option() to fetch them, so intercepting at the WP option layer
 *     overrides both the stored DB value AND the gettext fallback in one
 *     filter. Use [privacy_policy] as the link placeholder — WC's
 *     wc_replace_policy_page_link_placeholders() swaps it for the actual
 *     privacy-page link automatically.
 * -------------------------------------------------------------------------- */

define( 'KASTOR_SHOP_PRIVACY_REGISTER',
	'Вашите лични данни ще бъдат използвани за подобряване на вашето изживяване в този уебсайт, за управление на достъпа до акаунта ви и за други цели, описани в нашата [privacy_policy].' );

define( 'KASTOR_SHOP_PRIVACY_CHECKOUT',
	'Вашите лични данни ще бъдат използвани за обработка на поръчката ви, за подобряване на изживяването ви в този уебсайт и за други цели, описани в нашата [privacy_policy].' );

// Override the stored option value (runs whenever WC calls get_option()).
add_filter( 'option_woocommerce_registration_privacy_policy_text', 'kastor_shop_privacy_text_register', 99 );
add_filter( 'default_option_woocommerce_registration_privacy_policy_text', 'kastor_shop_privacy_text_register', 99 );

function kastor_shop_privacy_text_register( $value ) {
	return KASTOR_SHOP_PRIVACY_REGISTER;
}

add_filter( 'option_woocommerce_checkout_privacy_policy_text', 'kastor_shop_privacy_text_checkout', 99 );
add_filter( 'default_option_woocommerce_checkout_privacy_policy_text', 'kastor_shop_privacy_text_checkout', 99 );

function kastor_shop_privacy_text_checkout( $value ) {
	return KASTOR_SHOP_PRIVACY_CHECKOUT;
}

// Belt-and-suspenders: also catch the WC-level filter in case a future
// version moves these strings out of options entirely.
add_filter( 'woocommerce_get_privacy_policy_text', 'kastor_shop_privacy_text_wc_filter', 10, 2 );
function kastor_shop_privacy_text_wc_filter( $text, $type ) {
	if ( $type === 'registration' ) {
		return KASTOR_SHOP_PRIVACY_REGISTER;
	}
	if ( $type === 'checkout' ) {
		return KASTOR_SHOP_PRIVACY_CHECKOUT;
	}
	return $text;
}


/* --------------------------------------------------------------------------
 * 7. Single-product page extras
 *    - "Купи сега" button (adds + redirects to checkout)
 *    - Delivery info panel (free shipping, lead time)
 * -------------------------------------------------------------------------- */

add_action( 'woocommerce_after_add_to_cart_button', 'kastor_shop_buy_now_button', 20 );
function kastor_shop_buy_now_button() {
	global $product;
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}
	?>
	<button
		type="button"
		class="button kastor-shop__buy-now"
		data-kastor-shop-buy-now
		data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
	>
		Купи сега
	</button>
	<?php
}


add_action( 'woocommerce_after_add_to_cart_form', 'kastor_shop_delivery_info', 20 );
function kastor_shop_delivery_info() {
	?>
	<div class="kastor-shop__delivery">
		<div class="kastor-shop__delivery-item">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<rect x="1" y="3" width="15" height="13"></rect>
				<polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
				<circle cx="5.5" cy="18.5" r="2.5"></circle>
				<circle cx="18.5" cy="18.5" r="2.5"></circle>
			</svg>
			<div>
				<strong>Бърза доставка</strong>
				<span>1–3 работни дни в цяла България</span>
			</div>
		</div>
		<div class="kastor-shop__delivery-item">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"></path>
				<circle cx="12" cy="10" r="3"></circle>
			</svg>
			<div>
				<strong>Лично взимане</strong>
				<span>Безплатно от склад в Ягодово, обл. Пловдив</span>
			</div>
		</div>
		<div class="kastor-shop__delivery-item">
			<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
				<path d="M9 12l2 2 4-4"></path>
				<circle cx="12" cy="12" r="10"></circle>
			</svg>
			<div>
				<strong>Гаранция за качество</strong>
				<span>Сертифицирани части и резервни елементи</span>
			</div>
		</div>
	</div>
	<?php
}
