<?php
/**
 * Single template for every "machine" custom post type.
 * Loaded automatically via the single_template filter in kastor-machines.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render an image wrapped in a lightbox-trigger anchor pointing to the
 * full-size attachment URL.
 */
function kastor_machines_render_lightbox_image( $id, $size = 'large', $img_attrs = array() ) {
	$full = wp_get_attachment_image_url( $id, 'full' );
	$img  = wp_get_attachment_image( $id, $size, false, $img_attrs );
	if ( ! $img ) {
		return '';
	}
	return '<a href="' . esc_url( $full ) . '" class="kastor-machine__lightbox-trigger" data-kastor-lightbox aria-label="Виж в по-голям размер">' . $img . '</a>';
}

get_header();
?>

<main class="kastor-machine-main">
	<?php while ( have_posts() ) : the_post(); ?>

		<?php
		$comp           = kastor_machines_get_comparison_table( get_the_ID() );
		$has_comp       = ! empty( $comp['models'] ) && ! empty( $comp['rows'] );
		$params         = kastor_machines_get_params( get_the_ID() );
		$highlights     = kastor_machines_get_highlights( get_the_ID() );
		$has_content    = (bool) trim( strip_tags( get_the_content() ) );
		$has_simple_tbl = ! $has_comp && ! empty( $params );
		$has_body       = $has_content || ! empty( $highlights ) || $has_simple_tbl;
		$cpt_obj        = get_post_type_object( get_post_type() );
		$archive_url    = get_post_type_archive_link( get_post_type() );
		?>

		<nav class="kastor-machine__breadcrumbs" aria-label="Навигация">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Начало</a>
			<?php if ( $archive_url && $cpt_obj ) : ?>
				<span class="kastor-machine__breadcrumb-sep" aria-hidden="true">›</span>
				<a href="<?php echo esc_url( $archive_url ); ?>"><?php echo esc_html( $cpt_obj->labels->name ); ?></a>
			<?php endif; ?>
			<span class="kastor-machine__breadcrumb-sep" aria-hidden="true">›</span>
			<span class="kastor-machine__breadcrumb-current" aria-current="page"><?php echo esc_html( get_the_title() ); ?></span>
		</nav>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'kastor-machine' ); ?>>

			<header class="kastor-machine__header">
				<h1 class="kastor-machine__title"><?php the_title(); ?></h1>
			</header>

			<div class="kastor-machine__hero">

				<div class="kastor-machine__media">
					<?php
					$image_ids = kastor_machines_get_carousel_image_ids( get_the_ID() );

					if ( count( $image_ids ) > 1 ) :
						// ----- Multi-image carousel -----
						?>
						<div class="kastor-machine__carousel" data-kastor-carousel>

							<div class="swiper kastor-machine__swiper">
								<div class="swiper-wrapper">
									<?php foreach ( $image_ids as $id ) :
										$slide = kastor_machines_render_lightbox_image(
											$id,
											'large',
											array( 'class' => 'kastor-machine__image' )
										);
										if ( ! $slide ) { continue; }
										?>
										<div class="swiper-slide"><?php echo $slide; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="swiper kastor-machine__thumbs">
								<div class="swiper-wrapper">
									<?php foreach ( $image_ids as $id ) :
										$thumb = wp_get_attachment_image(
											$id,
											'thumbnail',
											false,
											array( 'class' => 'kastor-machine__thumb-image', 'loading' => 'lazy' )
										);
										if ( ! $thumb ) { continue; }
										?>
										<div class="swiper-slide kastor-machine__thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
									<?php endforeach; ?>
								</div>
							</div>

						</div>

					<?php elseif ( count( $image_ids ) === 1 ) :
						// ----- Single image (no carousel needed) -----
						echo kastor_machines_render_lightbox_image(
							$image_ids[0],
							'large',
							array( 'class' => 'kastor-machine__image' )
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>

					<?php else :
						// ----- No images -----
						?>
						<div class="kastor-machine__image kastor-machine__image--placeholder" aria-hidden="true">
							<span>Няма изображение</span>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $has_body ) : ?>
					<div class="kastor-machine__body">

						<?php if ( $has_content ) : ?>
							<div class="kastor-machine__description">
								<?php the_content(); ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $highlights ) ) : ?>
							<ul class="kastor-machine__highlights">
								<?php foreach ( $highlights as $h ) :
									$value = isset( $h['value'] ) ? (string) $h['value'] : '';
									$label = isset( $h['label'] ) ? (string) $h['label'] : '';
									if ( $value === '' && $label === '' ) { continue; }
									?>
									<li class="kastor-machine__highlight">
										<?php if ( $value !== '' ) : ?>
											<span class="kastor-machine__highlight-value"><?php echo esc_html( $value ); ?></span>
										<?php endif; ?>
										<?php if ( $label !== '' ) : ?>
											<span class="kastor-machine__highlight-label"><?php echo esc_html( $label ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $has_simple_tbl ) : ?>
							<section class="kastor-machine__specs">
								<table class="kastor-machine__specs-table">
									<tbody>
										<?php foreach ( $params as $row ) :
											$label = isset( $row['label'] ) ? $row['label'] : '';
											$value = isset( $row['value'] ) ? $row['value'] : '';
											if ( $label === '' && $value === '' ) {
												continue;
											}
											?>
											<tr>
												<th scope="row"><?php echo esc_html( $label ); ?></th>
												<td><?php echo esc_html( $value ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</section>
						<?php endif; ?>

						<a class="kastor-machine__cta" href="<?php echo esc_url( kastor_machines_get_inquiry_url() ); ?>">
							Свържете се с нас
						</a>

					</div><!-- /.kastor-machine__body -->
				<?php endif; ?>

			</div><!-- /.kastor-machine__hero -->

			<?php if ( $has_comp ) :
				// ------- Multi-model comparison table (full width, below the hero) -------
				// Build rowspan groups: consecutive rows where group is empty
				// (or equal to the previous non-empty group) get merged into
				// one block with a rowspanned group cell.
				$groups       = array();
				$models_count = count( $comp['models'] );
				foreach ( $comp['rows'] as $row ) {
					$g = isset( $row['group'] ) ? (string) $row['group'] : '';
					if ( ! empty( $groups ) ) {
						$last_idx = count( $groups ) - 1;
						$last_g   = $groups[ $last_idx ]['group'];
						if ( $g === '' || $g === $last_g ) {
							$groups[ $last_idx ]['rows'][] = $row;
							continue;
						}
					}
					$groups[] = array( 'group' => $g, 'rows' => array( $row ) );
				}
				?>
				<section class="kastor-machine__specs kastor-machine__specs--full" aria-labelledby="kastor-specs-heading" data-kastor-reveal="from-right">
					<h2 id="kastor-specs-heading" class="kastor-machine__specs-heading">Технически данни</h2>
					<div class="kastor-machine__specs-scroll">
						<table class="kastor-machine__specs-comparison">
							<thead>
								<tr>
									<th scope="colgroup" colspan="2" class="kastor-machine__specs-param-col">Параметър</th>
									<th scope="col" class="kastor-machine__specs-unit-col">Единици</th>
									<?php foreach ( $comp['models'] as $model ) : ?>
										<th scope="col" class="kastor-machine__specs-model-col"><?php echo esc_html( $model ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $groups as $block ) :
									$rowspan       = count( $block['rows'] );
									$has_group_lbl = $block['group'] !== '';
									foreach ( $block['rows'] as $i => $row ) :
										$label = isset( $row['label'] ) ? (string) $row['label'] : '';
										$unit  = isset( $row['unit'] )  ? (string) $row['unit']  : '';
										$vals  = isset( $row['values'] ) && is_array( $row['values'] ) ? $row['values'] : array();
										?>
										<tr>
											<?php if ( $i === 0 ) :
												if ( $has_group_lbl && $rowspan === 1 && $label === '' ) :
													?>
													<th scope="rowgroup" colspan="2" class="kastor-machine__specs-group kastor-machine__specs-group--standalone"><?php echo esc_html( $block['group'] ); ?></th>
													<?php
												elseif ( $has_group_lbl ) : ?>
													<th scope="rowgroup" rowspan="<?php echo (int) $rowspan; ?>" class="kastor-machine__specs-group"><?php echo esc_html( $block['group'] ); ?></th>
													<?php
												else : ?>
													<th rowspan="<?php echo (int) $rowspan; ?>" class="kastor-machine__specs-group kastor-machine__specs-group--empty" aria-hidden="true"></th>
													<?php
												endif;
											endif; ?>

											<?php if ( ! ( $has_group_lbl && $rowspan === 1 && $label === '' ) ) : ?>
												<th scope="row" class="kastor-machine__specs-label"><?php echo esc_html( $label ); ?></th>
											<?php endif; ?>

											<td class="kastor-machine__specs-unit"><?php echo esc_html( $unit ); ?></td>

											<?php for ( $m = 0; $m < $models_count; $m++ ) :
												$v = isset( $vals[ $m ] ) ? (string) $vals[ $m ] : '';
												?>
												<td class="kastor-machine__specs-value"><?php echo esc_html( $v ); ?></td>
											<?php endfor; ?>
										</tr>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ( ! empty( $comp['note'] ) ) : ?>
						<p class="kastor-machine__specs-note"><?php echo nl2br( esc_html( $comp['note'] ) ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php
			$long_desc = kastor_machines_get_long_description( get_the_ID() );
			if ( $long_desc !== '' ) : ?>
				<section class="kastor-machine__long-desc" aria-labelledby="kastor-long-desc-heading" data-kastor-reveal="up">
					<h2 id="kastor-long-desc-heading" class="kastor-machine__specs-heading">Описание</h2>
					<div class="kastor-machine__long-desc-body">
						<?php echo wp_kses_post( wpautop( $long_desc ) ); ?>
					</div>
				</section>
			<?php endif; ?>

		</article>

		<?php
		$related_query = new WP_Query( array(
			'post_type'           => get_post_type(),
			'post__not_in'        => array( get_the_ID() ),
			'posts_per_page'      => 4,
			'orderby'             => 'rand',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		) );

		if ( $related_query->have_posts() ) : ?>
			<section class="kastor-machine__related" aria-labelledby="kastor-related-title" data-kastor-reveal="up">
				<h2 id="kastor-related-title" class="kastor-machine__related-title">Други машини</h2>
				<ul class="kastor-machine__related-grid">
					<?php while ( $related_query->have_posts() ) : $related_query->the_post();
						$rel_thumb_id = get_post_thumbnail_id();
						if ( ! $rel_thumb_id ) {
							$rel_ids = kastor_machines_get_carousel_image_ids( get_the_ID() );
							$rel_thumb_id = ! empty( $rel_ids ) ? $rel_ids[0] : 0;
						}
						?>
						<li class="kastor-machine__related-card">
							<a href="<?php the_permalink(); ?>" class="kastor-machine__related-link">
								<div class="kastor-machine__related-thumb">
									<?php if ( $rel_thumb_id ) {
										echo wp_get_attachment_image(
											$rel_thumb_id,
											'medium',
											false,
											array( 'class' => 'kastor-machine__related-image', 'loading' => 'lazy' )
										);
									} else { ?>
										<span class="kastor-machine__related-placeholder" aria-hidden="true">Няма изображение</span>
									<?php } ?>
								</div>
								<h3 class="kastor-machine__related-name"><?php the_title(); ?></h3>
							</a>
						</li>
					<?php endwhile; ?>
				</ul>
			</section>
			<?php wp_reset_postdata(); ?>
		<?php endif; ?>

	<?php endwhile; ?>
</main>

<?php
get_footer();
