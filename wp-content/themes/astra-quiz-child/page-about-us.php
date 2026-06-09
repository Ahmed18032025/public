<?php
/**
 * Template: About Us
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="aqc-page-wrapper">
	<?php get_template_part( 'aqc-page-header', null, array(
		'title' => __( 'About Us', 'astra-quiz-child' ),
		'description' => __( 'Learn about our mission to help aspirants prepare better for government exams.', 'astra-quiz-child' ),
	) ); ?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2><?php esc_html_e( 'Our Mission', 'astra-quiz-child' ); ?></h2>
				<p><?php esc_html_e( 'We are dedicated to providing high-quality, exam-oriented practice material for government job aspirants across India.', 'astra-quiz-child' ); ?></p>
			</div>
			<div class="aqc-grid-3">
				<article class="aqc-feature-card">
					<span class="aqc-icon">🎯</span>
					<h3><?php esc_html_e( 'Exam-focused', 'astra-quiz-child' ); ?></h3>
					<p><?php esc_html_e( 'Every quiz is designed around actual exam patterns for SSC, Banking, Railway, Defence and State PSC.', 'astra-quiz-child' ); ?></p>
				</article>
				<article class="aqc-feature-card">
					<span class="aqc-icon">📊</span>
					<h3><?php esc_html_e( 'Performance Tracking', 'astra-quiz-child' ); ?></h3>
					<p><?php esc_html_e( 'Track your progress with detailed analytics, time-per-question stats and weak-area identification.', 'astra-quiz-child' ); ?></p>
				</article>
				<article class="aqc-feature-card">
					<span class="aqc-icon">🆓</span>
					<h3><?php esc_html_e( 'Free Access', 'astra-quiz-child' ); ?></h3>
					<p><?php esc_html_e( 'Quality preparation should not be limited by cost. Most of our content remains free for all aspirants.', 'astra-quiz-child' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<?php if ( function_exists( 'gmcq_get_dashboard_stats' ) ) : ?>
	<?php $stats = gmcq_get_dashboard_stats(); ?>
	<section class="aqc-page-section" style="background:var(--aqc-section-alt)">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2><?php esc_html_e( 'Our Impact', 'astra-quiz-child' ); ?></h2>
				<p><?php esc_html_e( 'Numbers that reflect our commitment to quality education.', 'astra-quiz-child' ); ?></p>
			</div>
			<div class="aqc-stat-grid">
				<div class="aqc-stat-card">
					<strong data-count="<?php echo (int) ( $stats['active_questions'] ?? 500 ); ?>">0</strong>
					<span><?php esc_html_e( 'Practice Questions', 'astra-quiz-child' ); ?></span>
				</div>
				<div class="aqc-stat-card">
					<strong data-count="<?php echo (int) ( $stats['published_quizzes'] ?? 25 ); ?>">0</strong>
					<span><?php esc_html_e( 'Quiz Sets', 'astra-quiz-child' ); ?></span>
				</div>
				<div class="aqc-stat-card">
					<strong data-count="<?php echo (int) ( $stats['top_level_categories'] ?? 6 ); ?>">0</strong>
					<span><?php esc_html_e( 'Exam Categories', 'astra-quiz-child' ); ?></span>
				</div>
				<div class="aqc-stat-card">
					<strong data-count="<?php echo (int) ( $stats['total_attempts'] ?? 0 ); ?>">0</strong>
					<span><?php esc_html_e( 'Tests Completed', 'astra-quiz-child' ); ?></span>
				</div>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<div class="aqc-section-heading">
				<h2><?php esc_html_e( 'How We Build Quizzes', 'astra-quiz-child' ); ?></h2>
				<p><?php esc_html_e( 'A careful process to ensure quality and relevance.', 'astra-quiz-child' ); ?></p>
			</div>
			<div class="aqc-steps">
				<article class="aqc-step">
					<span class="aqc-step-number">1</span>
					<h3><?php esc_html_e( 'Research', 'astra-quiz-child' ); ?></h3>
					<p><?php esc_html_e( 'Analyze previous year papers and exam notifications to identify important topics.', 'astra-quiz-child' ); ?></p>
				</article>
				<article class="aqc-step">
					<span class="aqc-step-number">2</span>
					<h3><?php esc_html_e( 'Content Creation', 'astra-quiz-child' ); ?></h3>
					<p><?php esc_html_e( 'Subject experts create well-researched questions with accurate explanations.', 'astra-quiz-child' ); ?></p>
				</article>
				<article class="aqc-step">
					<span class="aqc-step-number">3</span>
					<h3><?php esc_html_e( 'Review & Publish', 'astra-quiz-child' ); ?></h3>
					<p><?php esc_html_e( 'Each quiz goes through a multi-level review before being published.', 'astra-quiz-child' ); ?></p>
				</article>
			</div>
		</div>
	</section>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<div class="aqc-cta-banner">
				<h2><?php esc_html_e( 'Ready to start practicing?', 'astra-quiz-child' ); ?></h2>
				<p><?php esc_html_e( 'Join thousands of aspirants who prepare smarter every day.', 'astra-quiz-child' ); ?></p>
				<a class="aqc-btn aqc-btn-primary" href="<?php echo esc_url( home_url( '/all-quizzes/' ) ); ?>">
					<?php esc_html_e( 'Browse Quizzes', 'astra-quiz-child' ); ?>
				</a>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
