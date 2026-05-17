# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A local copy of a WordPress 6.9.4 site originally hosted on **InstaWP**. The user downloaded the site files so Claude can read/edit them locally. The user manually uploads changed files back to the live InstaWP server.

- **Git repository, but scoped narrowly.** The repo only tracks Kastor custom code (plugins like `kastor-machines`, child themes, `CLAUDE.md`). `.gitignore` ignores everything else by default — including WordPress core, third-party plugins, the Blocksy theme, uploads, and `wp-config.php`. When adding a new custom plugin or child theme, allowlist its path in `.gitignore` or it won't be tracked. The user still uploads changed files to InstaWP manually — git is for history/rollback, not deployment.
- **No build pipeline at the project root.** No `package.json`, no test runner, no linter. There are no project-level commands to run.
- **The site is a WooCommerce store** with a large ad/analytics/payments plugin footprint (Facebook, Snapchat, Reddit, Google Listings & Ads, Klaviyo, WooCommerce Payments, PayPal).

## Workflow

1. User describes a change.
2. Edit files in this directory.
3. Tell the user exactly which files were touched (full paths) so they can upload them to the InstaWP server.

## Where custom code lives

Custom site code is NOT in `mu-plugins/` (the directory does not exist) or in a child theme (there is no Blocksy child theme present). Look in:

- **`wp-content/plugins/kastor-machines/`** — custom plugin authored by Claude. Registers machine-catalog CPTs and renders them with a styled single template. See section below.
- `wpcodeboxide/` — this is the **WPCodeBox IDE** plugin's runtime, not custom code itself. It's a standalone PHP app (uses `SHORTINIT`) that the user runs through the WordPress admin to author code snippets. Snippets created via WPCodeBox are stored in the database, not as files here, so changes made through WPCodeBox in the live admin will not appear in this local copy.
- The active theme is **Blocksy 2.1.41** at `wp-content/themes/blocksy/`. There is no child theme, so direct theme edits will be lost when Blocksy updates — prefer a child theme, a WPCodeBox snippet, or extending the `kastor-machines` plugin / writing a new plugin for new functionality. Confirm with the user before editing theme files directly.

## The `kastor-machines` plugin

A small custom plugin that powers the machine catalogue. Things that aren't obvious from glancing at the files:

- **Config-driven CPT registration.** All managed post types live in the `kastor_machines_types()` array at the top of `kastor-machines.php`. To add a new machine category, add one entry — registration, metabox, save handler, template loading, and asset enqueueing all key off that list automatically. Don't duplicate logic per CPT.
- **Internal post_type slugs are capped at 20 chars** by WordPress. Existing keys use underscores (`nova_mashina`, `treatment_machine`, etc.). Match that convention when adding more.
- **Three independent metaboxes on each machine post:** simple `Параметри` (single-model spec list), `Сравнителна таблица` (multi-model comparison grid with grouped rows + footnote), and `Галерия` (extra images for the frontend carousel). Each owns its own post-meta keys and save handler. A given post typically uses Параметри *or* Сравнителна таблица — the template renders whichever has data, preferring the comparison table.
- **Post-meta keys (use the constants, not the literal strings):**
  - `_kastor_machine_params` (`KASTOR_MACHINES_PARAMS_META`) — `array<{label, value}>`, read via `kastor_machines_get_params()`.
  - `_kastor_machine_gallery` (`KASTOR_MACHINES_GALLERY_META`) — `int[]` of attachment IDs in display order, read via `kastor_machines_get_gallery()`. The carousel's full image list (featured + gallery, de-duplicated) is `kastor_machines_get_carousel_image_ids()`.
  - `_kastor_machine_models` / `_kastor_machine_specs` / `_kastor_machine_specs_note` — the three pieces of the comparison table, read together via `kastor_machines_get_comparison_table()` which returns `{models[], rows[], note}`. Each row is `{group, label, unit, values[]}` with `values[i]` aligned to `models[i]`. Consecutive rows where `group` is empty (or equals the previous row's `group`) get rendered with a rowspanned group cell in the frontend.
- **Comparison-table input UX is a stateful mini-app in `specs.js`.** It owns a JS state object and serialises it to a single hidden `<input name="kastor_specs_json">` on every change; the PHP save handler `json_decode`s and sanitises. Text typing updates state in place without re-rendering (preserves input focus); add/remove model or row triggers a full re-render. If you add new fields to a row, update *both* the JS shape *and* the PHP sanitiser, otherwise data silently drops on save.
- **One shared single template** (`single-machine.php`) renders every machine CPT, loaded via the `single_template` filter. There are no per-CPT `single-{slug}.php` files and you don't need to create them.
- **Permalinks flush is one-time per activation.** If you add a CPT to the config of an already-active plugin, the new URL slug won't work until the user re-saves **Settings → Permalinks** (or you bump the plugin version and re-activate). Always remind the user after adding CPTs.
- **CSS class prefix is `.kastor-machine__*`** (BEM). The frontend stylesheet is theme-independent — if the user reports styling that clashes with Blocksy, edit `frontend.css` rather than reaching into the theme.

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