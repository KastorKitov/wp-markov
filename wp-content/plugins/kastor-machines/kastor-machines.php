<?php
/**
 * Plugin Name: Kastor Machines
 * Description: Custom machine post types ("Нови машини", "Обезаразителни/Третиращи машини", "Рециклирани машини", "ТРАНСПОРТНИ СЪОРЪЖЕНИЯ") with a shared Parameters metabox and styled single template.
 * Version: 0.2.0
 * Author: Kastor
 * Text Domain: kastor-machines
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KASTOR_MACHINES_VERSION', '0.2.0' );
define( 'KASTOR_MACHINES_PATH', plugin_dir_path( __FILE__ ) );
define( 'KASTOR_MACHINES_URL', plugin_dir_url( __FILE__ ) );
define( 'KASTOR_MACHINES_PARAMS_META', '_kastor_machine_params' );


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
 * 2. "Параметри" metabox — shared by all machine CPTs
 * -------------------------------------------------------------------------- */

add_action( 'add_meta_boxes', 'kastor_machines_add_params_metabox' );
function kastor_machines_add_params_metabox() {
	add_meta_box(
		'kastor_machine_params',
		'Параметри',
		'kastor_machines_render_params_metabox',
		kastor_machines_type_keys(),
		'normal',
		'high'
	);
}

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


/* --------------------------------------------------------------------------
 * 3. Asset enqueueing (admin + frontend)
 * -------------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', 'kastor_machines_admin_assets' );
function kastor_machines_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, kastor_machines_type_keys(), true ) ) {
		return;
	}
	wp_enqueue_style(
		'kastor-machines-admin',
		KASTOR_MACHINES_URL . 'admin.css',
		array(),
		KASTOR_MACHINES_VERSION
	);
	wp_enqueue_script(
		'kastor-machines-admin',
		KASTOR_MACHINES_URL . 'admin.js',
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
}


/* --------------------------------------------------------------------------
 * 4. Load shared single-machine template from the plugin
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
 * 5. Helper for templates
 * -------------------------------------------------------------------------- */

function kastor_machines_get_params( $post_id = null ) {
	$post_id = $post_id ?: get_the_ID();
	$params  = get_post_meta( $post_id, KASTOR_MACHINES_PARAMS_META, true );
	return is_array( $params ) ? $params : array();
}
