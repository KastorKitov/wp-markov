# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A local copy of a WordPress 7.0 site originally hosted on **InstaWP**. The user downloaded the site files so Claude can read/edit them locally. The user manually uploads changed files back to the live InstaWP server.

- **Git repository, but scoped narrowly.** The repo only tracks Kastor custom code (the `kastor-machines` and `kastor-shop` plugins, child themes, `CLAUDE.md`/`README.md`). `.gitignore` ignores everything else by default — including WordPress core, third-party plugins, the Blocksy theme, uploads, and `wp-config.php`. When adding a new custom plugin or child theme, allowlist its path in `.gitignore` or it won't be tracked. The user still uploads changed files to InstaWP manually — git is for history/rollback, not deployment.
- **No build pipeline at the project root.** No `package.json`, no test runner, no linter. There are no project-level commands to run.
- **The site is a WooCommerce store** with a large ad/analytics/payments plugin footprint (Facebook, Snapchat, Reddit, Google Listings & Ads, Klaviyo, WooCommerce Payments, PayPal).

## Workflow

1. User describes a change.
2. Edit files in this directory.
3. Tell the user exactly which files were touched (full paths) so they can upload them to the InstaWP server.

## Where custom code lives

Custom site code is NOT in `mu-plugins/` (the directory does not exist) or in a child theme (there is no Blocksy child theme present). Look in:

- **`wp-content/plugins/kastor-machines/`** — custom plugin authored by Claude. Registers machine-catalog CPTs and renders them with a styled single template. See section below.
- **`wp-content/plugins/kastor-shop/`** — custom plugin authored by Claude. Brand styling + UX layer for the WooCommerce shop / category / single-product / cart / checkout pages. It only adds CSS, JS, and hooks — it never replaces WooCommerce functionality. See section below.
- `wpcodeboxide/` — this is the **WPCodeBox IDE** plugin's runtime, not custom code itself. It's a standalone PHP app (uses `SHORTINIT`) that the user runs through the WordPress admin to author code snippets. Snippets created via WPCodeBox are stored in the database, not as files here, so changes made through WPCodeBox in the live admin will not appear in this local copy.
- The active theme is **Blocksy 2.1.41** at `wp-content/themes/blocksy/`. There is no child theme, so direct theme edits will be lost when Blocksy updates — prefer a child theme, a WPCodeBox snippet, or extending the `kastor-machines` plugin / writing a new plugin for new functionality. Confirm with the user before editing theme files directly.

## The `kastor-machines` plugin

A small custom plugin that powers the machine catalogue. Things that aren't obvious from glancing at the files:

- **Config-driven CPT registration.** All managed post types live in the `kastor_machines_types()` array at the top of `kastor-machines.php`. To add a new machine category, add one entry — registration, metabox, save handler, template loading, and asset enqueueing all key off that list automatically. Don't duplicate logic per CPT.
- **Internal post_type slugs are capped at 20 chars** by WordPress. Existing keys use underscores (`nova_mashina`, `treatment_machine`, etc.). Match that convention when adding more.
- **Four independent metaboxes on each machine post:** `Акценти` (highlights — 2×N stat cards next to the image), `Параметри (един модел)` (simple key/value table), `Сравнителна таблица (няколко модела)` (multi-model grid with grouped rows + footnote), and `Галерия` (extra carousel images). Each owns its own meta key and save handler. The template prefers the comparison table over the simple params — a post with both only renders the comparison.
- **Post-meta keys (use the constants, not the literal strings):**
  - `_kastor_machine_highlights` (`KASTOR_MACHINES_HIGHLIGHTS_META`) — `array<{value, label}>` for the highlight cards, read via `kastor_machines_get_highlights()`.
  - `_kastor_machine_params` (`KASTOR_MACHINES_PARAMS_META`) — `array<{label, value}>`, read via `kastor_machines_get_params()`.
  - `_kastor_machine_gallery` (`KASTOR_MACHINES_GALLERY_META`) — `int[]` of attachment IDs in display order, read via `kastor_machines_get_gallery()`. Carousel's full image list (featured + gallery, de-duplicated) is `kastor_machines_get_carousel_image_ids()`.
  - `_kastor_machine_models` / `_kastor_machine_specs` / `_kastor_machine_specs_note` — comparison-table pieces, read together via `kastor_machines_get_comparison_table()` returning `{models[], rows[], note}`. Each row is `{group, label, unit, values[]}` with `values[i]` aligned to `models[i]`. Consecutive rows where `group` is empty (or equals the previous row's `group`) get a rowspanned group cell on the frontend.
- **Comparison-table input UX is a stateful mini-app in `specs.js`.** It owns a JS state object and serialises it to a single hidden `<input name="kastor_specs_json">` on every change; the PHP save handler `json_decode`s and sanitises. Text typing updates state in place without re-rendering (preserves input focus); add/remove model or row triggers a full re-render. If you add new fields to a row, update *both* the JS shape *and* the PHP sanitiser, otherwise data silently drops on save.
- **Smart-stack frontend layout.** The single-machine template renders a 50/50 hero with `image | body` where the body contains description → highlights grid → simple params (in that order). The *comparison table* is rendered OUTSIDE the hero, full-width below it, because wide multi-column tables never fit in the 50% right column. When the body would be empty (e.g. multi-model machine with no description/highlights), a `:has()` CSS selector collapses the hero to a single centered column with `max-width: 720px`. Modern browsers only (Chrome 105+, Safari 15.4+, Firefox 121+).
- **Frontend carousel uses Swiper.js loaded from jsDelivr CDN** (`KASTOR_MACHINES_SWIPER_*` constants). Not vendored — if the CDN is unreachable, `carousel.js` silently no-ops and slides render as a plain stack. Swap the CDN URLs for locally-hosted files if reliability matters more than payload size.
- **The lightbox is a custom component**, not a library. `lightbox.js` auto-attaches to anything matching `[data-kastor-lightbox]`, scopes the image list to the nearest ancestor `[data-kastor-carousel]`, and handles keyboard (Esc, ←, →) + click-outside-to-close. To make a new image clickable-to-enlarge, wrap it like `kastor_machines_render_lightbox_image()` does (anchor with `href=full-size-url`).
- **Brand colors are CSS custom properties** at the top of `frontend.css` (`--kastor-c-primary` `#38B6FF`, `--kastor-c-dark` `#216192`, `--kastor-c-darker` `#175585`, `--kastor-c-bg-light` `#F5F5F5`). Change them there; don't hardcode hex values further down the file.
- **`Свържете се с нас` CTA URL is filterable** via `kastor_machines_inquiry_url`. Default points to `/kontakti/?machine=<title>&machine_id=<id>` so the contact form can pre-fill. Override the path or query shape from a snippet without touching the plugin.
- **Related-machines strip is a `WP_Query` with `orderby => rand`** hardcoded to 4 results from the same CPT, inline in `single-machine.php`. Edit there for fixed order, larger count, or to skip drafts.
- **One shared single template** (`single-machine.php`) renders every machine CPT, loaded via the `single_template` filter. There are no per-CPT `single-{slug}.php` files and you don't need to create them.
- **Permalinks flush is one-time per activation.** If you add a CPT to the config of an already-active plugin, the new URL slug won't work until the user re-saves **Settings → Permalinks** (or you bump the plugin version and re-activate). Always remind the user after adding CPTs.
- **CSS class prefix is `.kastor-machine__*`** (BEM). The frontend stylesheet is theme-independent — if the user reports styling that clashes with Blocksy, edit `frontend.css` rather than reaching into the theme.

## The `kastor-shop` plugin

A presentation/UX companion to WooCommerce. Two entry files: `kastor-shop.php` (shop/archive/single-product styling, price + translation hooks) which `require`s `checkout.php` (block-checkout invoice fields). Things that aren't obvious from the files:

- **`KASTOR_SHOP_VERSION` in `kastor-shop.php` is the asset cache-buster** — bump it whenever you change any CSS/JS or the live site keeps serving stale files. (The plugin-header `Version:` line is stale at `0.1.0`; the constant is the real version.)
- **`shop.css` / `shop.js` are enqueued site-wide**, not just on shop pages. The CSS only targets WooCommerce classes so it's a no-op elsewhere; loading everywhere also covers Elementor/Kadence pages that render WC grids where `is_shop()` returns false. `checkout.css` / `checkout.js` load on `is_checkout()` only.
- **The shop filter (search / price / type) is 100% client-side** (`shop.js`). It can only filter products already in the DOM, so `loop_shop_per_page` is forced to `KASTOR_SHOP_PRODUCTS_PER_PAGE` (100). If a category grows into the hundreds, switch to an AJAX filter rather than raising this number. The "type" facet reads the `pa_part-type` product attribute (`KASTOR_SHOP_TYPE_ATTRIBUTE`, overridable); PHP injects each card's price + type slugs as a hidden `[data-kastor-shop-product-meta]` span so JS filters without a round-trip. The sidebar is rendered server-side then **rewrapped into a flex left-column by JS** (`buildSidebarLayout`) because WC templates expose no stable wrapper to split server-side.
- **BGN price display is split across PHP and JS — change both.** `kastor_shop_append_bgn_price` (PHP) appends "(… лв)" after simple EUR prices at `KASTOR_SHOP_EUR_TO_BGN_RATE` (1.95583, the fixed BNB peg). It deliberately **skips sale prices** (`<del>`/`<ins>`) and cart/checkout totals; those are handled client-side by `appendBgnToPrices` in `shop.js` (with a MutationObserver for the React-rendered cart).
- **Bulgarian translations also live in two layers.** The big English→Bulgarian map in `kastor_shop_translate_cart_strings_map()` (PHP `gettext` / `gettext_with_context`, priority 999) patches Cart-Block, My-Account, and WooCommerce email strings that ship untranslated; `translateCartBlockStrings()` in `shop.js` does the same for strings that only exist in client-rendered JSON bundles. Add a missing string to the server map first; only use the JS layer if the string never passes through `gettext`.
- **Privacy-policy text is overridden at the option layer** (`option_woocommerce_*_privacy_policy_text` + a WC-level fallback filter), using `[privacy_policy]` as the link placeholder WC swaps automatically. Edit the `KASTOR_SHOP_PRIVACY_*` constants.
- **Sale badge shows the discount percentage (`-NN%`)** instead of "SALE" via `woocommerce_sale_flash` (variable products use the largest variation discount); `shop.js` also rewrites badges rendered client-side. The category count loses its parentheses, and the non-purchasable "Read more" CTA becomes "Виж повече".
- **Single-product page adds a "Купи сега" button + a delivery-info panel** (fast delivery, free pickup in Ягодово, quality guarantee). The buy-now click (add to cart → redirect to checkout) is wired in `shop.js`.
- **Checkout invoice fields use WooCommerce's Additional Checkout Fields API** (WC 8.9+), registered on `woocommerce_init` in `checkout.php`: a "Желая фактура" checkbox plus Име на фирма / МОЛ / ИДН номер. They render in the **`order`** ("Additional information") area by default (`KASTOR_SHOP_INVOICE_LOCATION`) — don't switch to `address` unless you accept WC duplicating them across billing + shipping. Conditional **required** is enforced server-side (`woocommerce_blocks_validate_location_order_fields`); the show/hide is cosmetic, done in `checkout.js` via a `.kastor-invoice-on` body class re-applied on every React re-render (MutationObserver). Fields are matched by **id substring** (`invoice-company`, etc.), not the full id, to survive WC prefix changes — keep those substrings in sync between the `checkout.php` field ids and `checkout.js`.
- **Heads-up — a second, independent checkout mechanism lives in `shop.js`.** `setupCheckoutCustomerType()` shows/hides `.kastor-shop__company-field` rows from a `billing_customer_type` radio (Физическо лице / Юридическо лице) and treats `billing_vat` as optional. Those fields are **not** registered by this plugin (they aren't in `checkout.php`) — they must be defined elsewhere (a snippet, another plugin, or a classic-checkout setup). If you work on checkout, treat the `checkout.php`/`checkout.js` invoice system and this `shop.js` customer-type toggle as two separate things that may overlap.
- **CSS class prefix is `.kastor-shop__*`** (BEM). Brand colors and layout live in `shop.css` / `checkout.css`; keep styling there rather than editing the Blocksy theme.

## Files to avoid editing

Editing these means changes will be wiped on the next update — flag this if the user asks for changes here:

- `wp-admin/`, `wp-includes/` — WordPress core
- Anything under `wp-content/plugins/*/vendor/` — third-party plugin vendored libraries
- `wp-content/upgrade/`, `wp-content/upgrade-temp-backup/` — WP update working dirs

## Active stack (so suggestions match what's installed)

- **Theme:** Blocksy 2.1.41 (Blocksy Companion plugin is also active)
- **Page builders:** Elementor (+ Essential Addons), Kadence Blocks, Essential Blocks, Templately, Smart Slider 3
- **Commerce:** WooCommerce + Advanced Product Fields, Ajax Search, WPC Shoppable Images, Filter Everything
- **Payments:** WooCommerce Payments, WooCommerce PayPal Payments
- **Marketing/analytics:** Jetpack, Klaviyo, Facebook/Snapchat/Reddit for WooCommerce, Google Listings & Ads
- **Forms/mail:** Fluent Forms, Fluent SMTP, Site Mailer
- **Other:** Advanced Custom Fields, Custom Post Type UI, Loco Translate, Image Optimization, Manage (Elementor)

## Secrets

`wp-config.php` contains **real production database credentials and salts**. Do not paste its contents into any output that leaves this session (e.g. uploaded diagrams, gists, error reports). If the user asks to regenerate salts, the WordPress secret-key service is at `https://api.wordpress.org/secret-key/1.1/salt/`.

## Debug settings

`WP_DEBUG`, `WP_DEBUG_LOG`, automatic core updates, and the automatic updater are all currently **disabled** in `wp-config.php`. If the user is debugging a runtime issue, suggest temporarily flipping `WP_DEBUG` and `WP_DEBUG_LOG` on (and reverting after).