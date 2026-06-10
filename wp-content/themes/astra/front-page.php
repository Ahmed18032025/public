<?php
/**
 * Front Page Template - Government MCQ Homepage
 *
 * Premium, modern, fully responsive WordPress homepage for Government MCQ.
 * Dynamically fetches all data from the GMCQ plugin.
 * No hardcoded categories - everything auto-updates from GMCQ.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue homepage assets.
 * Uses WordPress standard enqueue functions.
 * Font Awesome loaded from CDN for performance.
 */
function gmcq_homepage_enqueue_assets() {
	// Only enqueue on front page
	if ( ! is_front_page() ) {
		return;
	}

	// Font Awesome 6 Free (CDN)
	wp_enqueue_style(
		'font-awesome',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);

	// Homepage styles
	wp_enqueue_style(
		'gmcq-homepage',
		get_stylesheet_directory_uri() . '/gmcq-homepage.css',
		array( 'font-awesome' ),
		filemtime( get_stylesheet_directory() . '/gmcq-homepage.css' )
	);

	// Homepage JavaScript
	wp_enqueue_script(
		'gmcq-homepage',
		get_stylesheet_directory_uri() . '/gmcq-homepage.js',
		array(), // No jQuery dependency
		filemtime( get_stylesheet_directory() . '/gmcq-homepage.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'wp_enqueue_scripts', 'gmcq_homepage_enqueue_assets' );

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main gmcq-homepage">

		<?php
		// ============================================================
		// HELPER FUNCTIONS
		// ============================================================

		/**
		 * Get Font Awesome icon class for a category based on its name.
		 * This is a smart fallback mapping when no icon field exists in DB.
		 *
		 * @param string $category_name The category name.
		 * @return string Font Awesome icon class.
		 */
		function gmcq_home_get_category_icon( $category_name ) {
			$name = strtolower( $category_name );
			$icon_map = array(
				'ssc'          => 'fa-graduation-cap',
				'upsc'         => 'fa-landmark',
				'railway'      => 'fa-train',
				'rrb'          => 'fa-train',
				'banking'      => 'fa-university',
				'ibps'         => 'fa-university',
				'sbi'          => 'fa-university',
				'rbi'          => 'fa-university',
				'defence'      => 'fa-shield-halved',
				'nda'          => 'fa-shield-halved',
				'cds'          => 'fa-shield-halved',
				'air force'    => 'fa-plane',
				'navy'         => 'fa-ship',
				'army'         => 'fa-shield-halved',
				'teaching'     => 'fa-chalkboard-user',
				'ctet'         => 'fa-chalkboard-user',
				'uptet'        => 'fa-chalkboard-user',
				'state psc'    => 'fa-building-columns',
				'uppsc'        => 'fa-building-columns',
				'mppsc'        => 'fa-building-columns',
				'bpsc'         => 'fa-building-columns',
				'rpsc'         => 'fa-building-columns',
				'police'       => 'fa-shield',
				'general'      => 'fa-book-open',
				'english'      => 'fa-language',
				'math'         => 'fa-calculator',
				'reasoning'    => 'fa-brain',
				'gk'           => 'fa-globe',
				'current affairs' => 'fa-newspaper',
				'science'      => 'fa-flask',
				'history'      => 'fa-clock-rotate-left',
				'geography'    => 'fa-map-location-dot',
				'polity'       => 'fa-scale-balanced',
				'economics'    => 'fa-chart-line',
				'computer'     => 'fa-laptop-code',
				'hindi'        => 'fa-font',
			);

			foreach ( $icon_map as $keyword => $icon ) {
				if ( strpos( $name, $keyword ) !== false ) {
					return $icon;
				}
			}

			return 'fa-book-open';
		}

		/**
		 * Get difficulty badge class.
		 *
		 * @param string $difficulty The difficulty level.
		 * @return string CSS class name.
		 */
		function gmcq_home_get_difficulty_class( $difficulty ) {
			$difficulty = strtolower( $difficulty );
			if ( 'easy' === $difficulty ) {
				return 'difficulty-easy';
			} elseif ( 'medium' === $difficulty ) {
				return 'difficulty-medium';
			} elseif ( 'hard' === $difficulty ) {
				return 'difficulty-hard';
			}
			return 'difficulty-medium';
		}

		// ============================================================
		// FETCH DYNAMIC DATA FROM GMCQ
		// ============================================================

		// Categories - active, top-level only for display
		$categories_data = array();
		$categories_total = 0;
		if ( function_exists( 'gmcq_get_categories' ) ) {
			$cats_result = gmcq_get_categories(
				array(
					'filter'      => 'active',
					'parent_only' => true,
					'per_page'    => -1,
					'orderby'     => 'question_count',
					'order'       => 'DESC',
				)
			);
			if ( ! empty( $cats_result['categories'] ) ) {
				$categories_data = $cats_result['categories'];
				$categories_total = (int) $cats_result['total'];
			}
		}

		// Dashboard stats for platform statistics
		$dashboard_stats = array(
			'total_questions'  => 0,
			'total_categories' => 0,
			'total_quizzes'    => 0,
			'total_attempts'   => 0,
		);
		if ( function_exists( 'gmcq_get_dashboard_stats' ) ) {
			$stats = gmcq_get_dashboard_stats();
			if ( ! empty( $stats ) && empty( $stats['_rebuilding'] ) ) {
				$dashboard_stats['total_questions']  = (int) ( $stats['active_questions'] ?? 0 );
				$dashboard_stats['total_categories'] = ( (int) ( $stats['top_level_categories'] ?? 0 ) ) + ( (int) ( $stats['child_categories'] ?? 0 ) );
				$dashboard_stats['total_quizzes']    = (int) ( $stats['published_quizzes'] ?? 0 );
				$dashboard_stats['total_attempts']   = (int) ( $stats['total_attempts'] ?? 0 );
			}
		}

		// Fallback: query directly if dashboard stats not available
		if ( 0 === $dashboard_stats['total_questions'] ) {
			global $wpdb;
			$p = $wpdb->prefix;
			$dashboard_stats['total_questions']  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_questions WHERE is_active = 1" );
			$dashboard_stats['total_categories'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_categories WHERE is_active = 1" );
			$dashboard_stats['total_quizzes']    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_quizzes_meta WHERE status = 'published' AND is_active = 1" );
			$dashboard_stats['total_attempts']   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}gmcq_attempts WHERE status = 'completed' AND is_active = 1" );
		}

		// Featured tests - top quizzes by attempts
		$featured_tests = array();
		if ( function_exists( 'gmcq_get_top_quizzes' ) ) {
			$featured_tests = gmcq_get_top_quizzes( 6 );
		}
		// If top quizzes empty, get published quizzes instead
		if ( empty( $featured_tests ) && function_exists( 'gmcq_get_quizzes' ) ) {
			$quizzes_result = gmcq_get_quizzes(
				array(
					'filter'   => 'published',
					'per_page' => 6,
				)
			);
			if ( ! empty( $quizzes_result['quizzes'] ) ) {
				$featured_tests = $quizzes_result['quizzes'];
			}
		}

		// Latest tests
		$latest_tests = array();
		if ( function_exists( 'gmcq_get_recent_quizzes' ) ) {
			$latest_tests = gmcq_get_recent_quizzes( 4 );
		}

		// Leaderboard data - top attempts
		$leaderboard_data = array();
		global $wpdb;
		$p = $wpdb->prefix;
		$leaderboard_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.user_id, a.score, a.percentage, a.total_questions, a.correct_answers,
				        a.wrong_answers, a.time_taken, a.started_at, a.category_id,
				        u.display_name AS user_name,
				        c.name AS category_name
				 FROM {$p}gmcq_attempts a
				 LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
				 LEFT JOIN {$p}gmcq_categories c ON c.id = a.category_id
				 WHERE a.status = 'completed' AND a.is_active = 1 AND a.user_id IS NOT NULL
				 ORDER BY a.percentage DESC, a.score DESC
				 LIMIT %d",
				10
			)
		) ?: array();

		// Category tree counts
		$tree_counts = array();
		if ( function_exists( 'gmcq_get_category_tree_counts' ) ) {
			$tree_counts = gmcq_get_category_tree_counts();
		}

		// Count registered students
		$registered_users = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT a.user_id) FROM {$p}gmcq_attempts a WHERE a.status = 'completed' AND a.is_active = 1 AND a.user_id IS NOT NULL"
		);
		if ( $registered_users < 1 ) {
			$registered_users = count_users()['total_users'] ?? 0;
		}

		// Check if GMCQ plugin is active
		$gmcq_active = function_exists( 'gmcq_get_categories' );
		?>

		<!-- ============================================================ -->
		<!-- SECTION 1: HERO SECTION -->
		<!-- ============================================================ -->
		<section class="gmcq-hero" id="hero">
			<div class="gmcq-hero-bg">
				<div class="gmcq-hero-shape gmcq-hero-shape-1"></div>
				<div class="gmcq-hero-shape gmcq-hero-shape-2"></div>
				<div class="gmcq-hero-shape gmcq-hero-shape-3"></div>
			</div>
			<div class="gmcq-container">
				<div class="gmcq-hero-content">
					<div class="gmcq-hero-text gmcq-animate-fade-in-up">
						<span class="gmcq-hero-badge"><?php esc_html_e( 'India\'s #1 Exam Preparation Platform', 'astra' ); ?></span>
						<h1 class="gmcq-hero-title"><?php esc_html_e( 'Prepare Smarter for Government Exams', 'astra' ); ?></h1>
						<p class="gmcq-hero-subtitle"><?php esc_html_e( 'Practice thousands of MCQs, attempt mock tests, track your progress, and improve your chances of success.', 'astra' ); ?></p>
						<div class="gmcq-hero-actions">
							<a href="#categories" class="gmcq-btn gmcq-btn-primary gmcq-btn-large">
								<?php esc_html_e( 'Start Practice', 'astra' ); ?>
								<i class="fas fa-arrow-right" aria-hidden="true"></i>
							</a>
							<a href="#features" class="gmcq-btn gmcq-btn-outline gmcq-btn-large">
								<?php esc_html_e( 'Explore Categories', 'astra' ); ?>
								<i class="fas fa-compass" aria-hidden="true"></i>
							</a>
						</div>
						<div class="gmcq-hero-features">
							<div class="gmcq-hero-feature">
								<i class="fas fa-check-circle" aria-hidden="true"></i>
								<span><?php esc_html_e( '10,000+ MCQs', 'astra' ); ?></span>
							</div>
							<div class="gmcq-hero-feature">
								<i class="fas fa-check-circle" aria-hidden="true"></i>
								<span><?php esc_html_e( 'Expert Curated', 'astra' ); ?></span>
							</div>
							<div class="gmcq-hero-feature">
								<i class="fas fa-check-circle" aria-hidden="true"></i>
								<span><?php esc_html_e( 'Free Practice', 'astra' ); ?></span>
							</div>
						</div>
					</div>
					<div class="gmcq-hero-visual gmcq-animate-fade-in-right">
						<div class="gmcq-hero-illustration">
							<div class="gmcq-illustration-card gmcq-illustration-card-1">
								<i class="fas fa-graduation-cap" aria-hidden="true"></i>
								<div class="gmcq-ill-card-content">
									<span class="gmcq-ill-card-number"><?php echo esc_html( number_format( min( $dashboard_stats['total_questions'], 10000 ) ) ); ?>+</span>
									<span class="gmcq-ill-card-label"><?php esc_html_e( 'Practice Questions', 'astra' ); ?></span>
								</div>
							</div>
							<div class="gmcq-illustration-card gmcq-illustration-card-2">
								<i class="fas fa-trophy" aria-hidden="true"></i>
								<div class="gmcq-ill-card-content">
									<span class="gmcq-ill-card-number"><?php echo esc_html( number_format( $dashboard_stats['total_quizzes'] ) ); ?>+</span>
									<span class="gmcq-ill-card-label"><?php esc_html_e( 'Mock Tests', 'astra' ); ?></span>
								</div>
							</div>
							<div class="gmcq-illustration-card gmcq-illustration-card-3">
								<i class="fas fa-users" aria-hidden="true"></i>
								<div class="gmcq-ill-card-content">
									<span class="gmcq-ill-card-number"><?php echo esc_html( number_format( $registered_users ) ); ?>+</span>
									<span class="gmcq-ill-card-label"><?php esc_html_e( 'Active Students', 'astra' ); ?></span>
								</div>
							</div>
							<div class="gmcq-hero-main-illustration">
								<i class="fas fa-book-open" aria-hidden="true"></i>
							</div>
						</div>
					</div>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 2: LIVE PLATFORM STATISTICS -->
		<!-- ============================================================ -->
		<section class="gmcq-stats" id="stats">
			<div class="gmcq-container">
				<div class="gmcq-stats-grid">
					<div class="gmcq-stat-card gmcq-animate-fade-in-up" data-delay="0">
						<div class="gmcq-stat-icon">
							<i class="fas fa-question-circle" aria-hidden="true"></i>
						</div>
						<div class="gmcq-stat-number" data-target="<?php echo esc_attr( $dashboard_stats['total_questions'] ); ?>">0</div>
						<div class="gmcq-stat-label"><?php esc_html_e( 'Total Questions', 'astra' ); ?></div>
					</div>
					<div class="gmcq-stat-card gmcq-animate-fade-in-up" data-delay="100">
						<div class="gmcq-stat-icon">
							<i class="fas fa-folder-open" aria-hidden="true"></i>
						</div>
						<div class="gmcq-stat-number" data-target="<?php echo esc_attr( $dashboard_stats['total_categories'] ); ?>">0</div>
						<div class="gmcq-stat-label"><?php esc_html_e( 'Total Categories', 'astra' ); ?></div>
					</div>
					<div class="gmcq-stat-card gmcq-animate-fade-in-up" data-delay="200">
						<div class="gmcq-stat-icon">
							<i class="fas fa-file-alt" aria-hidden="true"></i>
						</div>
						<div class="gmcq-stat-number" data-target="<?php echo esc_attr( $dashboard_stats['total_quizzes'] ); ?>">0</div>
						<div class="gmcq-stat-label"><?php esc_html_e( 'Total Mock Tests', 'astra' ); ?></div>
					</div>
					<div class="gmcq-stat-card gmcq-animate-fade-in-up" data-delay="300">
						<div class="gmcq-stat-icon">
							<i class="fas fa-user-graduate" aria-hidden="true"></i>
						</div>
						<div class="gmcq-stat-number" data-target="<?php echo esc_attr( $registered_users ); ?>">0</div>
						<div class="gmcq-stat-label"><?php esc_html_e( 'Registered Students', 'astra' ); ?></div>
					</div>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 3: POPULAR EXAM CATEGORIES -->
		<!-- ============================================================ -->
		<section class="gmcq-categories" id="categories">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Categories', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'Popular Exam Categories', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Choose your exam category and start practicing today.', 'astra' ); ?></p>
				</div>

				<?php if ( ! empty( $categories_data ) ) : ?>
					<div class="gmcq-categories-search gmcq-animate-fade-in-up">
						<div class="gmcq-search-wrapper">
							<i class="fas fa-search gmcq-search-icon" aria-hidden="true"></i>
							<input type="text" id="gmcq-category-search" class="gmcq-search-input" placeholder="<?php esc_attr_e( 'Search categories...', 'astra' ); ?>" aria-label="<?php esc_attr_e( 'Search categories', 'astra' ); ?>">
						</div>
					</div>
					<div class="gmcq-categories-grid" id="gmcq-categories-grid">
						<?php foreach ( $categories_data as $index => $category ) : ?>
							<?php
							$icon_class = gmcq_home_get_category_icon( $category->name );
							$q_count    = (int) $category->question_count;
							$test_count = 0;
							if ( ! empty( $tree_counts ) && isset( $tree_counts[ $category->id ] ) ) {
								$q_count = (int) $tree_counts[ $category->id ]->total_count;
							}
							// Count quizzes for this category
							if ( function_exists( 'gmcq_get_quizzes' ) ) {
								$cat_quizzes = gmcq_get_quizzes(
									array(
										'filter'   => 'published',
										'per_page' => -1,
									)
								);
								if ( ! empty( $cat_quizzes['quizzes'] ) ) {
									foreach ( $cat_quizzes['quizzes'] as $qz ) {
										if ( (int) $qz->category_id === (int) $category->id ) {
											$test_count++;
										}
									}
								}
							}
							$delay = $index * 100;
							?>
							<div class="gmcq-category-card gmcq-animate-fade-in-up" data-delay="<?php echo esc_attr( $delay ); ?>" data-category="<?php echo esc_attr( strtolower( $category->name ) ); ?>">
								<div class="gmcq-category-icon">
									<i class="fas <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></i>
								</div>
								<h3 class="gmcq-category-name"><?php echo esc_html( $category->name ); ?></h3>
								<?php if ( ! empty( $category->description ) ) : ?>
									<p class="gmcq-category-desc"><?php echo esc_html( wp_trim_words( $category->description, 15 ) ); ?></p>
								<?php endif; ?>
								<div class="gmcq-category-meta">
									<span class="gmcq-category-meta-item">
										<i class="fas fa-question-circle" aria-hidden="true"></i>
										<?php echo esc_html( number_format( $q_count ) ); ?> <?php esc_html_e( 'Questions', 'astra' ); ?>
									</span>
									<span class="gmcq-category-meta-item">
										<i class="fas fa-file-alt" aria-hidden="true"></i>
										<?php echo esc_html( number_format( $test_count ) ); ?> <?php esc_html_e( 'Tests', 'astra' ); ?>
									</span>
								</div>
								<a href="<?php echo esc_url( get_permalink( get_page_by_path( 'practice' ) ) ? get_permalink( get_page_by_path( 'practice' ) ) : home_url( '/?category=' . $category->slug ) ); ?>" class="gmcq-btn gmcq-btn-sm gmcq-btn-primary">
									<?php esc_html_e( 'Start Practice', 'astra' ); ?>
									<i class="fas fa-arrow-right" aria-hidden="true"></i>
								</a>
							</div>
						<?php endforeach; ?>
					</div>
					<div id="gmcq-no-results" class="gmcq-no-results" style="display:none;">
						<i class="fas fa-search" aria-hidden="true"></i>
						<p><?php esc_html_e( 'No categories found matching your search.', 'astra' ); ?></p>
					</div>
				<?php else : ?>
					<div class="gmcq-empty-state">
						<i class="fas fa-folder-open" aria-hidden="true"></i>
						<h3><?php esc_html_e( 'No Categories Available', 'astra' ); ?></h3>
						<p><?php esc_html_e( 'Categories will appear here once they are added by the administrator.', 'astra' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 4: FEATURED TESTS -->
		<!-- ============================================================ -->
		<section class="gmcq-featured-tests" id="featured-tests">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Featured Tests', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'Popular Mock Tests', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Attempt our most popular mock tests and assess your preparation.', 'astra' ); ?></p>
				</div>

				<?php if ( ! empty( $featured_tests ) ) : ?>
					<div class="gmcq-tests-grid">
						<?php foreach ( $featured_tests as $index => $test ) : ?>
							<?php
							$quiz_id      = (int) $test->quiz_id;
							$test_meta    = function_exists( 'gmcq_get_quiz_meta' ) ? gmcq_get_quiz_meta( $quiz_id ) : null;
							$time_limit   = $test_meta ? (int) $test_meta->time_limit : 0;
							$q_count      = $test_meta ? (int) $test_meta->question_count : (int) ( $test->question_count ?? 0 );
							$quiz_title   = $test->post_title ?? get_the_title( $quiz_id );
							$difficulty   = $test_meta ? ( 'hard' === $test_meta->status ? 'hard' : ( $q_count > 50 ? 'hard' : ( $q_count > 25 ? 'medium' : 'easy' ) ) ) : 'medium';
							$difficulty_class = gmcq_home_get_difficulty_class( $difficulty );
							$delay        = $index * 100;
							?>
							<div class="gmcq-test-card gmcq-animate-fade-in-up" data-delay="<?php echo esc_attr( $delay ); ?>">
								<div class="gmcq-test-card-header">
									<span class="gmcq-test-difficulty <?php echo esc_attr( $difficulty_class ); ?>">
										<?php echo esc_html( ucfirst( $difficulty ) ); ?>
									</span>
									<?php if ( property_exists( $test, 'attempt_count' ) && (int) $test->attempt_count > 0 ) : ?>
										<span class="gmcq-test-attempts">
											<i class="fas fa-users" aria-hidden="true"></i>
											<?php echo esc_html( number_format( (int) $test->attempt_count ) ); ?>
										</span>
									<?php endif; ?>
								</div>
								<h3 class="gmcq-test-title"><?php echo esc_html( $quiz_title ); ?></h3>
								<div class="gmcq-test-meta">
									<div class="gmcq-test-meta-item">
										<i class="fas fa-question-circle" aria-hidden="true"></i>
										<span><?php echo esc_html( $q_count ); ?> <?php esc_html_e( 'Questions', 'astra' ); ?></span>
									</div>
									<div class="gmcq-test-meta-item">
										<i class="fas fa-clock" aria-hidden="true"></i>
										<span><?php echo $time_limit > 0 ? esc_html( $time_limit ) . ' ' . esc_html__( 'min', 'astra' ) : esc_html__( 'No Limit', 'astra' ); ?></span>
									</div>
									<?php if ( $test_meta && (float) $test_meta->pass_percentage > 0 ) : ?>
										<div class="gmcq-test-meta-item">
											<i class="fas fa-check-circle" aria-hidden="true"></i>
											<span><?php echo esc_html( number_format( (float) $test_meta->pass_percentage, 0 ) ); ?>% <?php esc_html_e( 'Pass', 'astra' ); ?></span>
										</div>
									<?php endif; ?>
								</div>
								<a href="<?php echo esc_url( get_permalink( $quiz_id ) ? get_permalink( $quiz_id ) : home_url( '/quiz/' . $quiz_id ) ); ?>" class="gmcq-btn gmcq-btn-primary gmcq-btn-full">
									<?php esc_html_e( 'Attempt Now', 'astra' ); ?>
									<i class="fas fa-arrow-right" aria-hidden="true"></i>
								</a>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="gmcq-empty-state">
						<i class="fas fa-file-alt" aria-hidden="true"></i>
						<h3><?php esc_html_e( 'No Tests Available', 'astra' ); ?></h3>
						<p><?php esc_html_e( 'Mock tests will appear here once published by the administrator.', 'astra' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 5: WHY CHOOSE GOVERNMENT MCQ -->
		<!-- ============================================================ -->
		<section class="gmcq-features" id="features">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Why Choose Us', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'Why Choose Government MCQ', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Everything you need to crack your dream government exam.', 'astra' ); ?></p>
				</div>
				<div class="gmcq-features-grid">
					<?php
					$features = array(
						array(
							'icon' => 'fa-infinity',
							'title' => __( 'Unlimited Practice', 'astra' ),
							'desc'  => __( 'Practice unlimited MCQs across all government exam categories with detailed explanations.', 'astra' ),
						),
						array(
							'icon' => 'fa-layer-group',
							'title' => __( 'Topic-wise Preparation', 'astra' ),
							'desc'  => __( 'Master each topic with our structured, topic-wise question banks and progress tracking.', 'astra' ),
						),
						array(
							'icon' => 'fa-calendar-check',
							'title' => __( 'Mock Exams', 'astra' ),
							'desc'  => __( 'Attempt full-length mock tests designed to simulate the real exam environment.', 'astra' ),
						),
						array(
							'icon' => 'fa-bolt',
							'title' => __( 'Instant Results', 'astra' ),
							'desc'  => __( 'Get instant results with detailed analysis after completing each test.', 'astra' ),
						),
						array(
							'icon' => 'fa-lightbulb',
							'title' => __( 'Detailed Solutions', 'astra' ),
							'desc'  => __( 'Understand every answer with step-by-step explanations and reference materials.', 'astra' ),
						),
						array(
							'icon' => 'fa-chart-simple',
							'title' => __( 'Performance Tracking', 'astra' ),
							'desc'  => __( 'Track your progress with detailed analytics, strengths, and areas for improvement.', 'astra' ),
						),
					);
					foreach ( $features as $index => $feature ) :
						$delay = $index * 100;
						?>
						<div class="gmcq-feature-card gmcq-animate-fade-in-up" data-delay="<?php echo esc_attr( $delay ); ?>">
							<div class="gmcq-feature-icon">
								<i class="fas <?php echo esc_attr( $feature['icon'] ); ?>" aria-hidden="true"></i>
							</div>
							<h3 class="gmcq-feature-title"><?php echo esc_html( $feature['title'] ); ?></h3>
							<p class="gmcq-feature-desc"><?php echo esc_html( $feature['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 6: LEADERBOARD -->
		<!-- ============================================================ -->
		<section class="gmcq-leaderboard" id="leaderboard">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Leaderboard', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'Top Performers', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'See how you rank against other aspirants.', 'astra' ); ?></p>
				</div>

				<?php if ( ! empty( $leaderboard_data ) ) : ?>
					<div class="gmcq-leaderboard-table-wrapper gmcq-animate-fade-in-up">
						<table class="gmcq-leaderboard-table">
							<thead>
								<tr>
									<th class="gmcq-lb-rank"><?php esc_html_e( 'Rank', 'astra' ); ?></th>
									<th class="gmcq-lb-student"><?php esc_html_e( 'Student', 'astra' ); ?></th>
									<th class="gmcq-lb-category"><?php esc_html_e( 'Category', 'astra' ); ?></th>
									<th class="gmcq-lb-score"><?php esc_html_e( 'Score', 'astra' ); ?></th>
									<th class="gmcq-lb-percent"><?php esc_html_e( 'Percentage', 'astra' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$rank = 1;
								foreach ( $leaderboard_data as $entry ) :
									$is_top   = $rank <= 3;
									$row_class = $is_top ? 'gmcq-lb-top-' . $rank : '';
									$medal     = '';
									if ( 1 === $rank ) {
										$medal = '<i class="fas fa-trophy gmcq-lb-medal gold" aria-hidden="true"></i>';
									} elseif ( 2 === $rank ) {
										$medal = '<i class="fas fa-medal gmcq-lb-medal silver" aria-hidden="true"></i>';
									} elseif ( 3 === $rank ) {
										$medal = '<i class="fas fa-medal gmcq-lb-medal bronze" aria-hidden="true"></i>';
									}
									?>
									<tr class="<?php echo esc_attr( $row_class ); ?>">
										<td class="gmcq-lb-rank"><?php echo $medal ? wp_kses_post( $medal ) : esc_html( $rank ); ?></td>
										<td class="gmcq-lb-student">
											<div class="gmcq-lb-avatar">
												<?php echo esc_html( strtoupper( substr( $entry->user_name ?? 'G', 0, 1 ) ) ); ?>
											</div>
											<span><?php echo esc_html( $entry->user_name ?? __( 'Guest', 'astra' ) ); ?></span>
										</td>
										<td class="gmcq-lb-category"><?php echo esc_html( $entry->category_name ?? '—' ); ?></td>
										<td class="gmcq-lb-score"><?php echo esc_html( number_format( (float) $entry->score, 1 ) ); ?></td>
										<td class="gmcq-lb-percent">
											<div class="gmcq-lb-progress">
												<div class="gmcq-lb-progress-bar" style="width: <?php echo esc_attr( min( (float) $entry->percentage, 100 ) ); ?>%;"></div>
												<span><?php echo esc_html( number_format( (float) $entry->percentage, 1 ) ); ?>%</span>
											</div>
										</td>
									</tr>
									<?php
									$rank++;
								endforeach;
								?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="gmcq-empty-state">
						<i class="fas fa-trophy" aria-hidden="true"></i>
						<h3><?php esc_html_e( 'No Leaderboard Data', 'astra' ); ?></h3>
						<p><?php esc_html_e( 'Leaderboard will populate as students complete tests.', 'astra' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 7: STUDENT TESTIMONIALS -->
		<!-- ============================================================ -->
		<section class="gmcq-testimonials" id="testimonials">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Testimonials', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'What Our Students Say', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Hear from thousands of successful aspirants who achieved their dreams.', 'astra' ); ?></p>
				</div>

				<div class="gmcq-testimonials-slider gmcq-animate-fade-in-up">
					<div class="gmcq-testimonials-track" id="gmcq-testimonials-track">
						<?php
						$testimonials = array(
							array(
								'name'     => 'Priya Sharma',
								'category' => 'UPSC Aspirant',
								'text'     => 'Government MCQ helped me score 145/200 in my UPSC prelims. The quality of questions and detailed explanations are unmatched. Highly recommended for serious aspirants!',
								'rating'   => 5,
							),
							array(
								'name'     => 'Rahul Verma',
								'category' => 'SSC CGL Aspirant',
								'text'     => 'The mock tests are exactly like the real SSC CGL exam. I improved my speed and accuracy significantly. The performance tracker helped me identify my weak areas.',
								'rating'   => 5,
							),
							array(
								'name'     => 'Anita Patel',
								'category' => 'Bank PO Aspirant',
								'text'     => 'I attempted all the banking mock tests and saw a 30% improvement in my scores. The detailed solutions helped me understand concepts deeply. Thank you Government MCQ!',
								'rating'   => 5,
							),
							array(
								'name'     => 'Vikram Singh',
								'category' => 'Railway NTPC Aspirant',
								'text'     => 'Best platform for railway exam preparation. The topic-wise practice and unlimited mock tests helped me clear the NTPC exam in my first attempt!',
								'rating'   => 5,
							),
							array(
								'name'     => 'Deepika Reddy',
								'category' => 'Teaching Aspirant',
								'text'     => 'The teaching exam section is comprehensive and well-structured. I loved the instant results and performance analytics. Truly a game-changer for exam preparation.',
								'rating'   => 4,
							),
						);
						foreach ( $testimonials as $testimonial ) :
							?>
							<div class="gmcq-testimonial-card">
								<div class="gmcq-testimonial-stars">
									<?php for ( $i = 0; $i < 5; $i++ ) : ?>
										<i class="fas fa-star <?php echo $i < $testimonial['rating'] ? 'gmcq-star-filled' : 'gmcq-star-empty'; ?>" aria-hidden="true"></i>
									<?php endfor; ?>
								</div>
								<p class="gmcq-testimonial-text">"<?php echo esc_html( $testimonial['text'] ); ?>"</p>
								<div class="gmcq-testimonial-author">
									<div class="gmcq-testimonial-avatar">
										<?php echo esc_html( strtoupper( substr( $testimonial['name'], 0, 1 ) ) ); ?>
									</div>
									<div class="gmcq-testimonial-info">
										<strong class="gmcq-testimonial-name"><?php echo esc_html( $testimonial['name'] ); ?></strong>
										<span class="gmcq-testimonial-category"><?php echo esc_html( $testimonial['category'] ); ?></span>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<div class="gmcq-testimonials-nav">
						<button class="gmcq-testimonial-btn gmcq-testimonial-prev" aria-label="<?php esc_attr_e( 'Previous testimonial', 'astra' ); ?>">
							<i class="fas fa-chevron-left" aria-hidden="true"></i>
						</button>
						<div class="gmcq-testimonial-dots" id="gmcq-testimonial-dots"></div>
						<button class="gmcq-testimonial-btn gmcq-testimonial-next" aria-label="<?php esc_attr_e( 'Next testimonial', 'astra' ); ?>">
							<i class="fas fa-chevron-right" aria-hidden="true"></i>
						</button>
					</div>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 8: LATEST UPDATES -->
		<!-- ============================================================ -->
		<section class="gmcq-updates" id="updates">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Updates', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'Latest Updates', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Stay informed with the latest exam notifications and platform updates.', 'astra' ); ?></p>
				</div>
				<div class="gmcq-updates-grid">
					<?php
					// Get latest posts as updates
					$latest_posts = get_posts(
						array(
							'numberposts' => 4,
							'post_status' => 'publish',
						)
					);
					if ( ! empty( $latest_posts ) ) :
						foreach ( $latest_posts as $index => $post_item ) :
							setup_postdata( $post_item );
							$delay = $index * 100;
							$categories_list = get_the_category( $post_item->ID );
							$post_cat = ! empty( $categories_list ) ? $categories_list[0]->name : __( 'Update', 'astra' );
							?>
							<div class="gmcq-update-card gmcq-animate-fade-in-up" data-delay="<?php echo esc_attr( $delay ); ?>">
								<div class="gmcq-update-card-badge"><?php echo esc_html( $post_cat ); ?></div>
								<h3 class="gmcq-update-title"><?php echo esc_html( get_the_title( $post_item->ID ) ); ?></h3>
								<p class="gmcq-update-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_item->ID ) ?: wp_trim_words( get_the_content( null, false, $post_item->ID ), 20 ), 15 ) ); ?></p>
								<div class="gmcq-update-meta">
									<span class="gmcq-update-date">
										<i class="fas fa-calendar-alt" aria-hidden="true"></i>
										<?php echo esc_html( get_the_date( 'd M Y', $post_item->ID ) ); ?>
									</span>
									<a href="<?php echo esc_url( get_permalink( $post_item->ID ) ); ?>" class="gmcq-update-link">
										<?php esc_html_e( 'Read More', 'astra' ); ?>
										<i class="fas fa-arrow-right" aria-hidden="true"></i>
									</a>
								</div>
							</div>
						<?php endforeach;
						wp_reset_postdata();
					else : ?>
						<div class="gmcq-empty-state gmcq-animate-fade-in-up">
							<i class="fas fa-newspaper" aria-hidden="true"></i>
							<h3><?php esc_html_e( 'No Updates Yet', 'astra' ); ?></h3>
							<p><?php esc_html_e( 'Latest updates and notifications will appear here.', 'astra' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION 9: CALL TO ACTION -->
		<!-- ============================================================ -->
		<section class="gmcq-cta" id="cta">
			<div class="gmcq-container">
				<div class="gmcq-cta-content gmcq-animate-fade-in-up">
					<div class="gmcq-cta-bg-shapes">
						<div class="gmcq-cta-shape gmcq-cta-shape-1"></div>
						<div class="gmcq-cta-shape gmcq-cta-shape-2"></div>
					</div>
					<span class="gmcq-cta-badge"><?php esc_html_e( 'Get Started Today', 'astra' ); ?></span>
					<h2 class="gmcq-cta-title"><?php esc_html_e( 'Start Your Government Exam Preparation Today', 'astra' ); ?></h2>
					<p class="gmcq-cta-desc"><?php esc_html_e( 'Join thousands of successful aspirants. Practice, learn, and achieve your government job dream.', 'astra' ); ?></p>
					<div class="gmcq-cta-actions">
						<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="gmcq-btn gmcq-btn-accent gmcq-btn-large">
							<i class="fas fa-user-plus" aria-hidden="true"></i>
							<?php esc_html_e( 'Register Free', 'astra' ); ?>
						</a>
						<a href="#featured-tests" class="gmcq-btn gmcq-btn-outline-light gmcq-btn-large">
							<i class="fas fa-play-circle" aria-hidden="true"></i>
							<?php esc_html_e( 'Take Demo Test', 'astra' ); ?>
						</a>
					</div>
				</div>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();