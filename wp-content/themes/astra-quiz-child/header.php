<?php
/**
 * Universal custom header for Astra Quiz Child theme.
 * Works on all pages (home, inner, blog, etc.).
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

$brand_text = get_theme_mod( 'aqc_brand_text', 'Government MCQ' );
$show_toggle = get_theme_mod( 'aqc_show_theme_toggle', 1 );
$header_opacity = get_theme_mod( 'aqc_header_bg_opacity', 0.72 );
$header_padding = get_theme_mod( 'aqc_header_height', 18 );

$current_url = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' )
	. '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$current_path = parse_url( $current_url, PHP_URL_PATH );
$current_path = rtrim( $current_path, '/' );

$home_url_path = rtrim( parse_url( home_url(), PHP_URL_PATH ), '/' );

function aqc_is_current( $url, $current_path, $home_url_path ) {
	$path = rtrim( parse_url( $url, PHP_URL_PATH ), '/' );
	if ( '' === $path || $path === $home_url_path ) {
		return ( '' === $current_path || $home_url_path === $current_path );
	}
	return $path === $current_path;
}

$is_home         = aqc_is_current( home_url( '/' ), $current_path, $home_url_path );
$is_all_quizzes  = aqc_is_current( home_url( '/all-quizzes/' ), $current_path, $home_url_path );
$is_blog         = is_home() && ! is_front_page() || is_category() || is_single();
$is_contact      = aqc_is_current( home_url( '/contact-us/' ), $current_path, $home_url_path );
$is_about        = aqc_is_current( home_url( '/about-us/' ), $current_path, $home_url_path );
$is_quiz_single  = is_singular( 'gmcq_quiz' );
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#primary">
	<?php esc_html_e( 'Skip to content', 'astra-quiz-child' ); ?>
</a>

<div id="page" class="hfeed site aqc-page-wrapper">
	<?php astra_body_top(); ?>

	<header id="masthead" class="site-header aqc-header" role="banner">
		<div class="aqc-container">
			<nav class="aqc-nav" aria-label="<?php esc_attr_e( 'Primary', 'astra-quiz-child' ); ?>">
				<div class="aqc-brand aqc-brand-mark">
					<?php
					if ( has_custom_logo() ) {
						the_custom_logo();
					} else {
						echo '<span aria-hidden="true" style="font-size:1.4rem">✓</span>';
					}
					?>
					<span class="aqc-brand-text"><?php echo esc_html( $brand_text ); ?></span>
				</div>

				<button
					class="aqc-hamburger"
					aria-label="<?php esc_attr_e( 'Open menu', 'astra-quiz-child' ); ?>"
					aria-expanded="false"
					aria-controls="aqc-primary-menu"
				>
					<span></span><span></span><span></span>
				</button>

				<?php
				wp_nav_menu( array(
					'theme_location' => 'menu-1',
					'container'      => false,
					'menu_id'        => 'aqc-primary-menu',
					'menu_class'     => 'aqc-menu',
					'fallback_cb'    => function () {
						$items = array(
							home_url( '/' )           => __( 'Home', 'astra-quiz-child' ),
							home_url( '/all-quizzes/' ) => __( 'All Quizzes', 'astra-quiz-child' ),
							home_url( '/blog/' )        => __( 'Blog', 'astra-quiz-child' ),
							home_url( '/contact-us/' )  => __( 'Contact Us', 'astra-quiz-child' ),
							home_url( '/about-us/' )    => __( 'About Us', 'astra-quiz-child' ),
						);
						echo '<ul class="aqc-menu" id="aqc-primary-menu">';
						foreach ( $items as $url => $label ) {
							$is_current = ( trailingslashit( $url ) === trailingslashit( home_url( $_SERVER['REQUEST_URI'] ?? '/' ) ) )
								|| ( $url === home_url( '/' ) && is_front_page() );
							$class = $is_current ? ' class="is-active"' : '';
							echo '<li' . $class . '><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
						}
						echo '</ul>';
					},
					'depth'          => 2,
				) );
				?>

				<?php if ( $show_toggle ) : ?>
				<button
					type="button"
					class="aqc-theme-toggle"
					aria-label="<?php esc_attr_e( 'Toggle dark mode', 'astra-quiz-child' ); ?>"
				>
					<span class="aqc-icon-sun" aria-hidden="true">☀️</span>
					<span class="aqc-icon-moon" aria-hidden="true">🌙</span>
				</button>
				<?php endif; ?>
			</nav>
		</div>
	</header>

	<div id="content" class="site-content">
		<div class="ast-container">
			<?php astra_content_top(); ?>
