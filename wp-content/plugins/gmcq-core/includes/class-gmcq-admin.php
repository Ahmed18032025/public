<?php
/**
 * GMCQ Admin Bootstrap
 *
 * Stage 3.8 — Registers admin menus, dashboard page, and capability checks.
 * This makes the plugin "alive" inside WordPress admin.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register admin menus, scripts, and settings pages.
 *
 * Hooked to 'admin_menu' so menus appear in the WordPress sidebar.
 */
function gmcq_register_admin_menus(): void {
	// Main GMCQ menu (top-level)
	add_menu_page(
		__( 'GMCQ Dashboard', 'gmcq' ),          // Page title
		__( 'GMCQ', 'gmcq' ),                    // Menu title
		'manage_gmcq',                           // Capability required
		'gmcq-dashboard',                        // Menu slug
		'gmcq_render_dashboard_page',            // Callback function
		'dashicons-analytics',                    // Icon (WordPress dashicon)
		30                                       // Position (after Posts=5, before Media=10)
	);

	// Dashboard submenu (appears under GMCQ)
	add_submenu_page(
		'gmcq-dashboard',                        // Parent slug
		__( 'GMCQ Dashboard', 'gmcq' ),          // Page title
		__( 'Dashboard', 'gmcq' ),               // Menu title
		'manage_gmcq',                           // Capability
		'gmcq-dashboard',                        // Menu slug (same as parent)
		'gmcq_render_dashboard_page'             // Callback
	);

	// Categories submenu
	add_submenu_page(
		'gmcq-dashboard',
		__( 'Categories', 'gmcq' ),
		__( 'Categories', 'gmcq' ),
		'manage_gmcq',
		'gmcq-categories',
		'gmcq_render_categories_page'
	);

	// Questions submenu (placeholder)
	add_submenu_page(
		'gmcq-dashboard',
		__( 'Questions', 'gmcq' ),
		__( 'Questions', 'gmcq' ),
		'manage_gmcq',
		'gmcq-questions',
		'gmcq_render_questions_placeholder'
	);

	// Quizzes submenu (placeholder)
	add_submenu_page(
		'gmcq-dashboard',
		__( 'Quizzes', 'gmcq' ),
		__( 'Quizzes', 'gmcq' ),
		'manage_gmcq',
		'gmcq-quizzes',
		'gmcq_render_quizzes_placeholder'
	);

	// Import submenu (placeholder)
	add_submenu_page(
		'gmcq-dashboard',
		__( 'CSV Import', 'gmcq' ),
		__( 'CSV Import', 'gmcq' ),
		'manage_gmcq',
		'gmcq-import',
		'gmcq_render_import_placeholder'
	);

	// Reports submenu (placeholder)
	add_submenu_page(
		'gmcq-dashboard',
		__( 'Reports', 'gmcq' ),
		__( 'Reports', 'gmcq' ),
		'manage_gmcq',
		'gmcq-reports',
		'gmcq_render_reports_placeholder'
	);

	// Settings submenu (bottom, separated)
	add_submenu_page(
		'gmcq-dashboard',
		__( 'Settings', 'gmcq' ),
		__( 'Settings', 'gmcq' ),
		'manage_gmcq',
		'gmcq-settings',
		'gmcq_render_settings_placeholder'
	);
}
add_action( 'admin_menu', 'gmcq_register_admin_menus' );

/**
 * Enqueue admin CSS and JS assets.
 *
 * Hooked to 'admin_enqueue_scripts' — only loads on GMCQ pages.
 */
function gmcq_admin_enqueue_scripts( string $hook ): void {
	// Only load on GMCQ admin pages
	if ( strpos( $hook, 'gmcq-' ) === false && $hook !== 'toplevel_page_gmcq-dashboard' ) {
		return;
	}

	$upload_dir = wp_upload_dir();
	$css_url    = $upload_dir['baseurl'] . '/gmcq-assets/css/admin.css';

	wp_enqueue_style(
		'gmcq-admin',
		$css_url,
		array(),
		GMCQ_VERSION
	);

	wp_localize_script( 'jquery', 'gmcqAdmin', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'gmcq_category_nonce' ),
		'version' => GMCQ_VERSION,
	) );
}
add_action( 'admin_enqueue_scripts', 'gmcq_admin_enqueue_scripts' );

/**
 * Create the assets directory structure on activation.
 */
function gmcq_create_assets_dir(): void {
	$upload_dir = wp_upload_dir();
	$assets_dir = $upload_dir['basedir'] . '/gmcq-assets';

	if ( ! file_exists( $assets_dir ) ) {
		wp_mkdir_p( $assets_dir );
	}

	// Create CSS directory
	$css_dir = $assets_dir . '/css';
	if ( ! file_exists( $css_dir ) ) {
		wp_mkdir_p( $css_dir );
		$css_content = '/* GMCQ Admin Styles */' . PHP_EOL
			. '.gmcq-dashboard-wrap { padding: 20px 0; }' . PHP_EOL
			. '.gmcq-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 10px 0; }' . PHP_EOL
			. '.gmcq-card h2 { margin-top: 0; }' . PHP_EOL
			. '.gmcq-status-ok { color: #46b450; font-weight: 600; }' . PHP_EOL
			. '.gmcq-status-warning { color: #ffb900; font-weight: 600; }' . PHP_EOL;
		@file_put_contents( $css_dir . '/admin.css', $css_content );
	}
}
add_action( 'admin_init', 'gmcq_create_assets_dir' );

/**
 * Render the GMCQ Dashboard page.
 */
function gmcq_render_dashboard_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	// Get plugin status info
	$version   = GMCQ_VERSION;
	$db_option = get_option( 'gmcq_db_version', '0' );
	$settings  = get_option( 'gmcq_settings', array() );
	$has_settings = ! empty( $settings );

	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1>
			<span class="dashicons dashicons-analytics" style="font-size: 30px; margin-right: 8px;"></span>
			<?php esc_html_e( 'GMCQ Quiz Engine', 'gmcq' ); ?>
		</h1>
		<p class="description">
			<?php
			printf(
				/* translators: %s: plugin version */
				esc_html__( 'Version %s — MCQ Quiz Management System for WordPress', 'gmcq' ),
				esc_html( $version )
			);
			?>
		</p>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'System Status', 'gmcq' ); ?></h2>
			<table class="widefat" style="max-width: 600px;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Plugin Version', 'gmcq' ); ?></strong></td>
						<td><span class="gmcq-status-ok"><?php echo esc_html( $version ); ?></span></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Database Version', 'gmcq' ); ?></strong></td>
						<td><span class="gmcq-status-ok"><?php echo esc_html( $db_option ); ?></span></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Database Tables', 'gmcq' ); ?></strong></td>
						<td>
							<?php
							$schema = gmcq_get_schema_contract();
							printf(
								'<span class="gmcq-status-ok">%s</span>',
								sprintf(
									/* translators: %d: number of tables */
									esc_html__( '%d tables defined', 'gmcq' ),
									count( $schema )
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Settings', 'gmcq' ); ?></strong></td>
						<td>
							<?php if ( $has_settings ) : ?>
								<span class="gmcq-status-ok"><?php esc_html_e( 'Configured', 'gmcq' ); ?></span>
							<?php else : ?>
								<span class="gmcq-status-warning"><?php esc_html_e( 'Using defaults', 'gmcq' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Quick Start', 'gmcq' ); ?></h2>
			<ol>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories' ) ); ?>">
						<?php esc_html_e( 'Create Categories', 'gmcq' ); ?>
					</a>
					— <?php esc_html_e( 'Organize questions into categories (2-level hierarchy)', 'gmcq' ); ?>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-questions' ) ); ?>">
						<?php esc_html_e( 'Add Questions', 'gmcq' ); ?>
					</a>
					— <?php esc_html_e( 'Create MCQ questions with explanations', 'gmcq' ); ?>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-quizzes' ) ); ?>">
						<?php esc_html_e( 'Create Quizzes', 'gmcq' ); ?>
					</a>
					— <?php esc_html_e( 'Build quizzes with question sets', 'gmcq' ); ?>
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-reports' ) ); ?>">
						<?php esc_html_e( 'View Reports', 'gmcq' ); ?>
					</a>
					— <?php esc_html_e( 'Track quiz performance and results', 'gmcq' ); ?>
				</li>
			</ol>
		</div>
	</div>
	<?php
}

/**
 * Render the Categories page (placeholder).
 */
function gmcq_render_categories_page(): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
	if ( 'add' === $action ) {
		gmcq_render_category_add_form();
		return;
	} elseif ( 'edit' === $action && isset( $_GET['id'] ) ) {
		gmcq_render_category_edit_form( (int) $_GET['id'] );
		return;
	}

	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1>
			<?php
			printf(
				'<a href="%s">%s</a> &rsaquo; %s',
				esc_url( admin_url( 'admin.php?page=gmcq-dashboard' ) ),
				esc_html__( 'GMCQ', 'gmcq' ),
				esc_html__( 'Categories', 'gmcq' )
			);
			?>
		</h1>

		<div class="gmcq-card">
			<h2><?php esc_html_e( 'Category Management', 'gmcq' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: link to create a new category */
					esc_html__( 'Manage exam categories for organizing questions. %s to get started.', 'gmcq' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=gmcq-categories&action=add' ) ) . '">' . esc_html__( 'Add New Category', 'gmcq' ) . '</a>'
				);
				?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Categories support 2-level hierarchy: Parent → Child. No deeper nesting.', 'gmcq' ); ?>
			</p>

			<?php
			// Show existing categories if any
			$tree = gmcq_get_category_tree();
			$categories = array();
			
			// Flatten the tree so children appear directly under their parents
			foreach ( $tree as $parent ) {
				$categories[] = $parent;
				if ( ! empty( $parent->children ) ) {
					foreach ( $parent->children as $child ) {
						$categories[] = $child;
					}
				}
			}

			if ( ! empty( $categories ) ) :
			?>
			<table class="wp-list-table widefat fixed striped" style="max-width: 800px; margin-top: 15px;">
				<thead>
					<tr>
						<th style="width: 40px;"><?php esc_html_e( 'ID', 'gmcq' ); ?></th>
						<th style="width: 200px;"><?php esc_html_e( 'Name', 'gmcq' ); ?></th>
						<th style="width: 150px;"><?php esc_html_e( 'Slug', 'gmcq' ); ?></th>
						<th style="width: 60px;"><?php esc_html_e( 'Questions', 'gmcq' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Status', 'gmcq' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $categories as $cat ) : ?>
					<tr>
						<td><?php echo esc_html( $cat->id ); ?></td>
						<td>
							<?php
							$indent = ! empty( $cat->parent_id ) ? '&mdash;&mdash; ' : '';
							echo $indent . '<strong>' . esc_html( $cat->name ) . '</strong>';
							?>
							<div class="row-actions" style="font-size: 13px;">
								<span class="edit"><a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories&action=edit&id=' . $cat->id ) ); ?>"><?php esc_html_e( 'Edit', 'gmcq' ); ?></a> | </span>
								<?php if ( 1 === (int) $cat->is_active ) : ?>
<span class="trash"><a href="#" class="gmcq-delete-cat" data-id="<?php echo esc_attr( $cat->id ); ?>" style="color: #dc3232;"><?php esc_html_e( 'Remove', 'gmcq' ); ?></a></span>
								<?php else : ?>
									<span class="activate"><a href="#" class="gmcq-activate-cat" data-id="<?php echo esc_attr( $cat->id ); ?>" style="color: #46b450;"><?php esc_html_e( 'Activate', 'gmcq' ); ?></a></span>
								<?php endif; ?>
							</div>
						</td>
						<td><code><?php echo esc_html( $cat->slug ); ?></code></td>
						<td><?php echo esc_html( $cat->question_count ); ?></td>
						<td>
							<?php if ( 1 === (int) $cat->is_active ) : ?>
								<span class="gmcq-status-ok"><?php esc_html_e( 'Active', 'gmcq' ); ?></span>
							<?php else : ?>
								<span class="gmcq-status-warning"><?php esc_html_e( 'Inactive', 'gmcq' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php else : ?>
			<div class="gmcq-card" style="text-align: center; padding: 40px;">
				<p style="font-size: 16px; color: #666;">
					<?php esc_html_e( 'No categories created yet. Start by adding your first category.', 'gmcq' ); ?>
				</p>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<script>
	jQuery(document).ready(function($) {
$('.gmcq-delete-cat, .gmcq-deactivate-cat, .gmcq-activate-cat').on('click', function(e) {
			e.preventDefault();
			var $link = $(this);
			var id = $link.data('id');
			var isDeactivate = $link.hasClass('gmcq-deactivate-cat');
			var action = isDeactivate ? 'gmcq_deactivate_category' : 'gmcq_activate_category';
			var confirmMsg = isDeactivate 
				? '<?php esc_html_e( 'Are you sure you want to deactivate this category?', 'gmcq' ); ?>' 
				: '<?php esc_html_e( 'Are you sure you want to activate this category?', 'gmcq' ); ?>';
			
			if (!confirm(confirmMsg)) return;
			
			$link.css('opacity', '0.5');
			
			$.post(gmcqAdmin.ajaxUrl, {
				action: action,
				id: id,
				_ajax_nonce: gmcqAdmin.nonce
			}, function(res) {
				if (res.success) {
					window.location.reload();
				} else {
					alert(res.data.message || 'Error updating category.');
					$link.css('opacity', '1');
				}
			}).fail(function() {
				alert('Server error.');
				$link.css('opacity', '1');
			});
		});
	});
	</script>
	<?php
}

/**
 * Render the Add Category form.
 */
function gmcq_render_category_add_form(): void {
	// Get active top-level categories to populate the Parent dropdown
	$parents_result = gmcq_get_categories( array( 'parent_only' => true, 'filter' => 'active' ) );
	$parents = $parents_result['categories'];
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php esc_html_e( 'Add New Category', 'gmcq' ); ?></h1>
		
		<div class="gmcq-card" style="max-width: 800px;">
			<form id="gmcq-add-category-form">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gmcq-cat-name"><?php esc_html_e( 'Name', 'gmcq' ); ?> <span style="color:red;">*</span></label></th>
						<td><input name="name" type="text" id="gmcq-cat-name" class="regular-text" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-cat-slug"><?php esc_html_e( 'Slug', 'gmcq' ); ?></label></th>
						<td>
							<input name="slug" type="text" id="gmcq-cat-slug" class="regular-text">
							<p class="description"><?php esc_html_e( 'Leave empty to auto-generate from the name.', 'gmcq' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-cat-parent"><?php esc_html_e( 'Parent Category', 'gmcq' ); ?></label></th>
						<td>
							<select name="parent_id" id="gmcq-cat-parent">
								<option value=""><?php esc_html_e( 'None (Top Level)', 'gmcq' ); ?></option>
								<?php foreach ( $parents as $parent ) : ?>
									<option value="<?php echo esc_attr( $parent->id ); ?>"><?php echo esc_html( $parent->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Categories support 2-level hierarchy only.', 'gmcq' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-cat-desc"><?php esc_html_e( 'Description', 'gmcq' ); ?></label></th>
						<td><textarea name="description" id="gmcq-cat-desc" rows="4" class="regular-text"></textarea></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Category', 'gmcq' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel', 'gmcq' ); ?></a>
				</p>
			</form>
			<div id="gmcq-form-response" style="margin-top: 15px; padding: 10px; display: none; border-left: 4px solid transparent;"></div>
		</div>
	</div>
	<script>
	jQuery(document).ready(function($) {
		$('#gmcq-add-category-form').on('submit', function(e) {
			e.preventDefault();
			var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Saving...');
			var $response = $('#gmcq-form-response').hide();
			
			$.post(gmcqAdmin.ajaxUrl, $(this).serialize() + '&action=gmcq_add_category&_ajax_nonce=' + gmcqAdmin.nonce, function(res) {
				if (res.success) {
					$response.css('border-color', '#46b450').text(res.data.message).fadeIn();
					setTimeout(function() { window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories' ) ); ?>'; }, 1000);
				} else {
					$response.css('border-color', '#dc3232').text(res.data.message || 'Error saving category.').fadeIn();
					$btn.prop('disabled', false).text('<?php esc_html_e( 'Save Category', 'gmcq' ); ?>');
				}
			}).fail(function() {
				$response.css('border-color', '#dc3232').text('Server error.').fadeIn();
				$btn.prop('disabled', false).text('<?php esc_html_e( 'Save Category', 'gmcq' ); ?>');
			});
		});
	});
	</script>
	<?php
}

/**
 * Render the Edit Category form.
 *
 * @param int $category_id The ID of the category to edit.
 */
function gmcq_render_category_edit_form( int $category_id ): void {
	$category = gmcq_get_category( $category_id );
	
	if ( ! $category ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Category not found.', 'gmcq' ) . '</p></div>';
		return;
	}

	$parents_result = gmcq_get_categories( array( 'parent_only' => true, 'filter' => 'active' ) );
	$parents = $parents_result['categories'];
	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1><?php esc_html_e( 'Edit Category', 'gmcq' ); ?></h1>
		
		<div class="gmcq-card" style="max-width: 800px;">
			<form id="gmcq-edit-category-form">
				<input type="hidden" name="id" value="<?php echo esc_attr( $category->id ); ?>">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="gmcq-cat-name"><?php esc_html_e( 'Name', 'gmcq' ); ?> <span style="color:red;">*</span></label></th>
						<td><input name="name" type="text" id="gmcq-cat-name" class="regular-text" value="<?php echo esc_attr( $category->name ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-cat-slug"><?php esc_html_e( 'Slug', 'gmcq' ); ?></label></th>
						<td>
							<input name="slug" type="text" id="gmcq-cat-slug" class="regular-text" value="<?php echo esc_attr( $category->slug ); ?>">
							<p class="description"><?php esc_html_e( 'Leave empty to auto-generate from the name.', 'gmcq' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-cat-parent"><?php esc_html_e( 'Parent Category', 'gmcq' ); ?></label></th>
						<td>
<select name="parent_id" id="gmcq-cat-parent">
								<option value=""><?php esc_html_e( 'None (Top Level)', 'gmcq' ); ?></option>
								<?php foreach ( $parents as $parent ) : 
									if ( $parent->id === $category->id ) continue; // Cannot be own parent
								?>
									<option value="<?php echo esc_attr( $parent->id ); ?>" <?php selected( $category->parent_id, $parent->id ); ?>><?php echo esc_html( $parent->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Categories support 2-level hierarchy only. Categories with subcategories cannot have a parent.', 'gmcq' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gmcq-cat-desc"><?php esc_html_e( 'Description', 'gmcq' ); ?></label></th>
						<td><textarea name="description" id="gmcq-cat-desc" rows="4" class="regular-text"><?php echo esc_textarea( $category->description ); ?></textarea></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Update Category', 'gmcq' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel', 'gmcq' ); ?></a>
				</p>
			</form>
			<div id="gmcq-form-response" style="margin-top: 15px; padding: 10px; display: none; border-left: 4px solid transparent;"></div>
		</div>
	</div>
	<script>
	jQuery(document).ready(function($) {
		$('#gmcq-edit-category-form').on('submit', function(e) {
			e.preventDefault();
			var $btn = $(this).find('button[type="submit"]').prop('disabled', true).text('Updating...');
			var $response = $('#gmcq-form-response').hide();
			
			$.post(gmcqAdmin.ajaxUrl, $(this).serialize() + '&action=gmcq_update_category&_ajax_nonce=' + gmcqAdmin.nonce, function(res) {
				if (res.success) {
					$response.css('border-color', '#46b450').text(res.data.message).fadeIn();
					setTimeout(function() { window.location.href = '<?php echo esc_url( admin_url( 'admin.php?page=gmcq-categories' ) ); ?>'; }, 1000);
				} else {
					$response.css('border-color', '#dc3232').text(res.data.message || 'Error updating category.').fadeIn();
					$btn.prop('disabled', false).text('<?php esc_html_e( 'Update Category', 'gmcq' ); ?>');
				}
			}).fail(function() {
				$response.css('border-color', '#dc3232').text('Server error.').fadeIn();
				$btn.prop('disabled', false).text('<?php esc_html_e( 'Update Category', 'gmcq' ); ?>');
			});
		});
	});
	</script>
	<?php
}

/**
 * Render placeholder page for sections not yet implemented.
 *
 * @param string $section_name The section name to display.
 */
function gmcq_render_placeholder_page( string $section_name ): void {
	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'gmcq' ) );
	}

	?>
	<div class="wrap gmcq-dashboard-wrap">
		<h1>
			<?php
			printf(
				'<a href="%s">%s</a> &rsaquo; %s',
				esc_url( admin_url( 'admin.php?page=gmcq-dashboard' ) ),
				esc_html__( 'GMCQ', 'gmcq' ),
				esc_html( $section_name )
			);
			?>
		</h1>

		<div class="gmcq-card" style="text-align: center; padding: 60px;">
			<span class="dashicons dashicons-clipboard" style="font-size: 48px; color: #999;"></span>
			<h2 style="margin-top: 15px; color: #666;">
				<?php
				printf(
					/* translators: %s: section name */
					esc_html__( '%s — Coming Soon', 'gmcq' ),
					esc_html( $section_name )
				);
				?>
			</h2>
			<p style="color: #999;">
				<?php esc_html_e( 'This section is under development and will be available in a future update.', 'gmcq' ); ?>
			</p>
		</div>
	</div>
	<?php
}

function gmcq_render_questions_placeholder(): void {
	gmcq_render_placeholder_page( __( 'Questions', 'gmcq' ) );
}

function gmcq_render_quizzes_placeholder(): void {
	gmcq_render_placeholder_page( __( 'Quizzes', 'gmcq' ) );
}

function gmcq_render_import_placeholder(): void {
	gmcq_render_placeholder_page( __( 'CSV Import', 'gmcq' ) );
}

function gmcq_render_reports_placeholder(): void {
	gmcq_render_placeholder_page( __( 'Reports', 'gmcq' ) );
}

function gmcq_render_settings_placeholder(): void {
	gmcq_render_placeholder_page( __( 'Settings', 'gmcq' ) );
}