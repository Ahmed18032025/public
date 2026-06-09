<?php
/**
 * Template part: Page Header Banner
 *
 * @package Astra_Quiz_Child
 */

defined( 'ABSPATH' ) || exit;

$args = wp_parse_args( $args ?? array(), array(
	'title'       => get_the_title(),
	'description' => '',
	'bg'          => '',
) );
?>

<header class="aqc-page-header" <?php echo $args['bg'] ? 'style="background:' . esc_attr( $args['bg'] ) . '"' : ''; ?>>
	<div class="aqc-container">
		<h1><?php echo esc_html( $args['title'] ); ?></h1>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
	</div>
</header>
