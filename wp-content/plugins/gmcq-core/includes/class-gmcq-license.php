<?php
/**
 * GMCQ License System - Remote validation via Netlify
 */
defined( 'ABSPATH' ) || exit;

// Endpoint URL - update after Vercel deployment
if ( ! defined( 'GMCQ_LICENSE_ENDPOINT' ) ) {
	define( 'GMCQ_LICENSE_ENDPOINT', 'https://YOUR-PROJECT.vercel.app/api/validate-license' );
}

function gmcq_license_is_activated(): bool {
	$token = get_option( 'gmcq_license_token', '' );
	$activated_at = get_option( 'gmcq_license_activated_at', 0 );
	
	if ( empty( $token ) || ! $activated_at ) {
		return false;
	}
	
	// Check if token is expired (30 days)
	$expires_at = $activated_at + ( 30 * DAY_IN_SECONDS );
	if ( time() > $expires_at ) {
		return false;
	}
	
	return true;
}

function gmcq_license_get_stored_key(): string {
	return get_option( 'gmcq_license_key', '' );
}

function gmcq_license_activate( string $license_key ): array {
	$response = wp_remote_post( GMCQ_LICENSE_ENDPOINT, array(
		'body'        => wp_json_encode( array(
			'license_key' => $license_key,
			'domain'      => home_url(),
		) ),
		'headers'     => array(
			'Content-Type' => 'application/json',
		),
		'timeout'     => 30,
		'data_format' => 'body',
	) );
	
	if ( is_wp_error( $response ) ) {
		return array(
			'success' => false,
			'message' => $response->get_error_message(),
		);
	}
	
	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	
	if ( $code !== 200 || ! $data['valid'] ?? false ) {
		return array(
			'success' => false,
			'message' => $data['message'] ?? 'Invalid license key or server error',
		);
	}
	
	// Store encrypted token and key
	update_option( 'gmcq_license_token', $data['token'] ?? '' );
	update_option( 'gmcq_license_key', $license_key );
	update_option( 'gmcq_license_activated_at', time() );
	
	return array(
		'success' => true,
		'message' => 'License activated successfully',
	);
}

function gmcq_license_deactivate(): void {
	delete_option( 'gmcq_license_token' );
	delete_option( 'gmcq_license_key' );
	delete_option( 'gmcq_license_activated_at' );
}

function gmcq_license_render_page(): void {
	if ( gmcq_license_is_activated() ) {
		$key_masked = gmcq_license_get_stored_key();
		if ( strlen( $key_masked ) > 8 ) {
			$key_masked = substr( $key_masked, 0, 4 ) . str_repeat( '*', strlen( $key_masked ) - 8 ) . substr( $key_masked, -4 );
		}
		?>
		<div class="gmcq-card" style="max-width:600px">
			<h2><?php esc_html_e( 'License Status', 'gmcq' ); ?></h2>
			<p><strong><?php esc_html_e( 'Status:', 'gmcq' ); ?></strong> <span class="gmcq-status-ok"><?php esc_html_e( 'Activated', 'gmcq' ); ?></span></p>
			<p><strong><?php esc_html_e( 'License Key:', 'gmcq' ); ?></strong> <?php echo esc_html( $key_masked ); ?></p>
			<p><strong><?php esc_html_e( 'Activated:', 'gmcq' ); ?></strong> <?php echo esc_html( gmdate( 'Y-m-d H:i:s', get_option( 'gmcq_license_activated_at', 0 ) ) ); ?></p>
			<button type="button" class="button" id="gmcq-deactivate-license"><?php esc_html_e( 'Deactivate License', 'gmcq' ); ?></button>
		</div>
		<script>
		jQuery( function( $ ) {
			$( '#gmcq-deactivate-license' ).on( 'click', function() {
				if ( ! confirm( '<?php echo esc_js( __( 'Are you sure you want to deactivate the license?', 'gmcq' ) ); ?>' ) ) {
					return;
				}
				$.post( gmcqAdmin.ajaxUrl, { action: 'gmcq_deactivate_license', _ajax_nonce: gmcqAdmin.licenseNonce }, function() {
					location.reload();
				} );
			} );
		} );
		</script>
		<?php
		return;
	}
	
	$nonce = wp_create_nonce( 'gmcq_license_nonce' );
	$errors = get_transient( 'gmcq_license_errors', array() );
	delete_transient( 'gmcq_license_errors' );
	?>
	<div class="gmcq-card" style="max-width:600px">
		<h2><?php esc_html_e( 'License Activation Required', 'gmcq' ); ?></h2>
		<?php if ( ! empty( $errors ) ) : ?>
		<div class="notice notice-error" role="alert" style="margin-bottom:15px">
			<p><?php echo esc_html( $errors['message'] ?? '' ); ?></p>
		</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Enter your license key to activate this plugin.', 'gmcq' ); ?></p>
		<form id="gmcq-license-form">
			<?php wp_nonce_field( 'gmcq_license_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="gmcq-license-key"><?php esc_html_e( 'License Key', 'gmcq' ); ?></label></th>
					<td><input type="text" name="license_key" id="gmcq-license-key" class="regular-text" placeholder="XXXX-XXXX-XXXX-XXXX" required></td>
				</tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Activate License', 'gmcq' ); ?></button></p>
			<div id="gmcq-license-response" style="margin-top:15px;display:none"></div>
		</form>
	</div>
	<script>
	jQuery( function( $ ) {
		$( '#gmcq-license-form' ).on( 'submit', function( e ) {
			e.preventDefault();
			var $btn = $( this ).find( 'button[type="submit"]' ).prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Activating...', 'gmcq' ) ); ?>' );
			$.post( gmcqAdmin.ajaxUrl, {
				action: 'gmcq_activate_license',
				license_key: $( '#gmcq-license-key' ).val(),
				_ajax_nonce: gmcqAdmin.licenseNonce
			}, function( r ) {
				$( '#gmcq-license-response' ).removeClass( 'notice-success notice-error' ).addClass( r.success ? 'notice-success' : 'notice-error' ).html( '<p>' + r.data.message + '</p>' ).show();
				if ( r.success ) {
					setTimeout( function() { location.reload(); }, 1500 );
				} else {
					$btn.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Activate License', 'gmcq' ) ); ?>' );
				}
			} ).fail( function() {
				$btn.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Activate License', 'gmcq' ) ); ?>' );
				$( '#gmcq-license-response' ).addClass( 'notice-error' ).html( '<p><?php echo esc_js( __( 'Server error. Please try again.', 'gmcq' ) ); ?></p>' ).show();
			} );
		} );
	} );
	</script>
	<?php
}

function gmcq_license_register_ajax_handlers(): void {
	add_action( 'wp_ajax_gmcq_activate_license', 'gmcq_ajax_activate_license' );
	add_action( 'wp_ajax_gmcq_deactivate_license', 'gmcq_ajax_deactivate_license' );
}

function gmcq_ajax_activate_license(): void {
	check_ajax_referer( 'gmcq_license_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	
	$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
	
	if ( empty( $license_key ) ) {
		wp_send_json_error( array( 'message' => __( 'License key is required.', 'gmcq' ) ) );
	}
	
	$result = gmcq_license_activate( $license_key );
	
	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}

function gmcq_ajax_deactivate_license(): void {
	check_ajax_referer( 'gmcq_license_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}
	
	gmcq_license_deactivate();
	wp_send_json_success( array( 'message' => __( 'License deactivated.', 'gmcq' ) ) );
}

gmcq_license_register_ajax_handlers();