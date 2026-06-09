<?php
/**
 * Template: All Quizzes
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

	get_header();

	$paged = max( 1, get_query_var( 'paged', 1 ) );
	if ( $paged > 1 ) {
		echo '<link rel="canonical" href="' . esc_url( add_query_arg( 'paged', $paged, get_permalink() ) ) . '">' . "\n";
	}
	?>
?>

<main id="primary" class="aqc-page-wrapper">
	<?php get_template_part( 'aqc-page-header', null, array(
		'title' => __( 'All Quizzes', 'astra-quiz-child' ),
		'description' => __( 'Browse all available quiz sets, filter by category and practice for your exam.', 'astra-quiz-child' ),
	) ); ?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<?php if ( function_exists( 'gmcq_get_category_tree' ) ) : ?>
			<?php $category_tree = gmcq_get_category_tree( array( 'filter' => 'active' ) ); ?>
			<?php endif; ?>

			<form class="aqc-filter-bar" id="aqc-quiz-filters" aria-label="<?php esc_attr_e( 'Filter quizzes', 'astra-quiz-child' ); ?>">
				<div class="form-group">
					<label for="aqc-search"><?php esc_html_e( 'Search', 'astra-quiz-child' ); ?></label>
					<input type="search" id="aqc-search" name="search" placeholder="<?php esc_attr_e( 'Search quizzes...', 'astra-quiz-child' ); ?>" autocomplete="off">
				</div>
				<div class="form-group">
					<label for="aqc-category"><?php esc_html_e( 'Category', 'astra-quiz-child' ); ?></label>
					<select id="aqc-category" name="category_id">
						<option value=""><?php esc_html_e( 'All Categories', 'astra-quiz-child' ); ?></option>
						<?php if ( ! empty( $category_tree ) ) : ?>
							<?php foreach ( $category_tree as $cat ) : ?>
								<option value="<?php echo (int) $cat->id; ?>"><?php echo esc_html( $cat->name ); ?></option>
								<?php if ( ! empty( $cat->children ) ) : ?>
									<?php foreach ( $cat->children as $child ) : ?>
										<option value="<?php echo (int) $child->id; ?>">— <?php echo esc_html( $child->name ); ?></option>
									<?php endforeach; ?>
								<?php endif; ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</div>
				<div class="form-group">
					<label for="aqc-sort"><?php esc_html_e( 'Sort By', 'astra-quiz-child' ); ?></label>
					<select id="aqc-sort" name="sort_by">
						<option value="popular"><?php esc_html_e( 'Most Popular', 'astra-quiz-child' ); ?></option>
						<option value="newest"><?php esc_html_e( 'Newest', 'astra-quiz-child' ); ?></option>
						<option value="name"><?php esc_html_e( 'Name A-Z', 'astra-quiz-child' ); ?></option>
					</select>
				</div>
				<button type="button" class="aqc-clear-filters" id="aqc-clear-filters"><?php esc_html_e( 'Clear', 'astra-quiz-child' ); ?></button>
			</form>

			<div id="aqc-quiz-grid" class="aqc-grid-3" role="list" aria-live="polite">
				<?php
				$quizzes = function_exists( 'gmcq_get_top_quizzes' ) ? gmcq_get_top_quizzes( 12 ) : array();

				if ( ! empty( $quizzes ) ) :
					foreach ( $quizzes as $quiz ) :
						$tag = ! empty( $quiz->category_name ) ? esc_html( $quiz->category_name ) : __( 'Mock Test', 'astra-quiz-child' );
						?>
						<article class="aqc-quiz-card" role="listitem" data-quiz-id="<?php echo (int) $quiz->quiz_id; ?>" data-analytics='{"quiz_id":<?php echo (int) $quiz->quiz_id; ?>,"quiz_title":"<?php echo esc_js( $quiz->post_title ); ?>","category":"<?php echo esc_js( $tag ); ?>"}'>
							<span class="aqc-quiz-tag"><?php echo $tag; ?></span>
							<h3>
								<a href="<?php echo esc_url( get_permalink( $quiz->quiz_id ) ); ?>">
									<?php echo esc_html( $quiz->post_title ); ?>
								</a>
							</h3>
							<p>Practice with <?php echo (int) $quiz->question_count; ?> questions in a timed environment.</p>
							<div class="aqc-quiz-meta">
								<span><?php echo (int) $quiz->question_count; ?> Questions</span>
								<?php if ( ! empty( $quiz->time_limit ) ) : ?>
									<span><?php echo (int) $quiz->time_limit; ?> Minutes</span>
								<?php endif; ?>
								<?php if ( ! empty( $quiz->attempt_count ) ) : ?>
									<span><?php echo (int) $quiz->attempt_count; ?> Attempts</span>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach;
				else : ?>
					<p class="aqc-no-results"><?php esc_html_e( 'No quizzes available yet. Please check back soon.', 'astra-quiz-child' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="aqc-pagination" id="aqc-quiz-pagination" aria-label="<?php esc_attr_e( 'Quiz pagination', 'astra-quiz-child' ); ?>">
				<?php
				$paged = max( 1, get_query_var( 'paged', 1 ) );
				$total_pages = 5;

				if ( $paged > 1 ) :
					?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Previous page', 'astra-quiz-child' ); ?>">←</a>
				<?php endif; ?>

				<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
					<?php if ( $i === (int) $paged ) : ?>
						<span class="current" aria-current="page"><?php echo (int) $i; ?></span>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>"><?php echo (int) $i; ?></a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ( $paged < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1 ) ); ?>" aria-label="<?php esc_attr_e( 'Next page', 'astra-quiz-child' ); ?>">→</a>
				<?php endif; ?>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
