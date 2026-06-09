<?php
/**
 * Template: Contact Us
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="aqc-page-wrapper">
	<?php get_template_part( 'aqc-page-header', null, array(
		'title' => __( 'Get in Touch', 'astra-quiz-child' ),
		'description' => __( 'Have questions or feedback? We would love to hear from you.', 'astra-quiz-child' ),
	) ); ?>

	<section class="aqc-page-section">
		<div class="aqc-container">
			<div class="aqc-grid-2">
				<div class="aqc-contact-info">
					<div class="aqc-feature-card" style="margin-bottom:16px">
						<span class="aqc-icon">📧</span>
						<h3><?php esc_html_e( 'Email Us', 'astra-quiz-child' ); ?></h3>
						<p><?php echo esc_html( get_option( 'admin_email' ) ); ?></p>
					</div>
					<div class="aqc-feature-card" style="margin-bottom:16px">
						<span class="aqc-icon">⏰</span>
						<h3><?php esc_html_e( 'Response Time', 'astra-quiz-child' ); ?></h3>
						<p><?php esc_html_e( 'We usually respond within 24 hours on business days.', 'astra-quiz-child' ); ?></p>
					</div>
					<div class="aqc-feature-card">
						<span class="aqc-icon">💬</span>
						<h3><?php esc_html_e( 'Community', 'astra-quiz-child' ); ?></h3>
						<p><?php esc_html_e( 'Join our Telegram or YouTube channel for daily updates.', 'astra-quiz-child' ); ?></p>
					</div>
				</div>

				<div class="aqc-contact-form-wrapper">
					<form class="aqc-contact-form" id="aqc-contact-form" novalidate aria-label="<?php esc_attr_e( 'Contact form', 'astra-quiz-child' ); ?>">
						<div class="form-group">
							<label for="aqc-name"><?php esc_html_e( 'Name', 'astra-quiz-child' ); ?> <span aria-hidden="true">*</span></label>
							<input type="text" id="aqc-name" name="name" required aria-required="true">
						</div>
						<div class="form-group">
							<label for="aqc-email"><?php esc_html_e( 'Email', 'astra-quiz-child' ); ?> <span aria-hidden="true">*</span></label>
							<input type="email" id="aqc-email" name="email" required aria-required="true">
						</div>
						<div class="form-group full-width">
							<label for="aqc-subject"><?php esc_html_e( 'Subject', 'astra-quiz-child' ); ?> <span aria-hidden="true">*</span></label>
							<input type="text" id="aqc-subject" name="subject" required aria-required="true">
						</div>
						<div class="form-group full-width">
							<label for="aqc-message"><?php esc_html_e( 'Message', 'astra-quiz-child' ); ?> <span aria-hidden="true">*</span></label>
							<textarea id="aqc-message" name="message" rows="5" required aria-required="true"></textarea>
						</div>
						<button type="submit" class="aqc-btn aqc-btn-primary full-width">
							<?php esc_html_e( 'Send Message', 'astra-quiz-child' ); ?>
						</button>
					</form>
				</div>
			</div>
		</div>
	</section>
</main>

<?php
get_footer();
