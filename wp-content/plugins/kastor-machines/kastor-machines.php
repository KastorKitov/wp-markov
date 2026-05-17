<?php
/**
 * Plugin Name: Kastor Machines
 * Description: Custom machine post types ("Нови машини", "Обезаразителни/Третиращи машини", "Рециклирани машини", "ТРАНСПОРТНИ СЪОРЪЖЕНИЯ") with shared Parameters and Gallery metaboxes and a styled single template.
 * Version: 0.3.0
 * Author: Kastor
 * Text Domain: kastor-machines
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KASTOR_MACHINES_VERSION', '0.4.0' );
define( 'KASTOR_MACHINES_PATH', plugin_dir_path( __FILE__ ) );
define( 'KASTOR_MACHINES_URL', plugin_dir_url( __FILE__ ) );
define( 'KASTOR_MACHINES_PARAMS_META', '_kastor_machine_params' );
define( 'KASTOR_MACHINES_GALLERY_META', '_kastor_machine_gallery' );

// Comparison table (multi-model machines like the JCC series).
define( 'KASTOR_MACHINES_MODELS_META', '_kastor_machine_models' );
define( 'KASTOR_MACHINES_SPECS_META', '_kastor_machine_specs' );
define( 'KASTOR_MACHINES_SPECS_NOTE_META', '_kastor_machine_specs_note' );

// Swiper.js (frontend carousel) — loaded from jsDelivr CDN.
define( 'KASTOR_MACHINES_SWIPER_VERSION', '11' );
define( 'KASTOR_MACHINES_SWIPER_CSS', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css' );
define( 'KASTOR_MACHINES_SWIPER_JS',  'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js' );


/**
 * All machine post types this plugin manages.
 * To add a new machine category, just add an entry here.
 *
 * Keys are internal post_type slugs (max 20 chars, lowercase, [a-z0-9_-]).
 */
function kastor_machines_types() {
	return array(
		'nova_mashina' => array(
			'singular' => 'Нова машина',
			'plural'   => 'Нови машини',
			'slug'     => 'novi-mashini',
			'icon'     => 'dashicons-hammer',
			'position' => 20,
		),
		'treatment_machine' => array(
			'singular' => 'Третираща машина',
			'plural'   => 'Обезаразителни/Третиращи машини',
			'slug'     => 'obezarazitelni-mashini',
			'icon'     => 'dashicons-shield',
			'position' => 21,
		),
		'recycled_machine' => array(
			'singular' => 'Рециклирана машина',
			'plural'   => 'Рециклирани машини',
			'slug'     => 'reciklirani-mashini',
			'icon'     => 'dashicons-update',
			'position' => 22,
		),
		'transport_equipment' => array(
			'singular' => 'Транспортно съоръжение',
			'plural'   => 'ТРАНСПОРТНИ СЪОРЪЖЕНИЯ',
			'slug'     => 'transportni-saorazheniya',
			'icon'     => 'dashicons-car',
			'position' => 23,
		),
	);
}

function kastor_machines_type_keys() {
	return array_keys( kastor_machines_types() );
}


/* --------------------------------------------------------------------------
 * 1. Register all machine custom post types
 * -------------------------------------------------------------------------- */

add_action( 'init', 'kastor_machines_register_cpts' );
function kastor_machines_register_cpts() {
	foreach ( kastor_machines_types() as $key => $cfg ) {
		register_post_type(
			$key,
			array(
				'labels'        => array(
					'name'               => $cfg['plural'],
					'singular_name'      => $cfg['singular'],
					'menu_name'          => $cfg['plural'],
					'add_new'            => 'Добави нова',
					'add_new_item'       => 'Добави ' . $cfg['singular'],
					'edit_item'          => 'Редактирай: ' . $cfg['singular'],
					'new_item'           => $cfg['singular'],
					'view_item'          => 'Виж',
					'search_items'       => 'Търси',
					'not_found'          => 'Няма намерени',
					'not_found_in_trash' => 'Няма в кошчето',
					'all_items'          => 'Всички',
				),
				'public'        => true,
				'has_archive'   => true,
				'menu_position' => $cfg['position'],
				'menu_icon'     => $cfg['icon'],
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'       => array( 'slug' => $cfg['slug'], 'with_front' => false ),
				'show_in_rest'  => true,
			)
		);
	}
}

// Flush rewrite rules on activation so the new URLs work immediately.
register_activation_hook( __FILE__, function () {
	kastor_machines_register_cpts();
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );


/* --------------------------------------------------------------------------
 * 2. Metaboxes — shared by all machine CPTs
 * -------------------------------------------------------------------------- */

add_action( 'add_meta_boxes', 'kastor_machines_add_metaboxes' );
function kastor_machines_add_metaboxes() {
	$screens = kastor_machines_type_keys();

	add_meta_box(
		'kastor_machine_gallery',
		'Галерия',
		'kastor_machines_render_gallery_metabox',
		$screens,
		'normal',
		'high'
	);

	add_meta_box(
		'kastor_machine_params',
		'Параметри (един модел)',
		'kastor_machines_render_params_metabox',
		$screens,
		'normal',
		'default'
	);

	add_meta_box(
		'kastor_machine_specs',
		'Сравнителна таблица (няколко модела)',
		'kastor_machines_render_specs_metabox',
		$screens,
		'normal',
		'default'
	);
}


/* ---------- Параметри metabox ---------- */

function kastor_machines_render_params_metabox( $post ) {
	wp_nonce_field( 'kastor_machines_save_params', 'kastor_machines_params_nonce' );

	$params = get_post_meta( $post->ID, KASTOR_MACHINES_PARAMS_META, true );
	if ( ! is_array( $params ) ) {
		$params = array();
	}
	if ( empty( $params ) ) {
		$params = array( array( 'label' => '', 'value' => '' ) );
	}
	?>
	<div class="kastor-params-wrap" data-kastor-params>
		<p class="description">Добавете спецификации като двойки етикет → стойност. Например: <em>Мощност → 5 kW</em>.</p>

		<div class="kastor-params-head">
			<div>Етикет</div>
			<div>Стойност</div>
			<div></div>
		</div>

		<div class="kastor-params-rows" data-kastor-rows>
			<?php foreach ( $params as $row ) :
				$label = isset( $row['label'] ) ? $row['label'] : '';
				$value = isset( $row['value'] ) ? $row['value'] : '';
				?>
				<div class="kastor-params-row" data-kastor-row>
					<input type="text" name="kastor_params[label][]" value="<?php echo esc_attr( $label ); ?>" placeholder="напр. Мощност" />
					<input type="text" name="kastor_params[value][]" value="<?php echo esc_attr( $value ); ?>" placeholder="напр. 5 kW" />
					<button type="button" class="button kastor-params-remove" data-kastor-remove aria-label="Премахни ред">&times;</button>
				</div>
			<?php endforeach; ?>
		</div>

		<p>
			<button type="button" class="button button-secondary" data-kastor-add>+ Добави параметър</button>
		</p>

		<template data-kastor-template>
			<div class="kastor-params-row" data-kastor-row>
				<input type="text" name="kastor_params[label][]" value="" placeholder="напр. Мощност" />
				<input type="text" name="kastor_params[value][]" value="" placeholder="напр. 5 kW" />
				<button type="button" class="button kastor-params-remove" data-kastor-remove aria-label="Премахни ред">&times;</button>
			</div>
		</template>
	</div>
	<?php
}


/* ---------- Галерия metabox ---------- */

function kastor_machines_render_gallery_metabox( $post ) {
	wp_nonce_field( 'kastor_machines_save_gallery', 'kastor_machines_gallery_nonce' );

	$ids = kastor_machines_get_gallery( $post->ID );
	?>
	<div class="kastor-gallery-wrap" data-kastor-gallery>
		<p class="description">Изберете изображения за галерия (карусел) на страницата на машината. Първото е „главното" — то се показва първо. Кликнете и плъзнете за пренареждане. „×" премахва изображение.</p>

		<input type="hidden" name="kastor_gallery_ids" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" data-kastor-gallery-input />

		<ul class="kastor-gallery-list" data-kastor-gallery-list>
			<?php foreach ( $ids as $attachment_id ) :
				$thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
				if ( ! $thumb ) { continue; }
				?>
				<li class="kastor-gallery-item" data-id="<?php echo (int) $attachment_id; ?>" draggable="true">
					<img src="<?php echo esc_url( $thumb[0] ); ?>" alt="" />
					<button type="button" class="kastor-gallery-remove" data-kastor-gallery-remove aria-label="Премахни">&times;</button>
				</li>
			<?php endforeach; ?>
		</ul>

		<p>
			<button type="button" class="button button-secondary" data-kastor-gallery-add>+ Добави изображения</button>
		</p>
	</div>
	<?php
}


/* ---------- Сравнителна таблица metabox ---------- */

function kastor_machines_render_specs_metabox( $post ) {
	wp_nonce_field( 'kastor_machines_save_specs', 'kastor_machines_specs_nonce' );

	$data = kastor_machines_get_comparison_table( $post->ID );
	$json = wp_json_encode( $data );
	if ( ! $json ) {
		$json = '{"models":[],"rows":[],"note":""}';
	}
	?>
	<div class="kastor-specs-wrap" data-kastor-specs>
		<p class="description">
			Използвайте тази таблица, когато машината има <strong>няколко модела</strong> (напр. JCC 03 / JCC 05 / JCC 08).
			Първо добавете моделите (като колони), след това редовете с параметри.
			Оставете „Група" празна, за да продължите групирането на горния ред.
			Ако машината има само един модел, използвайте простия раздел „Параметри" по-горе вместо тази таблица.
		</p>

		<input type="hidden" name="kastor_specs_json" value="" data-kastor-specs-input />
		<script type="application/json" data-kastor-specs-init><?php echo $json; // already JSON-encoded ?></script>

		<div data-kastor-specs-root></div>

		<p>
			<label for="kastor-specs-note"><strong>Бележки под таблицата (опц.):</strong></label><br>
			<textarea id="kastor-specs-note" rows="3" class="large-text" data-kastor-specs-note placeholder="напр. *Параметрите са валидни за пшеница, тегло 750 кг/м³, влажност 16%."></textarea>
		</p>
	</div>
	<?php
}


/* --------------------------------------------------------------------------
 * 3. Save handlers
 * -------------------------------------------------------------------------- */

add_action( 'save_post', 'kastor_machines_save_params', 10, 2 );
function kastor_machines_save_params( $post_id, $post ) {
	if ( ! in_array( $post->post_type, kastor_machines_type_keys(), true ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['kastor_machines_params_nonce'] ) ||
	     ! wp_verify_nonce( $_POST['kastor_machines_params_nonce'], 'kastor_machines_save_params' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$labels = isset( $_POST['kastor_params']['label'] ) ? (array) $_POST['kastor_params']['label'] : array();
	$values = isset( $_POST['kastor_params']['value'] ) ? (array) $_POST['kastor_params']['value'] : array();

	$clean = array();
	$count = max( count( $labels ), count( $values ) );
	for ( $i = 0; $i < $count; $i++ ) {
		$label = isset( $labels[ $i ] ) ? sanitize_text_field( wp_unslash( $labels[ $i ] ) ) : '';
		$value = isset( $values[ $i ] ) ? sanitize_text_field( wp_unslash( $values[ $i ] ) ) : '';
		if ( $label === '' && $value === '' ) {
			continue;
		}
		$clean[] = array( 'label' => $label, 'value' => $value );
	}

	if ( empty( $clean ) ) {
		delete_post_meta( $post_id, KASTOR_MACHINES_PARAMS_META );
	} else {
		update_post_meta( $post_id, KASTOR_MACHINES_PARAMS_META, $clean );
	}
}

add_action( 'save_post', 'kastor_machines_save_specs', 10, 2 );
function kastor_machines_save_specs( $post_id, $post ) {
	if ( ! in_array( $post->post_type, kastor_machines_type_keys(), true ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['kastor_machines_specs_nonce'] ) ||
	     ! wp_verify_nonce( $_POST['kastor_machines_specs_nonce'], 'kastor_machines_save_specs' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw  = isset( $_POST['kastor_specs_json'] ) ? wp_unslash( $_POST['kastor_specs_json'] ) : '';
	$data = json_decode( $raw, true );

	if ( ! is_array( $data ) ) {
		delete_post_meta( $post_id, KASTOR_MACHINES_MODELS_META );
		delete_post_meta( $post_id, KASTOR_MACHINES_SPECS_META );
		delete_post_meta( $post_id, KASTOR_MACHINES_SPECS_NOTE_META );
		return;
	}

	$models = array();
	if ( ! empty( $data['models'] ) && is_array( $data['models'] ) ) {
		foreach ( $data['models'] as $m ) {
			$m = sanitize_text_field( (string) $m );
			$models[] = $m; // keep empties as positional placeholders for value indices
		}
		// Trim trailing empties.
		while ( ! empty( $models ) && end( $models ) === '' ) {
			array_pop( $models );
		}
	}

	$rows = array();
	if ( ! empty( $data['rows'] ) && is_array( $data['rows'] ) ) {
		foreach ( $data['rows'] as $r ) {
			if ( ! is_array( $r ) ) {
				continue;
			}
			$clean = array(
				'group'  => isset( $r['group'] ) ? sanitize_text_field( (string) $r['group'] ) : '',
				'label'  => isset( $r['label'] ) ? sanitize_text_field( (string) $r['label'] ) : '',
				'unit'   => isset( $r['unit'] ) ? sanitize_text_field( (string) $r['unit'] ) : '',
				'values' => array(),
			);
			if ( ! empty( $r['values'] ) && is_array( $r['values'] ) ) {
				foreach ( $r['values'] as $v ) {
					$clean['values'][] = sanitize_text_field( (string) $v );
				}
			}
			$has_content = $clean['group'] !== '' || $clean['label'] !== '' || $clean['unit'] !== '';
			if ( ! $has_content ) {
				foreach ( $clean['values'] as $v ) {
					if ( $v !== '' ) { $has_content = true; break; }
				}
			}
			if ( $has_content ) {
				$rows[] = $clean;
			}
		}
	}

	$note = isset( $data['note'] ) ? sanitize_textarea_field( (string) $data['note'] ) : '';

	if ( empty( $models ) && empty( $rows ) && $note === '' ) {
		delete_post_meta( $post_id, KASTOR_MACHINES_MODELS_META );
		delete_post_meta( $post_id, KASTOR_MACHINES_SPECS_META );
		delete_post_meta( $post_id, KASTOR_MACHINES_SPECS_NOTE_META );
	} else {
		update_post_meta( $post_id, KASTOR_MACHINES_MODELS_META, $models );
		update_post_meta( $post_id, KASTOR_MACHINES_SPECS_META, $rows );
		update_post_meta( $post_id, KASTOR_MACHINES_SPECS_NOTE_META, $note );
	}
}

add_action( 'save_post', 'kastor_machines_save_gallery', 10, 2 );
function kastor_machines_save_gallery( $post_id, $post ) {
	if ( ! in_array( $post->post_type, kastor_machines_type_keys(), true ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['kastor_machines_gallery_nonce'] ) ||
	     ! wp_verify_nonce( $_POST['kastor_machines_gallery_nonce'], 'kastor_machines_save_gallery' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$raw = isset( $_POST['kastor_gallery_ids'] ) ? wp_unslash( $_POST['kastor_gallery_ids'] ) : '';
	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

	if ( empty( $ids ) ) {
		delete_post_meta( $post_id, KASTOR_MACHINES_GALLERY_META );
	} else {
		update_post_meta( $post_id, KASTOR_MACHINES_GALLERY_META, array_values( $ids ) );
	}
}


/* --------------------------------------------------------------------------
 * 4. Asset enqueueing (admin + frontend)
 * -------------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', 'kastor_machines_admin_assets' );
function kastor_machines_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, kastor_machines_type_keys(), true ) ) {
		return;
	}

	// WordPress media picker (for the Gallery metabox).
	wp_enqueue_media();

	wp_enqueue_style(
		'kastor-machines-admin',
		KASTOR_MACHINES_URL . 'admin.css',
		array(),
		KASTOR_MACHINES_VERSION
	);
	wp_enqueue_script(
		'kastor-machines-admin',
		KASTOR_MACHINES_URL . 'admin.js',
		array( 'jquery' ), // wp.media requires jQuery to already be loaded
		KASTOR_MACHINES_VERSION,
		true
	);
	wp_enqueue_script(
		'kastor-machines-specs',
		KASTOR_MACHINES_URL . 'specs.js',
		array(),
		KASTOR_MACHINES_VERSION,
		true
	);
}

add_action( 'wp_enqueue_scripts', 'kastor_machines_frontend_assets' );
function kastor_machines_frontend_assets() {
	$keys = kastor_machines_type_keys();
	if ( ! is_singular( $keys ) && ! is_post_type_archive( $keys ) ) {
		return;
	}

	wp_enqueue_style(
		'kastor-machines-frontend',
		KASTOR_MACHINES_URL . 'frontend.css',
		array(),
		KASTOR_MACHINES_VERSION
	);

	// Swiper.js carousel — only on single machine pages.
	if ( is_singular( $keys ) ) {
		wp_enqueue_style(
			'kastor-machines-swiper',
			KASTOR_MACHINES_SWIPER_CSS,
			array(),
			KASTOR_MACHINES_SWIPER_VERSION
		);
		wp_enqueue_script(
			'kastor-machines-swiper',
			KASTOR_MACHINES_SWIPER_JS,
			array(),
			KASTOR_MACHINES_SWIPER_VERSION,
			true
		);
		wp_enqueue_script(
			'kastor-machines-carousel',
			KASTOR_MACHINES_URL . 'carousel.js',
			array( 'kastor-machines-swiper' ),
			KASTOR_MACHINES_VERSION,
			true
		);
	}
}


/* --------------------------------------------------------------------------
 * 5. Load shared single-machine template from the plugin
 *    (used for every machine CPT — no theme edits)
 * -------------------------------------------------------------------------- */

add_filter( 'single_template', 'kastor_machines_single_template' );
function kastor_machines_single_template( $template ) {
	if ( is_singular( kastor_machines_type_keys() ) ) {
		$candidate = KASTOR_MACHINES_PATH . 'single-machine.php';
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}
	return $template;
}


/* --------------------------------------------------------------------------
 * 6. Helpers for templates
 * -------------------------------------------------------------------------- */

function kastor_machines_get_params( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$params  = get_post_meta( $post_id, KASTOR_MACHINES_PARAMS_META, true );
	return is_array( $params ) ? $params : array();
}

function kastor_machines_get_gallery( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$ids     = get_post_meta( $post_id, KASTOR_MACHINES_GALLERY_META, true );
	if ( ! is_array( $ids ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'absint', $ids ) ) );
}

/**
 * Read the multi-model comparison table for a post.
 *
 * Returns array with keys:
 *   - models: array of strings (column headers)
 *   - rows:   array of { group, label, unit, values[] } records
 *   - note:   footnote text shown beneath the table
 */
function kastor_machines_get_comparison_table( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$models  = get_post_meta( $post_id, KASTOR_MACHINES_MODELS_META, true );
	$rows    = get_post_meta( $post_id, KASTOR_MACHINES_SPECS_META, true );
	$note    = get_post_meta( $post_id, KASTOR_MACHINES_SPECS_NOTE_META, true );
	return array(
		'models' => is_array( $models ) ? array_values( array_map( 'strval', $models ) ) : array(),
		'rows'   => is_array( $rows )   ? array_values( $rows )                          : array(),
		'note'   => is_string( $note )  ? $note                                          : '',
	);
}

/**
 * Return the ordered list of image attachment IDs to show in the carousel:
 * featured image first, then the gallery images (deduped).
 */
function kastor_machines_get_carousel_image_ids( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$ids     = array();

	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		$ids[] = (int) $thumb_id;
	}

	foreach ( kastor_machines_get_gallery( $post_id ) as $id ) {
		if ( ! in_array( $id, $ids, true ) ) {
			$ids[] = (int) $id;
		}
	}

	return $ids;
}
