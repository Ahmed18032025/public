<?php
/**
 * Template: 404 — Page Not Found
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="aqc-page-wrapper">
	<section class="aqc-page-section">
		<div class="aqc-container" style="text-align:center; padding: 60px 0;">
			<h1 style="font-size:clamp(3rem, 8vw, 5rem); margin-bottom: 12px; letter-spacing: -0.04em;">404</h1>
			<p style="font-size: 1.25rem; color: var(--aqc-muted-text); max-width: 520px; margin: 0 auto 24px;">
				<?php esc_html_e( 'The page you are looking for could not be found. It might have been removed, renamed, or does not exist.', 'astra-quiz-child' ); ?>
			</p>
			<a class="aqc-btn aqc-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Back to Home', 'astra-quiz-child' ); ?>
			</a>

			<form class="aqc-filter-bar" style="justify-content:center; margin-top: 32px;" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search" aria-label="<?php esc_attr_e( 'Site search', 'astra-quiz-child' ); ?>">
				<div class="form-group" style="max-width: 400px;">
					<input type="search" name="s" placeholder="<?php esc_attr_e( 'Search quizzes or pages...', 'astra-quiz-child' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="<?php esc_attr_e( 'Search', 'astra-quiz-child' ); ?>">
				</div>
				<button type="submit" class="aqc-btn aqc-btn-primary aqc-btn-sm"><?php esc_html_e( 'Search', 'astra-quiz-child' ); ?></button>
			</form>
		</div>
	</section>
</main>

<?php
get_footer();
