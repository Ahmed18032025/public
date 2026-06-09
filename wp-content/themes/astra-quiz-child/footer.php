<?php
/**
 * Universal custom footer for Astra Quiz Child theme.
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

$brand_text    = get_theme_mod( 'aqc_brand_text', 'Government MCQ' );
$footer_tagline = get_theme_mod( 'aqc_footer_tagline', __( 'Made for aspirants', 'astra-quiz-child' ) );
$copyright_text = get_theme_mod( 'aqc_footer_copyright', '' );
$show_categories = get_theme_mod( 'aqc_footer_show_categories', 1 );
$show_links     = get_theme_mod( 'aqc_footer_show_links', 1 );
$category_tree  = function_exists( 'gmcq_get_category_tree' ) ? gmcq_get_category_tree( array( 'filter' => 'active' ) ) : array();

if ( empty( $copyright_text ) ) {
	$copyright_text = '&copy; ' . date_i18n( 'Y' ) . ' ' . get_bloginfo( 'name' ) . '. ' . $footer_tagline;
}
?>

	</div><!-- .ast-container -->
</div><!-- #content -->

<footer id="colophon" class="aqc-footer" role="contentinfo">
	<div class="aqc-container">
		<div class="aqc-footer-grid">
			<div class="aqc-footer-brand">
				<div class="aqc-brand aqc-brand-mark">
					<?php
					if ( has_custom_logo() ) {
						the_custom_logo();
					} else {
						echo '<span aria-hidden="true" style="font-size:1.3rem">✓</span>';
					}
					?>
					<span><?php echo esc_html( $brand_text ); ?></span>
				</div>
				<p><?php echo esc_html( $footer_tagline ); ?></p>
				<?php if ( ! empty( $category_tree ) && $show_categories ) : ?>
				<div class="aqc-footer-categories">
					<h4><?php esc_html_e( 'Exam Categories', 'astra-quiz-child' ); ?></h4>
					<ul>
						<?php foreach ( $category_tree as $cat ) : ?>
							<li>
								<a href="<?php echo esc_url( home_url( '/all-quizzes/?category=' . urlencode( $cat->slug ) ) ); ?>">
									<?php echo esc_html( $cat->name ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php endif; ?>
			</div>

			<?php if ( $show_links ) : ?>
			<div class="aqc-footer-links">
				<h4><?php esc_html_e( 'Quick Links', 'astra-quiz-child' ); ?></h4>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'astra-quiz-child' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/all-quizzes/' ) ); ?>"><?php esc_html_e( 'All Quizzes', 'astra-quiz-child' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"><?php esc_html_e( 'Blog', 'astra-quiz-child' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>"><?php esc_html_e( 'Contact Us', 'astra-quiz-child' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>"><?php esc_html_e( 'About Us', 'astra-quiz-child' ); ?></a></li>
				</ul>
			</div>
			<?php endif; ?>

			<div class="aqc-footer-newsletter">
				<h4><?php esc_html_e( 'Stay Updated', 'astra-quiz-child' ); ?></h4>
				<p><?php esc_html_e( 'Get notified about new quiz sets and exam updates.', 'astra-quiz-child' ); ?></p>
				<form class="aqc-newsletter-form" method="post" action="#">
					<input
						type="email"
						name="aqc_newsletter_email"
						placeholder="<?php esc_attr_e( 'your@email.com', 'astra-quiz-child' ); ?>"
						required
						aria-label="<?php esc_attr_e( 'Email for newsletter', 'astra-quiz-child' ); ?>"
					>
					<button type="submit" class="aqc-btn aqc-btn-primary aqc-btn-sm">
						<?php esc_html_e( 'Subscribe', 'astra-quiz-child' ); ?>
					</button>
				</form>
				<div class="aqc-social-links">
					<a href="#" aria-label="Facebook" rel="noopener">FB</a>
					<a href="#" aria-label="Twitter" rel="noopener">X</a>
					<a href="#" aria-label="YouTube" rel="noopener">YT</a>
					<a href="#" aria-label="Telegram" rel="noopener">TG</a>
				</div>
			</div>
		</div>

		<div class="aqc-footer-bottom">
			<p><?php echo wp_kses_post( $copyright_text ); ?></p>
		</div>
	</div>
</footer>

<?php astra_body_bottom(); ?>
<?php wp_footer(); ?>
</body>
</html>
