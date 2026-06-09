<?php
/**
 * Template: Blog (home.php)
 * Loads native WordPress posts when a static front page + separate posts page is set.
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="aqc-page-wrapper">
	<?php get_template_part( 'aqc-page-header', null, array(
		'title' => get_the_archive_title(),
		'description' => get_the_archive_description(),
	) ); ?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<?php if ( have_posts() ) : ?>
				<div class="aqc-grid-3" role="list">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<article class="aqc-blog-card" role="listitem" id="post-<?php the_ID(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
								</a>
							<?php endif; ?>

							<?php if ( has_category() ) : ?>
								<span class="aqc-quiz-tag">
									<?php echo esc_html( get_the_category()[0]->name ); ?>
								</span>
							<?php endif; ?>

							<h3>
								<a href="<?php the_permalink(); ?>">
									<?php the_title(); ?>
								</a>
							</h3>

							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>

							<div class="aqc-blog-meta">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
									<?php echo esc_html( get_the_date() ); ?>
								</time>
								<span><?php echo esc_html( get_the_author() ); ?></span>
							</div>
						</article>
					<?php endwhile; ?>
				</div>

				<nav class="aqc-pagination" aria-label="<?php esc_attr_e( 'Blog pagination', 'astra-quiz-child' ); ?>">
					<?php
					the_posts_pagination( array(
						'mid_size'  => 2,
						'prev_text' => '←',
						'next_text' => '→',
					) );
					?>
				</nav>
			<?php else : ?>
				<p><?php esc_html_e( 'No posts found.', 'astra-quiz-child' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
