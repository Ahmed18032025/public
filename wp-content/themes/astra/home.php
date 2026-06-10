<?php
/**
 * Blog Index Template - Government MCQ Blog
 *
 * Premium blog listing page. WordPress auto-detects home.php
 * and uses it for the blog posts index page.
 * Does NOT interfere with front-page.php, page.php, single.php, or archive.php.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue blog assets.
 */
function gmcq_blog_enqueue_assets() {
	// Only on blog index
	if ( ! is_home() && ! is_front_page() ) {
		return;
	}
	// Don't enqueue on front page if it's not the blog
	if ( is_front_page() && ! is_home() ) {
		return;
	}

	// Font Awesome
	if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
			array(),
			'6.5.1'
		);
	}

	// Homepage CSS (reuse variables)
	if ( ! wp_style_is( 'gmcq-homepage', 'enqueued' ) ) {
		$homepage_css = get_stylesheet_directory() . '/gmcq-homepage.css';
		if ( file_exists( $homepage_css ) ) {
			wp_enqueue_style(
				'gmcq-homepage',
				get_stylesheet_directory_uri() . '/gmcq-homepage.css',
				array( 'font-awesome' ),
				filemtime( $homepage_css )
			);
		}
	}

	// Blog CSS
	$blog_css = get_stylesheet_directory() . '/gmcq-blog.css';
	if ( file_exists( $blog_css ) ) {
		wp_enqueue_style(
			'gmcq-blog',
			get_stylesheet_directory_uri() . '/gmcq-blog.css',
			array( 'gmcq-homepage' ),
			filemtime( $blog_css )
		);
	}

	// Blog JS
	$blog_js = get_stylesheet_directory() . '/gmcq-blog.js';
	if ( file_exists( $blog_js ) ) {
		wp_enqueue_script(
			'gmcq-blog',
			get_stylesheet_directory_uri() . '/gmcq-blog.js',
			array(), // No jQuery
			filemtime( $blog_js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gmcq_blog_enqueue_assets' );

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main gmcq-homepage gmcq-blog-page">

		<?php
		// Fetch blog stats
		$blog_total   = (int) wp_count_posts()->publish;
		$blog_cats    = wp_count_terms( array( 'taxonomy' => 'category', 'hide_empty' => true ) );
		$blog_tags    = wp_count_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => true ) );
		$blog_authors = count_users();
		$blog_author_count = (int) ( $blog_authors['total_users'] ?? 0 );

		// Get all categories for filter
		$all_categories = get_categories( array( 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC' ) );

		// Pagination
		$paged    = max( 1, get_query_var( 'paged' ) );
		$per_page = get_option( 'posts_per_page', 9 );
		$offset   = ( $paged - 1 ) * $per_page;
		$total_pages = max( 1, ceil( $blog_total / $per_page ) );

		// WP_Query
		$blog_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$blog_query = new WP_Query( $blog_args );
		?>

		<!-- ============================================================ -->
		<!-- HEADER: Blog Hero -->
		<!-- ============================================================ -->
		<section class="gmcq-blog-header">
			<div class="gmcq-container">
				<nav class="gmcq-blog-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'astra' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'astra' ); ?></a>
					<i class="fas fa-chevron-right" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Blog', 'astra' ); ?></span>
				</nav>

				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Our Blog', 'astra' ); ?></span>
					<h1 class="gmcq-section-title"><?php esc_html_e( 'Latest Updates & Articles', 'astra' ); ?></h1>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Stay updated with exam notifications, study tips, and important announcements.', 'astra' ); ?></p>
				</div>

				<div class="gmcq-blog-stats gmcq-animate-fade-in-up">
					<div class="gmcq-blog-stat-item">
						<span class="gmcq-blog-stat-number"><?php echo esc_html( number_format( $blog_total ) ); ?></span>
						<span class="gmcq-blog-stat-label"><?php esc_html_e( 'Articles', 'astra' ); ?></span>
					</div>
					<div class="gmcq-blog-stat-item">
						<span class="gmcq-blog-stat-number"><?php echo esc_html( number_format( $blog_cats ) ); ?></span>
						<span class="gmcq-blog-stat-label"><?php esc_html_e( 'Categories', 'astra' ); ?></span>
					</div>
					<div class="gmcq-blog-stat-item">
						<span class="gmcq-blog-stat-number"><?php echo esc_html( number_format( $blog_tags ) ); ?></span>
						<span class="gmcq-blog-stat-label"><?php esc_html_e( 'Tags', 'astra' ); ?></span>
					</div>
					<div class="gmcq-blog-stat-item">
						<span class="gmcq-blog-stat-number"><?php echo esc_html( number_format( $blog_author_count ) ); ?></span>
						<span class="gmcq-blog-stat-label"><?php esc_html_e( 'Authors', 'astra' ); ?></span>
					</div>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION: Blog Content (Grid + Sidebar) -->
		<!-- ============================================================ -->
		<section class="gmcq-blog-content">
			<div class="gmcq-container">
				<div class="gmcq-blog-layout">

					<!-- ==================== MAIN CONTENT ==================== -->
					<div class="gmcq-blog-main">

						<!-- Category Filter Pills -->
						<?php if ( ! empty( $all_categories ) ) : ?>
							<div class="gmcq-blog-filters gmcq-animate-fade-in-up">
								<button class="gmcq-blog-filter-pill active" data-category="0">
									<?php esc_html_e( 'All', 'astra' ); ?>
								</button>
								<?php foreach ( $all_categories as $cat ) : ?>
									<button class="gmcq-blog-filter-pill" data-category="<?php echo esc_attr( $cat->term_id ); ?>">
										<?php echo esc_html( $cat->name ); ?>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<!-- Posts Grid -->
						<?php if ( $blog_query->have_posts() ) : ?>
							<div class="gmcq-blog-grid" id="gmcq-blog-grid">
								<?php
								$post_index = 0;
								while ( $blog_query->have_posts() ) :
									$blog_query->the_post();

									$post_id      = get_the_ID();
									$title        = get_the_title();
									$excerpt      = get_the_excerpt() ? wp_trim_words( get_the_excerpt(), 25 ) : wp_trim_words( get_the_content(), 25 );
									$permalink    = get_permalink();
									$author_id    = get_the_author_meta( 'ID' );
									$author_name  = get_the_author();
									$author_avatar = get_avatar_url( $author_id, array( 'size' => 32 ) );
									$date         = get_the_date( 'd M Y' );
									$categories   = get_the_category();
									$first_cat    = ! empty( $categories ) ? $categories[0] : null;
									$has_thumbnail = has_post_thumbnail();
									$thumbnail    = $has_thumbnail ? get_the_post_thumbnail_url( $post_id, 'medium_large' ) : '';
									$reading_time = ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 200 );

									// Category colors
									$cat_colors = array(
										'#0F4C81', '#1E88E5', '#FFC107', '#10B981',
										'#EF4444', '#8B5CF6', '#EC4899', '#F59E0B',
										'#14B8A6', '#6366F1', '#F97316', '#06B6D4',
									);
									$cat_color  = $first_cat ? $cat_colors[ $first_cat->term_id % count( $cat_colors ) ] : '#0F4C81';
									$delay      = ( $post_index % 9 ) * 80;
									$post_index++;
									?>
									<article class="gmcq-blog-card gmcq-animate-fade-in-up" data-delay="<?php echo esc_attr( $delay ); ?>">
										<a href="<?php echo esc_url( $permalink ); ?>" class="gmcq-blog-card-image-link">
											<div class="gmcq-blog-card-image"<?php echo $thumbnail ? ' style="background-image: url(' . esc_url( $thumbnail ) . ');"' : ' style="background: linear-gradient(135deg, ' . esc_attr( $cat_color ) . ', ' . esc_attr( $cat_color ) . 'dd);"'; ?>>
												<?php if ( ! $thumbnail ) : ?>
													<div class="gmcq-blog-card-image-fallback">
														<i class="fas fa-newspaper" aria-hidden="true"></i>
													</div>
												<?php endif; ?>
												<?php if ( $first_cat ) : ?>
													<span class="gmcq-blog-card-badge" style="background: <?php echo esc_attr( $cat_color ); ?>;">
														<?php echo esc_html( $first_cat->name ); ?>
													</span>
												<?php endif; ?>
											</div>
										</a>
										<div class="gmcq-blog-card-body">
											<h3 class="gmcq-blog-card-title">
												<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
											</h3>
											<p class="gmcq-blog-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
											<div class="gmcq-blog-card-footer">
												<div class="gmcq-blog-card-author">
													<?php if ( $author_avatar ) : ?>
														<img src="<?php echo esc_url( $author_avatar ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" class="gmcq-blog-card-avatar" loading="lazy" width="32" height="32">
													<?php else : ?>
														<div class="gmcq-blog-card-avatar gmcq-blog-card-avatar-placeholder"><?php echo esc_html( strtoupper( substr( $author_name, 0, 1 ) ) ); ?></div>
													<?php endif; ?>
													<span class="gmcq-blog-card-author-name"><?php echo esc_html( $author_name ); ?></span>
												</div>
												<div class="gmcq-blog-card-meta">
													<span class="gmcq-blog-card-date">
														<i class="fas fa-calendar-alt" aria-hidden="true"></i>
														<?php echo esc_html( $date ); ?>
													</span>
													<span class="gmcq-blog-card-reading">
														<i class="fas fa-book-open" aria-hidden="true"></i>
														<?php echo esc_html( $reading_time ); ?> <?php esc_html_e( 'min read', 'astra' ); ?>
													</span>
												</div>
											</div>
											<a href="<?php echo esc_url( $permalink ); ?>" class="gmcq-blog-card-cta">
												<?php esc_html_e( 'Read More', 'astra' ); ?>
												<i class="fas fa-arrow-right" aria-hidden="true"></i>
											</a>
										</div>
									</article>
								<?php endwhile; ?>
								<?php wp_reset_postdata(); ?>
							</div>

							<!-- Pagination -->
							<?php if ( $total_pages > 1 ) : ?>
								<nav class="gmcq-blog-pagination" aria-label="<?php esc_attr_e( 'Blog pagination', 'astra' ); ?>">
									<?php
									echo paginate_links(
										array(
											'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
											'format'    => '?paged=%#%',
											'current'   => $paged,
											'total'     => $total_pages,
											'prev_text' => '<i class="fas fa-chevron-left" aria-hidden="true"></i> ' . __( 'Previous', 'astra' ),
											'next_text' => __( 'Next', 'astra' ) . ' <i class="fas fa-chevron-right" aria-hidden="true"></i>',
											'type'      => 'list',
										)
									);
									?>
								</nav>
							<?php endif; ?>

						<?php else : ?>
							<div class="gmcq-blog-empty">
								<i class="fas fa-newspaper" aria-hidden="true"></i>
								<h3><?php esc_html_e( 'No Posts Yet', 'astra' ); ?></h3>
								<p><?php esc_html_e( 'No blog posts have been published yet. Check back soon for updates and articles.', 'astra' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<!-- ==================== SIDEBAR ==================== -->
					<aside class="gmcq-blog-sidebar" role="complementary">
						<div class="gmcq-blog-sidebar-inner">

							<!-- Search Widget -->
							<div class="gmcq-blog-widget">
								<h3 class="gmcq-blog-widget-title"><?php esc_html_e( 'Search', 'astra' ); ?></h3>
								<form role="search" method="get" class="gmcq-blog-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
									<div class="gmcq-blog-search-wrap">
										<i class="fas fa-search" aria-hidden="true"></i>
										<input type="search" class="gmcq-blog-search-input" placeholder="<?php esc_attr_e( 'Search articles...', 'astra' ); ?>" value="<?php echo get_search_query(); ?>" name="s">
									</div>
								</form>
							</div>

							<!-- Recent Posts Widget -->
							<div class="gmcq-blog-widget">
								<h3 class="gmcq-blog-widget-title"><?php esc_html_e( 'Recent Posts', 'astra' ); ?></h3>
								<ul class="gmcq-blog-widget-list">
									<?php
									$recent_posts = get_posts(
										array(
											'numberposts' => 5,
											'post_status' => 'publish',
										)
									);
									foreach ( $recent_posts as $rp ) :
										setup_postdata( $rp );
										?>
										<li>
											<a href="<?php echo esc_url( get_permalink( $rp->ID ) ); ?>">
												<?php if ( has_post_thumbnail( $rp->ID ) ) : ?>
													<img src="<?php echo esc_url( get_the_post_thumbnail_url( $rp->ID, 'thumbnail' ) ); ?>" alt="<?php echo esc_attr( get_the_title( $rp->ID ) ); ?>" loading="lazy" width="60" height="60">
												<?php endif; ?>
												<div class="gmcq-blog-widget-post-info">
													<span class="gmcq-blog-widget-post-title"><?php echo esc_html( get_the_title( $rp->ID ) ); ?></span>
													<span class="gmcq-blog-widget-post-date"><?php echo esc_html( get_the_date( 'd M Y', $rp->ID ) ); ?></span>
												</div>
											</a>
										</li>
									<?php endforeach; ?>
									<?php wp_reset_postdata(); ?>
								</ul>
							</div>

							<!-- Categories Widget -->
							<?php if ( ! empty( $all_categories ) ) : ?>
								<div class="gmcq-blog-widget">
									<h3 class="gmcq-blog-widget-title"><?php esc_html_e( 'Categories', 'astra' ); ?></h3>
									<ul class="gmcq-blog-widget-categories">
										<?php foreach ( $all_categories as $cat ) : ?>
											<li>
												<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>">
													<span><?php echo esc_html( $cat->name ); ?></span>
													<span class="gmcq-blog-widget-count"><?php echo esc_html( $cat->count ); ?></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>

							<!-- Tags Widget -->
							<?php
							$all_tags = get_tags( array( 'hide_empty' => true, 'number' => 20 ) );
							if ( ! empty( $all_tags ) ) :
								?>
								<div class="gmcq-blog-widget">
									<h3 class="gmcq-blog-widget-title"><?php esc_html_e( 'Tags', 'astra' ); ?></h3>
									<div class="gmcq-blog-widget-tags">
										<?php foreach ( $all_tags as $tag ) : ?>
											<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>" class="gmcq-blog-tag">
												<?php echo esc_html( $tag->name ); ?>
											</a>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

						</div>
					</aside>

				</div>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();