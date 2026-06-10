<?php
/**
 * Template Name: Contact Us
 * Description: Premium contact page with working form, AJAX submission, DB storage, FAQ.
 *
 * @package Astra
 * @subpackage Government_MCQ
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================
// CREATE DB TABLE ON THEME ACTIVATION
// ============================================================
function gmcq_contact_create_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'gmcq_contact_messages';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		email varchar(255) NOT NULL,
		phone varchar(50) DEFAULT '',
		subject varchar(255) DEFAULT '',
		message text NOT NULL,
		is_read tinyint(1) DEFAULT 0,
		ip_address varchar(45) DEFAULT '',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_read (is_read),
		KEY idx_created (created_at)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
add_action( 'after_switch_theme', 'gmcq_contact_create_table' );
// Also run on plugins_loaded in case theme was already active
add_action( 'init', 'gmcq_contact_create_table' );

// ============================================================
// AJAX HANDLER: Submit Contact Form
// ============================================================
add_action( 'wp_ajax_gmcq_contact_submit', 'gmcq_ajax_contact_submit' );
add_action( 'wp_ajax_nopriv_gmcq_contact_submit', 'gmcq_ajax_contact_submit' );

function gmcq_ajax_contact_submit() {
	check_ajax_referer( 'gmcq_contact_nonce', 'nonce' );

	// Honeypot check
	if ( ! empty( $_POST['website'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Spam detected.', 'astra' ) ) );
	}

	// Rate limiting
	$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	$rate_key = 'gmcq_contact_rate_' . md5( $ip );
	$count = (int) get_transient( $rate_key );
	if ( $count >= 3 ) {
		wp_send_json_error( array( 'message' => __( 'Too many submissions. Please try again later.', 'astra' ) ) );
	}

	// Validate fields
	$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	$errors  = array();

	if ( empty( $name ) ) {
		$errors['name'] = __( 'Please enter your name.', 'astra' );
	}
	if ( empty( $email ) || ! is_email( $email ) ) {
		$errors['email'] = __( 'Please enter a valid email address.', 'astra' );
	}
	if ( empty( $subject ) ) {
		$errors['subject'] = __( 'Please select a subject.', 'astra' );
	}
	if ( empty( $message ) ) {
		$errors['message'] = __( 'Please enter your message.', 'astra' );
	}
	if ( strlen( $message ) > 2000 ) {
		$errors['message'] = __( 'Message is too long (max 2000 characters).', 'astra' );
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error( array( 'errors' => $errors ) );
	}

	// Store in DB
	global $wpdb;
	$inserted = $wpdb->insert(
		$wpdb->prefix . 'gmcq_contact_messages',
		array(
			'name'       => $name,
			'email'      => $email,
			'phone'      => $phone,
			'subject'    => $subject,
			'message'    => $message,
			'ip_address' => $ip,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	// Send email to admin
	$admin_email = get_option( 'admin_email' );
	$site_name   = get_bloginfo( 'name' );
	$mail_headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $site_name . ' <' . $admin_email . '>',
		'Reply-To: ' . $name . ' <' . $email . '>',
	);

	$admin_subject = sprintf( __( '[%s] New Contact Message: %s', 'astra' ), $site_name, $subject );
	$admin_body    = '<h2>' . __( 'New Contact Message', 'astra' ) . '</h2>';
	$admin_body   .= '<p><strong>' . __( 'Name:', 'astra' ) . '</strong> ' . esc_html( $name ) . '</p>';
	$admin_body   .= '<p><strong>' . __( 'Email:', 'astra' ) . '</strong> ' . esc_html( $email ) . '</p>';
	$admin_body   .= '<p><strong>' . __( 'Phone:', 'astra' ) . '</strong> ' . esc_html( $phone ?: '—' ) . '</p>';
	$admin_body   .= '<p><strong>' . __( 'Subject:', 'astra' ) . '</strong> ' . esc_html( $subject ) . '</p>';
	$admin_body   .= '<p><strong>' . __( 'Message:', 'astra' ) . '</strong></p>';
	$admin_body   .= '<p>' . nl2br( esc_html( $message ) ) . '</p>';
	wp_mail( $admin_email, $admin_subject, $admin_body, $mail_headers );

	// Send auto-reply to user
	$user_subject = sprintf( __( 'Thank you for contacting %s', 'astra' ), $site_name );
	$user_body    = '<h2>' . sprintf( __( 'Hello %s,', 'astra' ), esc_html( $name ) ) . '</h2>';
	$user_body   .= '<p>' . __( 'Thank you for reaching out to Government MCQ. We have received your message and will get back to you within 24 hours.', 'astra' ) . '</p>';
	$user_body   .= '<hr>';
	$user_body   .= '<p><strong>' . __( 'Your Message:', 'astra' ) . '</strong></p>';
	$user_body   .= '<blockquote>' . nl2br( esc_html( $message ) ) . '</blockquote>';
	$user_body   .= '<p>' . __( 'If you have an urgent query, please call us during business hours.', 'astra' ) . '</p>';
	$user_body   .= '<p>' . __( 'Best regards,', 'astra' ) . '<br>' . esc_html( $site_name ) . ' ' . __( 'Team', 'astra' ) . '</p>';

	$user_headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $site_name . ' <' . $admin_email . '>',
	);
	wp_mail( $email, $user_subject, $user_body, $user_headers );

	// Update rate limiter
	set_transient( $rate_key, $count + 1, HOUR_IN_SECONDS );

	wp_send_json_success( array(
		'message' => __( 'Your message has been sent successfully! We will get back to you within 24 hours.', 'astra' ),
	) );
}

// ============================================================
// ADMIN PANEL: Contact Messages
// ============================================================
add_action( 'admin_menu', 'gmcq_contact_admin_menu' );

function gmcq_contact_admin_menu() {
	add_submenu_page(
		'gmcq-dashboard',
		__( 'Contact Messages', 'astra' ),
		__( 'Contact Messages', 'astra' ),
		'manage_options',
		'gmcq-contact',
		'gmcq_contact_admin_page'
	);
}

add_action( 'admin_init', 'gmcq_contact_admin_actions' );

function gmcq_contact_admin_actions() {
	if ( ! isset( $_GET['page'] ) || 'gmcq-contact' !== $_GET['page'] ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Mark as read
	if ( isset( $_GET['action'], $_GET['id'] ) && 'read' === $_GET['action'] ) {
		$id = (int) $_GET['id'];
		check_admin_referer( 'gmcq_contact_read_' . $id );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'gmcq_contact_messages',
			array( 'is_read' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
		wp_safe_redirect( remove_query_arg( array( 'action', 'id', '_wpnonce' ) ) );
		exit;
	}

	// Mark as unread
	if ( isset( $_GET['action'], $_GET['id'] ) && 'unread' === $_GET['action'] ) {
		$id = (int) $_GET['id'];
		check_admin_referer( 'gmcq_contact_unread_' . $id );
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'gmcq_contact_messages',
			array( 'is_read' => 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
		wp_safe_redirect( remove_query_arg( array( 'action', 'id', '_wpnonce' ) ) );
		exit;
	}

	// Delete
	if ( isset( $_GET['action'], $_GET['id'] ) && 'delete' === $_GET['action'] ) {
		$id = (int) $_GET['id'];
		check_admin_referer( 'gmcq_contact_delete_' . $id );
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'gmcq_contact_messages',
			array( 'id' => $id ),
			array( '%d' )
		);
		wp_safe_redirect( remove_query_arg( array( 'action', 'id', '_wpnonce' ) ) );
		exit;
	}

	// Export CSV
	if ( isset( $_GET['export'] ) && 'csv' === $_GET['export'] ) {
		check_admin_referer( 'gmcq_contact_export' );
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}gmcq_contact_messages ORDER BY created_at DESC"
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=contact-messages-' . gmdate( 'Y-m-d' ) . '.csv' );
		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM
		fputcsv( $output, array( 'ID', 'Name', 'Email', 'Phone', 'Subject', 'Message', 'Status', 'Date' ) );
		foreach ( $rows as $row ) {
			fputcsv( $output, array(
				$row->id,
				$row->name,
				$row->email,
				$row->phone,
				$row->subject,
				$row->message,
				(int) $row->is_read ? 'Read' : 'Unread',
				$row->created_at,
			) );
		}
		fclose( $output );
		exit;
	}
}

function gmcq_contact_admin_page() {
	if ( isset( $_GET['view'] ) && (int) $_GET['view'] > 0 ) {
		gmcq_contact_admin_view_message( (int) $_GET['view'] );
		return;
	}
	gmcq_contact_admin_list_table();
}

function gmcq_contact_admin_view_message( $id ) {
	global $wpdb;
	$message = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_contact_messages WHERE id = %d",
			$id
		)
	);
	if ( ! $message ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Message not found.', 'astra' ) . '</p></div>';
		return;
	}

	// Mark as read
	if ( ! (int) $message->is_read ) {
		$wpdb->update(
			$wpdb->prefix . 'gmcq_contact_messages',
			array( 'is_read' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
		$message->is_read = 1;
	}

	$back_url = admin_url( 'admin.php?page=gmcq-contact' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Contact Message', 'astra' ); ?></h1>
		<a href="<?php echo esc_url( $back_url ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Messages', 'astra' ); ?></a>
		<hr>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Name', 'astra' ); ?></th><td><?php echo esc_html( $message->name ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Email', 'astra' ); ?></th><td><a href="mailto:<?php echo esc_attr( $message->email ); ?>"><?php echo esc_html( $message->email ); ?></a></td></tr>
			<tr><th><?php esc_html_e( 'Phone', 'astra' ); ?></th><td><?php echo esc_html( $message->phone ?: '—' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Subject', 'astra' ); ?></th><td><?php echo esc_html( $message->subject ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Message', 'astra' ); ?></th><td><pre style="white-space:pre-wrap;background:#f5f5f5;padding:15px;border-radius:6px;"><?php echo esc_html( $message->message ); ?></pre></td></tr>
			<tr><th><?php esc_html_e( 'Date', 'astra' ); ?></th><td><?php echo esc_html( $message->created_at ); ?></td></tr>
			<tr><th><?php esc_html_e( 'IP', 'astra' ); ?></th><td><?php echo esc_html( $message->ip_address ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Status', 'astra' ); ?></th><td><?php echo (int) $message->is_read ? '<span style="color:green">' . esc_html__( 'Read', 'astra' ) . '</span>' : '<span style="color:#cc8800">' . esc_html__( 'Unread', 'astra' ) . '</span>'; ?></td></tr>
		</table>
		<hr>
		<h2><?php esc_html_e( 'Quick Reply', 'astra' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gmcq_contact_reply_' . $id ); ?>
			<input type="hidden" name="action" value="gmcq_contact_reply">
			<input type="hidden" name="message_id" value="<?php echo (int) $id; ?>">
			<table class="form-table">
				<tr><th><label for="reply_subject"><?php esc_html_e( 'Subject', 'astra' ); ?></label></th>
				<td><input type="text" name="subject" id="reply_subject" class="regular-text" value="<?php echo esc_attr( 'Re: ' . $message->subject ); ?>"></td></tr>
				<tr><th><label for="reply_message"><?php esc_html_e( 'Message', 'astra' ); ?></label></th>
				<td><textarea name="message" id="reply_message" rows="8" class="large-text"></textarea></td></tr>
			</table>
			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Send Reply', 'astra' ); ?></button></p>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_gmcq_contact_reply', 'gmcq_contact_admin_reply' );

function gmcq_contact_admin_reply() {
	$id = isset( $_POST['message_id'] ) ? (int) $_POST['message_id'] : 0;
	if ( ! $id || ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'gmcq_contact_reply_' . $id ) ) {
		wp_die( 'Invalid request.' );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Permission denied.' );
	}

	global $wpdb;
	$message = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_contact_messages WHERE id = %d",
			$id
		)
	);
	if ( ! $message ) {
		wp_redirect( admin_url( 'admin.php?page=gmcq-contact' ) );
		exit;
	}

	$reply_subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
	$reply_message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );

	if ( empty( $reply_message ) ) {
		wp_die( 'Message cannot be empty.' );
	}

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
	);

	$body = '<h2>' . sprintf( __( 'Hello %s,', 'astra' ), esc_html( $message->name ) ) . '</h2>';
	$body .= '<p>' . nl2br( esc_html( $reply_message ) ) . '</p>';
	$body .= '<hr><p style="color:#999;font-size:12px;">' . __( 'This is a reply to your message:', 'astra' ) . '<br>' . esc_html( $message->message ) . '</p>';

	wp_mail( $message->email, $reply_subject, $body, $headers );

	wp_safe_redirect( admin_url( 'admin.php?page=gmcq-contact&view=' . $id . '&replied=1' ) );
	exit;
}

function gmcq_contact_admin_list_table() {
	global $wpdb;
	$per_page = 20;
	$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$offset   = ( $page - 1 ) * $per_page;
	$filter   = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';

	$where = '1=1';
	if ( 'unread' === $filter ) {
		$where = 'is_read = 0';
	} elseif ( 'read' === $filter ) {
		$where = 'is_read = 1';
	}

	$total = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_contact_messages WHERE {$where}"
	);

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_contact_messages WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$per_page,
			$offset
		)
	);

	$base_url = admin_url( 'admin.php?page=gmcq-contact' );
	$export_url = wp_nonce_url( $base_url . '&export=csv', 'gmcq_contact_export' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Contact Messages', 'astra' ); ?></h1>
		<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'astra' ); ?></a>
		<hr class="wp-header-end">

		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $base_url ); ?>" class="<?php echo 'all' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'astra' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( $base_url . '&filter=unread' ); ?>" class="<?php echo 'unread' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Unread', 'astra' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( $base_url . '&filter=read' ); ?>" class="<?php echo 'read' === $filter ? 'current' : ''; ?>"><?php esc_html_e( 'Read', 'astra' ); ?></a></li>
		</ul>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'astra' ); ?></th>
					<th><?php esc_html_e( 'Email', 'astra' ); ?></th>
					<th><?php esc_html_e( 'Subject', 'astra' ); ?></th>
					<th><?php esc_html_e( 'Status', 'astra' ); ?></th>
					<th><?php esc_html_e( 'Date', 'astra' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'astra' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No messages yet.', 'astra' ); ?></td></tr>
				<?php else : foreach ( $rows as $row ) : ?>
					<tr style="<?php echo ! (int) $row->is_read ? 'font-weight:600;' : ''; ?>">
						<td><?php echo esc_html( $row->name ); ?></td>
						<td><a href="mailto:<?php echo esc_attr( $row->email ); ?>"><?php echo esc_html( $row->email ); ?></a></td>
						<td><?php echo esc_html( $row->subject ); ?></td>
						<td><?php echo (int) $row->is_read ? '<span style="color:green">' . esc_html__( 'Read', 'astra' ) . '</span>' : '<span style="color:#cc8800">' . esc_html__( 'Unread', 'astra' ) . '</span>'; ?></td>
						<td><?php echo esc_html( $row->created_at ); ?></td>
						<td>
							<a href="<?php echo esc_url( $base_url . '&view=' . $row->id ); ?>"><?php esc_html_e( 'View', 'astra' ); ?></a>
							| <?php if ( (int) $row->is_read ) : ?>
								<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&action=unread&id=' . $row->id, 'gmcq_contact_unread_' . $row->id ) ); ?>"><?php esc_html_e( 'Mark Unread', 'astra' ); ?></a>
							<?php else : ?>
								<a href="<?php echo esc_url( wp_nonce_url( $base_url . '&action=read&id=' . $row->id, 'gmcq_contact_read_' . $row->id ) ); ?>"><?php esc_html_e( 'Mark Read', 'astra' ); ?></a>
							<?php endif; ?>
							| <a href="<?php echo esc_url( wp_nonce_url( $base_url . '&action=delete&id=' . $row->id, 'gmcq_contact_delete_' . $row->id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this message?', 'astra' ); ?>')" style="color:#dc3232"><?php esc_html_e( 'Delete', 'astra' ); ?></a>
						</td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>

		<?php if ( $total > $per_page ) : ?>
			<div class="tablenav" style="margin-top:15px;">
				<div class="tablenav-pages">
					<?php
					echo paginate_links( array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'current'   => $page,
						'total'     => ceil( $total / $per_page ),
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					) );
					?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

// ============================================================
// ENQUEUE ASSETS
// ============================================================
function gmcq_contact_enqueue_assets() {
	if ( ! is_page_template( 'page-contact.php' ) ) {
		return;
	}

	if ( ! wp_style_is( 'font-awesome', 'enqueued' ) ) {
		wp_enqueue_style(
			'font-awesome',
			'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
			array(),
			'6.5.1'
		);
	}

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

	$contact_css = get_stylesheet_directory() . '/gmcq-contact.css';
	if ( file_exists( $contact_css ) ) {
		wp_enqueue_style(
			'gmcq-contact',
			get_stylesheet_directory_uri() . '/gmcq-contact.css',
			array( 'gmcq-homepage' ),
			filemtime( $contact_css )
		);
	}

	$contact_js = get_stylesheet_directory() . '/gmcq-contact.js';
	if ( file_exists( $contact_js ) ) {
		wp_enqueue_script(
			'gmcq-contact',
			get_stylesheet_directory_uri() . '/gmcq-contact.js',
			array(), // No jQuery
			filemtime( $contact_js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'gmcq-contact',
			'gmcqContact',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gmcq_contact_nonce' ),
				'labels'  => array(
					'sending'    => __( 'Sending...', 'astra' ),
					'send'       => __( 'Send Message', 'astra' ),
					'success'    => __( 'Message Sent!', 'astra' ),
					'error'      => __( 'Please fix the errors above.', 'astra' ),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gmcq_contact_enqueue_assets' );

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main gmcq-homepage gmcq-contact-page">

		<!-- ============================================================ -->
		<!-- HEADER -->
		<!-- ============================================================ -->
		<section class="gmcq-contact-header">
			<div class="gmcq-container">
				<nav class="gmcq-contact-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'astra' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'astra' ); ?></a>
					<i class="fas fa-chevron-right" aria-hidden="true"></i>
					<span><?php esc_html_e( 'Contact Us', 'astra' ); ?></span>
				</nav>
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'Get in Touch', 'astra' ); ?></span>
					<h1 class="gmcq-section-title"><?php esc_html_e( 'Contact Us', 'astra' ); ?></h1>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Have a question, feedback, or need support? We\'d love to hear from you.', 'astra' ); ?></p>
				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- CONTENT: Form + Info -->
		<!-- ============================================================ -->
		<section class="gmcq-contact-content">
			<div class="gmcq-container">
				<div class="gmcq-contact-layout">

					<!-- ============ FORM ============ -->
					<div class="gmcq-contact-form-wrap gmcq-animate-fade-in-up">
						<div class="gmcq-contact-form-card">
							<h2 class="gmcq-contact-form-title"><?php esc_html_e( 'Send Us a Message', 'astra' ); ?></h2>
							<form id="gmcq-contact-form" class="gmcq-contact-form" method="post" novalidate>
								<!-- Honeypot -->
								<div style="display:none;visibility:hidden;position:absolute;left:-9999px;">
									<label for="gmcq-website"><?php esc_html_e( 'Website', 'astra' ); ?></label>
									<input type="text" id="gmcq-website" name="website" tabindex="-1" autocomplete="off">
								</div>

								<div class="gmcq-form-row gmcq-form-row-2">
									<div class="gmcq-form-group">
										<label for="gmcq-name"><?php esc_html_e( 'Your Name', 'astra' ); ?> <span class="gmcq-required">*</span></label>
										<input type="text" id="gmcq-name" name="name" class="gmcq-form-input" placeholder="<?php esc_attr_e( 'Enter your full name', 'astra' ); ?>" required maxlength="100" autocomplete="name">
										<span class="gmcq-form-error" id="gmcq-name-error"></span>
									</div>
									<div class="gmcq-form-group">
										<label for="gmcq-email"><?php esc_html_e( 'Your Email', 'astra' ); ?> <span class="gmcq-required">*</span></label>
										<input type="email" id="gmcq-email" name="email" class="gmcq-form-input" placeholder="<?php esc_attr_e( 'Enter your email address', 'astra' ); ?>" required autocomplete="email">
										<span class="gmcq-form-error" id="gmcq-email-error"></span>
									</div>
								</div>

								<div class="gmcq-form-row gmcq-form-row-2">
									<div class="gmcq-form-group">
										<label for="gmcq-phone"><?php esc_html_e( 'Phone Number', 'astra' ); ?> <span class="gmcq-optional"><?php esc_html_e( '(optional)', 'astra' ); ?></span></label>
										<input type="tel" id="gmcq-phone" name="phone" class="gmcq-form-input" placeholder="<?php esc_attr_e( '+91 98765 43210', 'astra' ); ?>" maxlength="20" autocomplete="tel">
										<span class="gmcq-form-error" id="gmcq-phone-error"></span>
									</div>
									<div class="gmcq-form-group">
										<label for="gmcq-subject"><?php esc_html_e( 'Subject', 'astra' ); ?> <span class="gmcq-required">*</span></label>
										<select id="gmcq-subject" name="subject" class="gmcq-form-select" required>
											<option value=""><?php esc_html_e( 'Select a subject...', 'astra' ); ?></option>
											<option value="general"><?php esc_html_e( 'General Inquiry', 'astra' ); ?></option>
											<option value="technical"><?php esc_html_e( 'Technical Support', 'astra' ); ?></option>
											<option value="report"><?php esc_html_e( 'Report a Problem', 'astra' ); ?></option>
											<option value="partnership"><?php esc_html_e( 'Partnership / Collaboration', 'astra' ); ?></option>
											<option value="feedback"><?php esc_html_e( 'Feedback / Suggestion', 'astra' ); ?></option>
											<option value="other"><?php esc_html_e( 'Other', 'astra' ); ?></option>
										</select>
										<span class="gmcq-form-error" id="gmcq-subject-error"></span>
									</div>
								</div>

								<div class="gmcq-form-group">
									<label for="gmcq-message"><?php esc_html_e( 'Your Message', 'astra' ); ?> <span class="gmcq-required">*</span></label>
									<textarea id="gmcq-message" name="message" class="gmcq-form-textarea" rows="6" placeholder="<?php esc_attr_e( 'Write your message here...', 'astra' ); ?>" required maxlength="2000"></textarea>
									<div class="gmcq-form-textarea-footer">
										<span class="gmcq-form-error" id="gmcq-message-error"></span>
										<span class="gmcq-form-counter" id="gmcq-message-counter">0 / 2000</span>
									</div>
								</div>

								<button type="submit" class="gmcq-btn gmcq-btn-primary gmcq-btn-large" id="gmcq-contact-submit">
									<i class="fas fa-paper-plane" aria-hidden="true"></i>
									<span id="gmcq-submit-text"><?php esc_html_e( 'Send Message', 'astra' ); ?></span>
									<div class="gmcq-btn-spinner" id="gmcq-submit-spinner" style="display:none;">
										<div class="gmcq-spinner-sm"></div>
									</div>
								</button>
							</form>
							<div id="gmcq-contact-success" class="gmcq-contact-success" style="display:none;">
								<div class="gmcq-success-icon">
									<i class="fas fa-check-circle"></i>
								</div>
								<h3><?php esc_html_e( 'Message Sent Successfully!', 'astra' ); ?></h3>
								<p><?php esc_html_e( 'Thank you for contacting us. We will get back to you within 24 hours.', 'astra' ); ?></p>
								<button type="button" class="gmcq-btn gmcq-btn-outline" id="gmcq-send-another">
									<?php esc_html_e( 'Send Another Message', 'astra' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- ============ CONTACT INFO ============ -->
					<div class="gmcq-contact-info-wrap gmcq-animate-fade-in-up" data-delay="150">
						<div class="gmcq-contact-info-card">
							<h2 class="gmcq-contact-info-title"><?php esc_html_e( 'Contact Information', 'astra' ); ?></h2>
							<p class="gmcq-contact-info-desc"><?php esc_html_e( 'Reach out to us through any of the following channels.', 'astra' ); ?></p>

							<div class="gmcq-contact-info-items">
								<div class="gmcq-contact-info-item">
									<div class="gmcq-contact-info-icon">
										<i class="fas fa-map-marker-alt"></i>
									</div>
									<div class="gmcq-contact-info-content">
										<h4><?php esc_html_e( 'Our Address', 'astra' ); ?></h4>
										<p><?php esc_html_e( '123, Education Hub, Sector 14, New Delhi - 110001, India', 'astra' ); ?></p>
									</div>
								</div>

								<div class="gmcq-contact-info-item">
									<div class="gmcq-contact-info-icon">
										<i class="fas fa-phone-alt"></i>
									</div>
									<div class="gmcq-contact-info-content">
										<h4><?php esc_html_e( 'Phone', 'astra' ); ?></h4>
										<p><a href="tel:+911234567890">+91 12345 67890</a></p>
									</div>
								</div>

								<div class="gmcq-contact-info-item">
									<div class="gmcq-contact-info-icon">
										<i class="fas fa-envelope"></i>
									</div>
									<div class="gmcq-contact-info-content">
										<h4><?php esc_html_e( 'Email', 'astra' ); ?></h4>
										<p><a href="mailto:support@governmentmcq.com">support@governmentmcq.com</a></p>
									</div>
								</div>

								<div class="gmcq-contact-info-item">
									<div class="gmcq-contact-info-icon">
										<i class="fas fa-clock"></i>
									</div>
									<div class="gmcq-contact-info-content">
										<h4><?php esc_html_e( 'Business Hours', 'astra' ); ?></h4>
										<p><?php esc_html_e( 'Monday - Friday: 9:00 AM - 6:00 PM', 'astra' ); ?><br>
										<?php esc_html_e( 'Saturday: 10:00 AM - 4:00 PM', 'astra' ); ?><br>
										<?php esc_html_e( 'Sunday: Closed', 'astra' ); ?></p>
									</div>
								</div>
							</div>

							<div class="gmcq-contact-social">
								<h4><?php esc_html_e( 'Follow Us', 'astra' ); ?></h4>
								<div class="gmcq-contact-social-icons">
									<a href="https://facebook.com" target="_blank" rel="noopener noreferrer" class="gmcq-social-icon facebook" aria-label="Facebook">
										<i class="fab fa-facebook-f"></i>
									</a>
									<a href="https://twitter.com" target="_blank" rel="noopener noreferrer" class="gmcq-social-icon twitter" aria-label="Twitter">
										<i class="fab fa-twitter"></i>
									</a>
									<a href="https://youtube.com" target="_blank" rel="noopener noreferrer" class="gmcq-social-icon youtube" aria-label="YouTube">
										<i class="fab fa-youtube"></i>
									</a>
									<a href="https://instagram.com" target="_blank" rel="noopener noreferrer" class="gmcq-social-icon instagram" aria-label="Instagram">
										<i class="fab fa-instagram"></i>
									</a>
									<a href="https://linkedin.com" target="_blank" rel="noopener noreferrer" class="gmcq-social-icon linkedin" aria-label="LinkedIn">
										<i class="fab fa-linkedin-in"></i>
									</a>
								</div>
							</div>
						</div>

						<!-- Map -->
						<div class="gmcq-contact-map">
							<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3504.042900104646!2d77.2167!3d28.5678!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMjjCsDM0JzA0LjEiTiA3N8KwMTMnMDAuMSJF!5e0!3m2!1sen!2sin!4v1" width="100%" height="240" style="border:0;border-radius:12px;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="<?php esc_attr_e( 'Our Location', 'astra' ); ?>"></iframe>
						</div>
					</div>

				</div>
			</div>
		</section>

		<!-- ============================================================ -->
		<!-- SECTION: FAQ -->
		<!-- ============================================================ -->
		<section class="gmcq-contact-faq">
			<div class="gmcq-container">
				<div class="gmcq-section-header gmcq-animate-fade-in-up">
					<span class="gmcq-section-badge"><?php esc_html_e( 'FAQ', 'astra' ); ?></span>
					<h2 class="gmcq-section-title"><?php esc_html_e( 'Frequently Asked Questions', 'astra' ); ?></h2>
					<p class="gmcq-section-desc"><?php esc_html_e( 'Find quick answers to common questions before reaching out.', 'astra' ); ?></p>
				</div>

				<div class="gmcq-faq-list gmcq-animate-fade-in-up">
					<?php
					$faqs = array(
						array(
							'q' => __( 'How do I reset my password?', 'astra' ),
							'a' => __( 'Go to the Login page and click "Forgot Password". Enter your registered email address and we will send you a password reset link.', 'astra' ),
						),
						array(
							'q' => __( 'How do I view my test results and progress?', 'astra' ),
							'a' => __( 'After completing a test, your results are displayed instantly. You can view your detailed performance history, including scores, accuracy, and rankings, from your Dashboard.', 'astra' ),
						),
						array(
							'q' => __( 'Can I retake a quiz or test?', 'astra' ),
							'a' => __( 'Yes, most tests allow unlimited attempts. Go to the test page and click "Attempt Now" to retake it. Your previous attempt history will be preserved.', 'astra' ),
						),
						array(
							'q' => __( 'How do I report a technical issue?', 'astra' ),
							'a' => __( 'Use the contact form on this page to report any technical issues. Please include details about the problem, the page URL, and your browser/device information for faster resolution.', 'astra' ),
						),
						array(
							'q' => __( 'Are the practice questions free?', 'astra' ),
							'a' => __( 'Yes, all our practice questions and mock tests are completely free. We believe in providing equal access to quality exam preparation for every aspirant.', 'astra' ),
						),
					);
					foreach ( $faqs as $index => $faq ) :
						?>
						<div class="gmcq-faq-item">
							<button class="gmcq-faq-question" aria-expanded="false">
								<span><?php echo esc_html( $faq['q'] ); ?></span>
								<i class="fas fa-chevron-down" aria-hidden="true"></i>
							</button>
							<div class="gmcq-faq-answer" style="display:none;">
								<p><?php echo esc_html( $faq['a'] ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

	</main>
</div>

<?php
get_footer();