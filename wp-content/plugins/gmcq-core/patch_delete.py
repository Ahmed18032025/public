import sys

file_path = "c:/Users/leonm/Local Sites/quizmanagementwebsite/app/public/wp-content/plugins/gmcq-core/includes/class-gmcq-categories.php"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

old_hook = """	add_action( 'wp_ajax_gmcq_bulk_categories', 'gmcq_ajax_bulk_categories' );
	add_action( 'wp_ajax_gmcq_search_categories', 'gmcq_ajax_search_categories' );
	add_action( 'wp_ajax_gmcq_get_subcategories', 'gmcq_ajax_get_subcategories' );
}"""

new_hook = """	add_action( 'wp_ajax_gmcq_bulk_categories', 'gmcq_ajax_bulk_categories' );
	add_action( 'wp_ajax_gmcq_search_categories', 'gmcq_ajax_search_categories' );
	add_action( 'wp_ajax_gmcq_get_subcategories', 'gmcq_ajax_get_subcategories' );
	add_action( 'wp_ajax_gmcq_delete_category', 'gmcq_ajax_delete_category' );
}"""

if old_hook in content:
    content = content.replace(old_hook, new_hook)
    print("Hook injected.")
else:
    print("Could not find old hook.")

old_func = """function gmcq_ajax_get_subcategories(): void {"""

new_func = """/**
 * AJAX handler: Delete category.
 */
function gmcq_ajax_delete_category(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid category ID.', 'gmcq' ) ) );
	}

	$result = gmcq_delete_category( $id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => __( 'Category deactivated successfully.', 'gmcq' ) ) );
}

function gmcq_ajax_get_subcategories(): void {"""

if old_func in content:
    content = content.replace(old_func, new_func)
    print("Function injected.")
else:
    print("Could not find old func.")

with open(file_path, "w", encoding="utf-8", newline="\n") as f:
    f.write(content)

print("Done.")