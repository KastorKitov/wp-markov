<?php
/**
 * Single template for every "machine" custom post type.
 * Loaded automatically via the single_template filter in kastor-machines.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main class="kastor-machine-main">
	<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'kastor-machine' ); ?>>

			<div class="kastor-machine__grid">

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
										$img = wp_get_attachment_image(
											$id,
											'large',
											false,
											array( 'class' => 'kastor-machine__image' )
										);
										if ( ! $img ) { continue; }
										?>
										<div class="swiper-slide"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe HTML from core ?></div>
									<?php endforeach; ?>
								</div>

								<button type="button" class="kastor-machine__nav kastor-machine__nav-prev" aria-label="Предишно изображение"></button>
								<button type="button" class="kastor-machine__nav kastor-machine__nav-next" aria-label="Следващо изображение"></button>
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
										<div class="swiper-slide kastor-machine__thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- safe HTML from core ?></div>
									<?php endforeach; ?>
								</div>
							</div>

						</div>

					<?php elseif ( count( $image_ids ) === 1 ) :
						// ----- Single image (no carousel needed) -----
						echo wp_get_attachment_image(
							$image_ids[0],
							'large',
							false,
							array( 'class' => 'kastor-machine__image' )
						);
						?>

					<?php else :
						// ----- No images -----
						?>
						<div class="kastor-machine__image kastor-machine__image--placeholder" aria-hidden="true">
							<span>Няма изображение</span>
						</div>
					<?php endif; ?>
				</div>

				<div class="kastor-machine__body">
					<h1 class="kastor-machine__title"><?php the_title(); ?></h1>

					<?php if ( get_the_content() ) : ?>
						<div class="kastor-machine__description">
							<?php the_content(); ?>
						</div>
					<?php endif; ?>

					<?php
					$comp        = kastor_machines_get_comparison_table( get_the_ID() );
					$has_comp    = ! empty( $comp['models'] ) && ! empty( $comp['rows'] );
					$params      = kastor_machines_get_params( get_the_ID() );

					if ( $has_comp ) :
						// ------- Multi-model comparison table -------
						// Build rowspan groups: consecutive rows where group is empty
						// (or equal to the previous non-empty group) get merged into
						// one block with a rowspanned group cell.
						$groups        = array();
						$models_count  = count( $comp['models'] );
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
						<section class="kastor-machine__specs" aria-labelledby="kastor-specs-title">
							<h2 id="kastor-specs-title" class="kastor-machine__specs-title">Параметри</h2>
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
															// Group with no sub-label and only one row in block —
															// merge group + label columns.
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

					<?php elseif ( ! empty( $params ) ) :
						// ------- Simple single-model params -------
						?>
						<section class="kastor-machine__specs" aria-labelledby="kastor-specs-title">
							<h2 id="kastor-specs-title" class="kastor-machine__specs-title">Параметри</h2>
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
				</div>

			</div>

		</article>

	<?php endwhile; ?>
</main>

<?php
get_footer();
