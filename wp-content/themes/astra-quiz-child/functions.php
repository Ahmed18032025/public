<?php
/**
 * Astra Quiz Child theme functions.
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue Astra parent styles and this child theme stylesheet.
 */
function aqc_enqueue_styles(): void {
	wp_enqueue_style(
		'astra-parent-style',
		get_template_directory_uri() . '/style.css',
		array(),
		wp_get_theme( 'astra' )->get( 'Version' )
	);

	wp_enqueue_style(
		'astra-quiz-child-style',
		get_stylesheet_uri(),
		array( 'astra-parent-style' ),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'aqc_enqueue_styles', 20 );

/**
 * Keep the custom homepage clean and full width when this child theme is active.
 */
function aqc_homepage_body_class( array $classes ): array {
	if ( is_front_page() ) {
		$classes[] = 'aqc-modern-home';
	}

	return $classes;
}
add_filter( 'body_class', 'aqc_homepage_body_class' );