<?php
/**
 * Template: Search Results
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();

$search_query = get_search_query();
?>

<main id="primary" class="aqc-page-wrapper">
	<?php get_template_part( 'aqc-page-header', null, array(
		'title' => sprintf( __( 'Search Results for: %s', 'astra-quiz-child' ), get_search_query() ),
		'description' => __( 'Showing results from quizzes and blog posts.', 'astra-quiz-child' ),
	) ); ?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<?php if ( have_posts() ) : ?>
				<div class="aqc-grid-3" role="list">
					<?php
					while ( have_posts() ) :
						the_post();
						$post_type = get_post_type();
						?>
						<article class="aqc-blog-card" role="listitem" id="post-<?php the_ID(); ?>">
							<?php if ( has_post_thumbnail() ) : ?>
								<a href="<?php the_permalink(); ?>">
									<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
								</a>
							<?php endif; ?>

							<?php if ( 'gmcq_quiz' === $post_type && function_exists( 'gmcq_get_quiz_meta' ) ) : ?>
								<?php
								$quiz_meta = gmcq_get_quiz_meta( get_the_ID() );
								$cat_name = ! empty( $quiz_meta->category_name ) ? esc_html( $quiz_meta->category_name ) : __( 'Mock Test', 'astra-quiz-child' );
								?>
								<span class="aqc-quiz-tag"><?php echo $cat_name; ?></span>
								<div class="aqc-quiz-meta">
									<span><?php echo (int) ( $quiz_meta->question_count ?? 0 ); ?> Questions</span>
									<?php if ( ! empty( $quiz_meta->time_limit ) ) : ?>
										<span><?php echo (int) $quiz_meta->time_limit; ?> Minutes</span>
									<?php endif; ?>
								</div>
							<?php elseif ( has_category() ) : ?>
								<span class="aqc-quiz-tag">
									<?php echo esc_html( get_the_category()[0]->name ); ?>
								</span>
							<?php endif; ?>

							<h3>
								<a href="<?php the_permalink(); ?>">
									<?php the_title(); ?>
								</a>
							</h3>

							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>

							<div class="aqc-blog-meta">
								<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
									<?php echo esc_html( get_the_date() ); ?>
								</time>
								<span><?php echo esc_html( get_the_author() ); ?></span>
							</div>
						</article>
					<?php endwhile; ?>
				</div>

				<nav class="aqc-pagination" aria-label="<?php esc_attr_e( 'Search pagination', 'astra-quiz-child' ); ?>">
					<?php
					the_posts_pagination( array(
						'mid_size'  => 2,
						'prev_text' => '←',
						'next_text' => '→',
					) );
					?>
				</nav>
			<?php else : ?>
				<p style="text-align:center; color: var(--aqc-muted-text);">
					<?php esc_html_e( 'No results found for your search.', 'astra-quiz-child' ); ?>
				</p>
				<form class="aqc-filter-bar" style="justify-content:center; margin-top: 24px;" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
					<div class="form-group" style="max-width: 400px;">
						<input type="search" name="s" placeholder="<?php esc_attr_e( 'Try a different search...', 'astra-quiz-child' ); ?>" aria-label="<?php esc_attr_e( 'Search', 'astra-quiz-child' ); ?>">
					</div>
					<button type="submit" class="aqc-btn aqc-btn-primary aqc-btn-sm"><?php esc_html_e( 'Search', 'astra-quiz-child' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</section>
</main>

<?php
get_footer();
