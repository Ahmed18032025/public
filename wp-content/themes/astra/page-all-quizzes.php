<?php
/**
 * Template Name: All Quizzes
 * Description: Premium listing of all GMCQ quizzes with search, filter, sort, and pagination.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue All Quizzes assets.
 */
function gmcq_quizzes_enqueue_assets() {
	if ( ! is_page_template( 'page-all-quizzes.php' ) ) {
		return;
	}

	// Font Awesome 6 (already enqueued by homepage, but safe to declare dependency)
	if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
			array(),
			'6.5.1'
		);
	}

	// Homepage CSS (reuse variables & base styles)
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

	// Quizzes page CSS
	$quizzes_css = get_stylesheet_directory() . '/gmcq-quizzes.css';
	if ( file_exists( $quizzes_css ) ) {
		wp_enqueue_style(
			'gmcq-quizzes',
			get_stylesheet_directory_uri() . '/gmcq-quizzes.css',
			array( 'gmcq-homepage' ),
			filemtime( $quizzes_css )
		);
	}

	// Quizzes page JS
	$quizzes_js = get_stylesheet_directory() . '/gmcq-quizzes.js';
	if ( file_exists( $quizzes_js ) ) {
		wp_enqueue_script(
			'gmcq-quizzes',
			get_stylesheet_directory_uri() . '/gmcq-quizzes.js',
			array(), // No jQuery
			filemtime( $quizzes_js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		// Localize script with AJAX URL and nonce
		wp_localize_script(
			'gmcq-quizzes',
			'gmcqQuizzes',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gmcq_quizzes_nonce' ),
				'labels'  => array(
					'attempt'      => __( 'Attempt Now', 'astra' ),
					'resume'       => __( 'Resume Attempt', 'astra' ),
					'requiresLogin' => __( 'Requires Login', 'astra' ),
					'questions'    => __( 'Questions', 'astra' ),
					'min'          => __( 'min', 'astra' ),
					'noLimit'      => __( 'No Limit', 'astra' ),
					'noResults'    => __( 'No quizzes match your search criteria.', 'astra' ),
					'noQuizzes'    => __( 'No quizzes published yet. Check back soon!', 'astra' ),
					'loadMore'     => __( 'Load More', 'astra' ),
					'loading'      => __( 'Loading...', 'astra' ),
					'error'        => __( 'Something went wrong. Please try again.', 'astra' ),
					'by'           => __( 'by', 'astra' ),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gmcq_quizzes_enqueue_assets' );

// ============================================================
// AJAX HANDLER: Fetch filtered quizzes
// ============================================================
add_action( 'wp_ajax_gmcq_filter_quizzes', 'gmcq_ajax_filter_quizzes' );
add_action( 'wp_ajax_nopriv_gmcq_filter_quizzes', 'gmcq_ajax_filter_quizzes' );

/**
 * AJAX handler that returns filtered quiz cards + pagination HTML.
 */
function gmcq_ajax_filter_quizzes() {
	check_ajax_referer( 'gmcq_quizzes_nonce', 'nonce' );

	$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$category_id = isset( $_POST['category_id'] ) ? (int) $_POST['category_id'] : 0;
	$sort        = isset( $_POST['sort'] ) ? sanitize_key( $_POST['sort'] ) : 'newest';
	$page        = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;
	$per_page    = 12;

	global $wpdb;
	$p = $wpdb->prefix;

	$where   = array( "zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish'" );
	$prepare = array();

	// Category filter
	if ( $category_id > 0 ) {
		// Include child categories
		$cat_ids = array( $category_id );
		$children = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$p}gmcq_categories WHERE parent_id = %d AND is_active = 1",
				$category_id
			)
		);
		if ( ! empty( $children ) ) {
			$cat_ids = array_merge( $cat_ids, array_map( 'intval', $children ) );
		}
		$placeholders = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );
		$where[]      = "zm.category_id IN ({$placeholders})";
		$prepare      = array_merge( $prepare, $cat_ids );
	}

	// Search filter
	if ( ! empty( $search ) ) {
		$like      = '%' . $wpdb->esc_like( $search ) . '%';
		$where[]   = 'p.post_title LIKE %s';
		$prepare[] = $like;
	}

	$where_clause = implode( ' AND ', $where );

	// Count total
	$total = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE {$where_clause}",
			$prepare
		)
	);

	// Sort
	switch ( $sort ) {
		case 'popular':
			$order = 'zm.attempt_count DESC, p.post_title ASC';
			break;
		case 'name':
			$order = 'p.post_title ASC';
			break;
		case 'newest':
		default:
			$order = 'zm.created_at DESC, p.post_title ASC';
			break;
	}

	$offset = ( $page - 1 ) * $per_page;
	$prepare[] = $per_page;
	$prepare[] = $offset;

	$quizzes = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT zm.*, p.post_title, p.post_name, c.name AS category_name, c.slug AS category_slug
			 FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 LEFT JOIN {$p}gmcq_categories c ON c.id = zm.category_id
			 WHERE {$where_clause}
			 ORDER BY {$order}
			 LIMIT %d OFFSET %d",
			$prepare
		)
	);

	$total_pages = max( 1, ceil( $total / $per_page ) );

	// Build cards HTML
	$cards_html = '';
	if ( empty( $quizzes ) ) {
		$cards_html = '<div class="gmcq-q-empty">
			<i class="fas fa-search" aria-hidden="true"></i>
			<h3>' . esc_html__( 'No Quizzes Found', 'astra' ) . '</h3>
			<p>' . esc_html__( 'No quizzes match your search criteria.', 'astra' ) . '</p>
		</div>';
	} else {
		foreach ( $quizzes as $quiz ) {
			$cards_html .= gmcq_render_quiz_card( $quiz );
		}
	}

	// Build pagination HTML
	$pagination_html = '';
	if ( $total_pages > 1 ) {
		$pagination_html = '<div class="gmcq-q-pagination" data-total-pages="' . esc_attr( $total_pages ) . '" data-current="' . esc_attr( $page ) . '">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$active = $i === $page ? ' class="active"' : '';
			$pagination_html .= '<a href="#" data-page="' . $i . '"' . $active . '>' . $i . '</a>';
		}
		$pagination_html .= '</div>';
	}

	wp_send_json_success(
		array(
			'cards'      => $cards_html,
			'pagination' => $pagination_html,
			'total'      => $total,
			'totalPages' => $total_pages,
			'current'    => $page,
			'hasMore'    => $page < $total_pages,
		)
	);
}

// ============================================================
// HELPER: Render a single quiz card
// ============================================================

/**
 * Render a premium quiz card
 * @param object $quiz Quiz row from DB.
 * @return string HTML.
 */
function gmcq_render_quiz_card( $quiz ) {
	$quiz_id     = (int) $quiz->quiz_id;
	$title       = $quiz->post_title ?? get_the_title( $quiz_id );
	$q_count     = (int) $quiz->question_count;
	$time_limit  = (int) $quiz->time_limit;
	$attempt_cnt = (int) $quiz->attempt_count;
	$pass_pct    = (float) $quiz->pass_percentage;
	$cat_name    = $quiz->category_name ?? '';
	$cat_slug    = $quiz->category_slug ?? '';
	$require_login = (int) $quiz->require_login;

	// Smart difficulty
	if ( $q_count <= 15 ) {
		$difficulty = 'easy';
	} elseif ( $q_count <= 40 ) {
		$difficulty = 'medium';
	} else {
		$difficulty = 'hard';
	}

	$difficulty_class = 'difficulty-' . $difficulty;
	$difficulty_label = ucfirst( $difficulty );

	// Category icon
	$icon = 'fa-book-open';
	$name_lower = strtolower( $cat_name );
	$icon_map = array(
		'ssc'       => 'fa-graduation-cap',
		'upsc'      => 'fa-landmark',
		'railway'   => 'fa-train',
		'rrb'       => 'fa-train',
		'banking'   => 'fa-university',
		'ibps'      => 'fa-university',
		'sbi'       => 'fa-university',
		'defence'   => 'fa-shield-halved',
		'nda'       => 'fa-shield-halved',
		'cds'       => 'fa-shield-halved',
		'teaching'  => 'fa-chalkboard-user',
		'ctet'      => 'fa-chalkboard-user',
		'police'    => 'fa-shield',
		'english'   => 'fa-language',
		'math'      => 'fa-calculator',
		'reasoning' => 'fa-brain',
		'gk'        => 'fa-globe',
		'science'   => 'fa-flask',
		'history'   => 'fa-clock-rotate-left',
		'geography' => 'fa-map-location-dot',
		'polity'    => 'fa-scale-balanced',
		'economics' => 'fa-chart-line',
		'computer'  => 'fa-laptop-code',
		'hindi'     => 'fa-font',
	);
	foreach ( $icon_map as $keyword => $icn ) {
		if ( strpos( $name_lower, $keyword ) !== false ) {
			$icon = $icn;
			break;
		}
	}

	$permalink = get_permalink( $quiz_id ) ? get_permalink( $quiz_id ) : home_url( '/quiz/' . $quiz_id );
	$time_text = $time_limit > 0 ? $time_limit . ' ' . __( 'min', 'astra' ) : __( 'No Limit', 'astra' );

	$login_badge = $require_login ? '<span class="gmcq-q-card-badge gmcq-q-badge-login"><i class="fas fa-lock" aria-hidden="true"></i> ' . esc_html__( 'Login Required', 'astra' ) . '</span>' : '';

	ob_start();
	?>
	<div class="gmcq-q-card" data-quiz-id="<?php echo esc_attr( $quiz_id ); ?>">
		<div class="gmcq-q-card-top">
			<div class="gmcq-q-card-badges">
				<?php if ( $cat_name ) : ?>
					<span class="gmcq-q-card-badge gmcq-q-badge-category">
						<i class="fas <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
						<?php echo esc_html( $cat_name ); ?>
					</span>
				<?php endif; ?>
				<span class="gmcq-q-card-badge <?php echo esc_attr( $difficulty_class ); ?>">
					<?php echo esc_html( $difficulty_label ); ?>
				</span>
				<?php echo $login_badge; ?>
			</div>
			<?php if ( $attempt_cnt > 0 ) : ?>
				<span class="gmcq-q-card-attempts">
					<i class="fas fa-users" aria-hidden="true"></i>
					<?php echo esc_html( number_format( $attempt_cnt ) ); ?>
				</span>
			<?php endif; ?>
		</div>

		<h3 class="gmcq-q-card-title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>

		<div class="gmcq-q-card-meta">
			<span class="gmcq-q-meta-item">
				<i class="fas fa-question-circle" aria-hidden="true"></i>
				<?php echo esc_html( number_format( $q_count ) ); ?> <?php esc_html_e( 'Questions', 'astra' ); ?>
			</span>
			<span class="gmcq-q-meta-item">
				<i class="fas fa-clock" aria-hidden="true"></i>
				<?php echo esc_html( $time_text ); ?>
			</span>
			<?php if ( $pass_pct > 0 ) : ?>
				<span class="gmcq-q-meta-item">
					<i class="fas fa-check-circle" aria-hidden="true"></i>
					<?php echo esc_html( number_format( $pass_pct, 0 ) ); ?>% <?php esc_html_e( 'Pass', 'astra' ); ?>
				</span>
			<?php endif; ?>
		</div>

		<a href="<?php echo esc_url( $permalink ); ?>" class="gmcq-btn gmcq-btn-primary gmcq-btn-full">
			<?php esc_html_e( 'Attempt Now', 'astra' ); ?>
			<i class="fas fa-arrow-right" aria-hidden="true"></i>
		</a>
	</div>
	<?php
	return ob_get_clean();
}

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main gmcq-homepage gmcq-quizzes-page">

		<?php
		// ============================================================
		// FETCH INITIAL DATA
		// ============================================================
		global $wpdb;
		$p = $wpdb->prefix;

		// Stats
		$stats = array(
			'total_quizzes'  => 0,
			'total_questions' => 0,
			'total_categories' => 0,
		);
		$stats['total_quizzes'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish'"
		);
		$stats['total_questions'] = (int) $wpdb->get_var(
			"SELECT COALESCE(SUM(zm.question_count), 0) FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish'"
		);
		$stats['total_categories'] = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT zm.category_id) FROM {$p}gmcq_quizzes_meta zm
			 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
			 WHERE zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish' AND zm.category_id IS NOT NULL"
		);

		// Categories for filter dropdown
		$categories = array();
		if ( function_exists( 'gmcq_get_categories' ) ) {
			$cat_result = gmcq_get_categories(
				array(
					'filter'   => 'active',
					'per_page' => -1,
					'orderby'  => 'name',
					'order'    => 'ASC',
				)
			);
			if ( ! empty( $cat_result['categories'] ) ) {
				$categories = $cat_result['categories'];
			}
		}

		// Initial quizzes (page 1)
		$per_page   = 12;
		$total      = $stats['total_quizzes'];
		$total_pages = max( 1, ceil( $total / $per_page ) );
		$quizzes    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT zm.*, p.post_title, p.post_name, c.name AS category_name, c.slug AS category_slug
				 FROM {$p}gmcq_quizzes_meta zm
				 JOIN {$wpdb->posts} p ON p.ID = zm.quiz_id
				 LEFT JOIN {$p}gmcq_categories c ON c.id = zm.category_id
				 WHERE zm.status = 'published' AND zm.is_active = 1 AND p.post_status = 'publish'
				 ORDER BY zm.created_at DESC
				 LIMIT %d OFFSET 0",
				$per_page
			)
		);
		?>

		<!-- ============================================================ -->
		<!-- SECTION: PAGE HEADER -->
		<!-- ============================================================ -->
		<section class="gmcq-q-header">
			<div class="gmcq-container">
				<nav class="gmcq-q-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'astra' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'astra' ); ?></a>
					<i class="fas fa-chevron-right" aria-hidden="true"></i>
					<span><?php esc_html_e( 'All Quizzes', 'astra' ); ?></span>
				</nav>

				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Practice Tests', 'astra' ); ?></span>
					<h1 class="gmcq-section-title"><?php esc_html_e( 'All Quizzes', 'astra' ); ?></h1>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Browse all available mock tests and practice quizzes. Filter by category or search by name.', 'astra' ); ?></p>
				</div>

				<div class="gmcq-q-stats gmcq-animate-fade-in-up">
					<div class="gmcq-q-stat-item">
						<span class="gmcq-q-stat-number" id="gmcq-q-total"><?php echo esc_html( number_format( $stats['total_quizzes'] ) ); ?></span>
						<span class="gmcq-q-stat-label"><?php esc_html_e( 'Total Quizzes', 'astra' ); ?></span>
					</div>
					<div class="gmcq-q-stat-item">
						<span class="gmcq-q-stat-number"><?php echo esc_html( number_format( $stats['total_questions'] ) ); ?></span>
						<span class="gmcq-q-stat-label"><?php esc_html_e( 'Total Questions', 'astra' ); ?></span>
					</div>
					<div class="gmcq-q-stat-item">
						<span class="gmcq-q-stat-number"><?php echo esc_html( number_format( $stats['total_categories'] ) ); ?></span>
						<span class="gmcq-q-stat-label"><?php esc_html_e( 'Categories', 'astra' ); ?></span>
					</div>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION: FILTER BAR -->
		<!-- ============================================================ -->
		<section class="gmcq-q-filters" id="gmcq-q-filters">
			<div class="gmcq-container">
				<div class="gmcq-q-filter-bar gmcq-animate-fade-in-up">
					<div class="gmcq-q-search-wrap">
						<i class="fas fa-search gmcq-q-search-icon" aria-hidden="true"></i>
						<input type="text" id="gmcq-q-search" class="gmcq-q-search-input" placeholder="<?php esc_attr_e( 'Search quizzes...', 'astra' ); ?>" aria-label="<?php esc_attr_e( 'Search quizzes', 'astra' ); ?>">
					</div>

					<div class="gmcq-q-select-wrap">
						<i class="fas fa-folder-open gmcq-q-select-icon" aria-hidden="true"></i>
						<select id="gmcq-q-category" aria-label="<?php esc_attr_e( 'Filter by category', 'astra' ); ?>">
							<option value="0"><?php esc_html_e( 'All Categories', 'astra' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="gmcq-q-select-wrap">
						<i class="fas fa-sort gmcq-q-select-icon" aria-hidden="true"></i>
						<select id="gmcq-q-sort" aria-label="<?php esc_attr_e( 'Sort quizzes', 'astra' ); ?>">
							<option value="newest"><?php esc_html_e( 'Newest First', 'astra' ); ?></option>
							<option value="popular"><?php esc_html_e( 'Most Popular', 'astra' ); ?></option>
							<option value="name"><?php esc_html_e( 'Name (A-Z)', 'astra' ); ?></option>
						</select>
					</div>

					<button class="gmcq-q-view-toggle" id="gmcq-q-view-toggle" aria-label="<?php esc_attr_e( 'Toggle list view', 'astra' ); ?>">
						<i class="fas fa-list" aria-hidden="true"></i>
					</button>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION: QUIZZES GRID -->
		<!-- ============================================================ -->
		<section class="gmcq-q-grid-section" id="gmcq-q-grid-section">
			<div class="gmcq-container">
				<div id="gmcq-q-grid" class="gmcq-q-grid <?php echo $total > 0 ? '' : 'gmcq-q-grid-empty'; ?>">
					<?php if ( empty( $quizzes ) ) : ?>
						<div class="gmcq-q-empty">
							<i class="fas fa-file-alt" aria-hidden="true"></i>
							<h3><?php esc_html_e( 'No Quizzes Yet', 'astra' ); ?></h3>
							<p><?php esc_html_e( 'No quizzes published yet. Check back soon!', 'astra' ); ?></p>
						</div>
					<?php else : ?>
						<?php foreach ( $quizzes as $quiz ) : ?>
							<?php echo gmcq_render_quiz_card( $quiz ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Loading Spinner -->
				<div id="gmcq-q-loading" class="gmcq-q-loading" style="display:none;">
					<div class="gmcq-q-spinner"></div>
					<span><?php esc_html_e( 'Loading quizzes...', 'astra' ); ?></span>
				</div>

				<!-- Pagination -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="gmcq-q-pagination" id="gmcq-q-pagination" data-total-pages="<?php echo esc_attr( $total_pages ); ?>" data-current="1">
						<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
							<a href="#" data-page="<?php echo esc_attr( $i ); ?>" class="<?php echo 1 === $i ? 'active' : ''; ?>"><?php echo esc_html( $i ); ?></a>
						<?php endfor; ?>
					</div>
				<?php endif; ?>

				<!-- Load More -->
				<?php if ( $total_pages > 1 ) : ?>
					<div class="gmcq-q-loadmore-wrap" id="gmcq-q-loadmore-wrap">
						<button id="gmcq-q-loadmore" class="gmcq-btn gmcq-btn-outline gmcq-btn-large">
							<i class="fas fa-chevron-down" aria-hidden="true"></i>
							<?php esc_html_e( 'Load More', 'astra' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();