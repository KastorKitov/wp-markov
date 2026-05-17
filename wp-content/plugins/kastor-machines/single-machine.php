<?php
/**
 * Single template for the "Нова машина" custom post type.
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
					<?php if ( has_post_thumbnail() ) : ?>
						<?php the_post_thumbnail( 'large', array( 'class' => 'kastor-machine__image' ) ); ?>
					<?php else : ?>
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
					$params = kastor_machines_get_params( get_the_ID() );
					if ( ! empty( $params ) ) : ?>
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
