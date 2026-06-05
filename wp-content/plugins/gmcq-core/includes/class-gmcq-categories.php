<?php

defined( 'ABSPATH' ) || exit;

/**
 * ============================================================
 * Stage 3 — Categories Module (Phase 1)
 * ============================================================
 *
 * CRUD + Deactivate/Activate for the gmcq_categories table.
 * Phase 1: No soft delete, no merge, no import/export.
 * Structure: 2 levels only (Parent → Child).
 */

/**
 * Create a new category.
 *
 * @param array $data {
 *     Category data.
 *     @type string $name        Required. Category name.
 *     @type string $slug        Optional. Auto-generated from name if empty.
 *     @type int    $parent_id   Optional. Parent category ID (NULL for top-level).
 *     @type string $description Optional. Category description.
 *     @type int    $sort_order  Optional. Display order.
 *     @type int    $created_by  Optional. WP user ID. Defaults to current user.
 * }
 * @return int|\WP_Error Category ID on success, WP_Error on failure.
 */
function gmcq_create_category( array $data ) {
	global $wpdb;

	$validation = gmcq_validate_category_data( $data, 'create' );
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$parent_id = ! empty( $data['parent_id'] ) ? (int) $data['parent_id'] : null;
	$name = sanitize_text_field( $data['name'] );
	$slug_base = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );

	// Implement hierarchical slugs: prefix child with parent slug (e.g., ssc-mts)
	if ( $parent_id ) {
		$parent = gmcq_get_category( $parent_id );
		if ( $parent && ! str_starts_with( $slug_base, $parent->slug . '-' ) ) {
			$slug_base = $parent->slug . '-' . $slug_base;
		}
	}

	// Ensure unique slug
	$slug = gmcq_make_category_slug_unique( $slug_base, 0 );
	$created_by = ! empty( $data['created_by'] ) ? (int) $data['created_by'] : get_current_user_id();

	$insert_data = array(
		'name'        => $name,
		'slug'        => $slug,
		'description' => ! empty( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
		'sort_order'  => ! empty( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
		'created_by'  => $created_by,
	);

	$insert_format = array( '%s', '%s', '%s', '%d', '%d' );

	// Only include parent_id in the query if it is not null
	if ( null !== $parent_id ) {
		$insert_data['parent_id'] = $parent_id;
		$insert_format[]          = '%d';
	}

	$wpdb->insert(
		$wpdb->prefix . 'gmcq_categories',
		$insert_data,
		$insert_format
	);

	if ( ! empty( $wpdb->last_error ) ) {
		return new WP_Error( 'db_error', 'Database error: ' . $wpdb->last_error );
	}

	$category_id = (int) $wpdb->insert_id;
	if ( ! $category_id ) {
		return new WP_Error( 'db_insert_failed', 'Failed to create category.' );
	}

	// Invalidate dashboard caches
	gmcq_clear_dashboard_cache( 'category' );

	do_action( 'gmcq_category_created', $category_id, $insert_data );

	return $category_id;
}

/**
 * Get a single category by ID.
 *
 * @param int $category_id Category ID.
 * @return object|null Category row object, or null if not found.
 */
function gmcq_get_category( int $category_id ) {
	global $wpdb;

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gmcq_categories WHERE id = %d",
			$category_id
		)
	);
}

/**
 * Get categories with optional filters.
 *
 * @param array $args {
 *     Optional. Query arguments.
 *     @type string $filter     Optional. Filter: 'all', 'active', 'inactive', 'no_children', 'no_questions'.
 *     @type int    $parent_id  Optional. Get children of specific parent.
 *     @type bool   $parent_only Optional. Get only top-level categories (parent_id IS NULL).
 *     @type string $search     Optional. Search term for name/slug.
 *     @type string $orderby    Optional. Order by column. Default 'sort_order'.
 *     @type string $order      Optional. ASC or DESC. Default 'ASC'.
 *     @type int    $per_page   Optional. Results per page. Default -1 (all).
 *     @type int    $page       Optional. Page number. Default 1.
 * }
 * @return array {
 *     @type array  $categories Array of category objects.
 *     @type int    $total      Total matching categories.
 * }
 */
function gmcq_get_categories( array $args = array() ): array {
	global $wpdb;

	$defaults = array(
		'filter'      => 'all',
		'parent_id'   => null,
		'parent_only' => false,
		'search'      => '',
		'orderby'     => 'sort_order',
		'order'       => 'ASC',
		'per_page'    => -1,
		'page'        => 1,
	);
	$args  = wp_parse_args( $args, $defaults );
	$p     = $wpdb->prefix;

	$where   = array( '1=1' );
	$prepare = array();

	// Filter by status
	switch ( $args['filter'] ) {
		case 'active':
			$where[] = 'c.is_active = 1';
			break;
		case 'inactive':
			$where[] = 'c.is_active = 0';
			break;
		case 'no_children':
			$where[] = 'c.parent_id IS NULL';
			$where[] = 'NOT EXISTS (SELECT 1 FROM ' . $p . 'gmcq_categories child WHERE child.parent_id = c.id)';
			break;
		case 'no_questions':
			$where[] = 'c.question_count = 0';
			break;
		case 'all':
		default:
			// No filter
			break;
	}

	// Parent filter
	if ( null !== $args['parent_id'] ) {
		$where[]  = 'c.parent_id = %d';
		$prepare[] = (int) $args['parent_id'];
	} elseif ( $args['parent_only'] ) {
		$where[] = 'c.parent_id IS NULL';
	}

	// Search
	if ( ! empty( $args['search'] ) ) {
		$search   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[]  = '(c.name LIKE %s OR c.slug LIKE %s)';
		$prepare[] = $search;
		$prepare[] = $search;
	}

	$where_clause = implode( ' AND ', $where );

	// Count total
	$total = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$p}gmcq_categories c WHERE {$where_clause}",
			$prepare
		)
	);

	// Order
	$allowed_orderby = array( 'id', 'name', 'slug', 'sort_order', 'question_count', 'created_at', 'updated_at' );
	$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';
	$order   = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

	$limit  = '';
	$offset = '';
	if ( $args['per_page'] > 0 ) {
		$limit    = ' LIMIT %d';
		$prepare[] = (int) $args['per_page'];
		$offset   = ' OFFSET %d';
		$prepare[] = ( (int) $args['page'] - 1 ) * (int) $args['per_page'];
	}

	$categories = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.* FROM {$p}gmcq_categories c WHERE {$where_clause} ORDER BY c.{$orderby} {$order}{$limit}{$offset}",
			$prepare
		)
	);

	return array(
		'categories' => $categories ?: array(),
		'total'      => $total,
	);
}

/**
 * Update an existing category.
 *
 * @param int   $category_id Category ID.
 * @param array $data {
 *     Category data fields to update.
 *     @type string $name        Optional. Category name.
 *     @type string $slug        Optional. Slug.
 *     @type int    $parent_id   Optional. Parent category ID.
 *     @type string $description Optional. Description.
 *     @type int    $sort_order  Optional. Display order.
 * }
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_update_category( int $category_id, array $data ) {
	global $wpdb;

	$category = gmcq_get_category( $category_id );
	if ( ! $category ) {
		return new WP_Error( 'not_found', 'Category not found.' );
	}

	$validation = gmcq_validate_category_data( $data, 'update', $category_id );
	if ( is_wp_error( $validation ) ) {
		return $validation;
	}

	$update_data   = array();
	$update_format = array();

	if ( isset( $data['name'] ) ) {
		$name = sanitize_text_field( $data['name'] );
		if ( $name !== $category->name ) {
			$update_data['name'] = $name;
			$update_format[]     = '%s';
		}
	}

	$new_parent_id = isset( $data['parent_id'] ) ? ( ! empty( $data['parent_id'] ) ? (int) $data['parent_id'] : null ) : ( ! empty( $category->parent_id ) ? (int) $category->parent_id : null );
	$slug_source = null;

	if ( isset( $data['slug'] ) ) {
		$slug_source = sanitize_title( $data['slug'] );
	} elseif ( isset( $data['name'] ) && ! isset( $data['slug'] ) ) {
		$slug_source = sanitize_title( $data['name'] );
	}

	// If slug base or parent changed, recalculate the hierarchical slug
	if ( null !== $slug_source || ( isset( $data['parent_id'] ) && (int) $data['parent_id'] !== (int) $category->parent_id ) ) {
		$final_slug_base = ( null !== $slug_source ) ? $slug_source : $category->slug;
		
		if ( $new_parent_id ) {
			$parent = gmcq_get_category( $new_parent_id );
			if ( $parent ) {
				// Strip old parent prefix if moving between parents
				if ( ! empty( $category->parent_id ) && (int) $category->parent_id !== (int) $new_parent_id ) {
					$old_parent = gmcq_get_category( (int) $category->parent_id );
					if ( $old_parent ) {
						$final_slug_base = preg_replace( '/^' . preg_quote( $old_parent->slug, '/' ) . '-/', '', $final_slug_base );
					}
				}
				
				// Apply new parent prefix
				if ( ! str_starts_with( $final_slug_base, $parent->slug . '-' ) ) {
					$final_slug_base = $parent->slug . '-' . $final_slug_base;
				}
			}
		} elseif ( ! empty( $category->parent_id ) ) {
			// If moving from child to top-level, strip the old parent prefix
			$old_parent = gmcq_get_category( (int) $category->parent_id );
			if ( $old_parent ) {
				$final_slug_base = preg_replace( '/^' . preg_quote( $old_parent->slug, '/' ) . '-/', '', $final_slug_base );
			}
		}

		if ( $final_slug_base !== $category->slug ) {
			$update_data['slug'] = gmcq_make_category_slug_unique( $final_slug_base, $category_id );
			$update_format[]     = '%s';
		}
	}

	if ( isset( $data['parent_id'] ) ) {
		$new_parent_id = ! empty( $data['parent_id'] ) ? (int) $data['parent_id'] : null;
		$old_parent_id = ! empty( $category->parent_id ) ? (int) $category->parent_id : null;
		if ( $new_parent_id !== $old_parent_id ) {
			$update_data['parent_id'] = $new_parent_id;
			$update_format[]          = ( null === $new_parent_id ) ? null : '%d';
		}
	}

	if ( isset( $data['description'] ) ) {
		$description = sanitize_textarea_field( $data['description'] );
		if ( $description !== $category->description ) {
			$update_data['description'] = $description;
			$update_format[]            = '%s';
		}
	}

	if ( isset( $data['sort_order'] ) ) {
		$sort_order = (int) $data['sort_order'];
		if ( $sort_order !== (int) $category->sort_order ) {
			$update_data['sort_order'] = $sort_order;
			$update_format[]           = '%d';
		}
	}

	if ( empty( $update_data ) ) {
		return true; // Nothing to update
	}

	$updated = $wpdb->update(
		$wpdb->prefix . 'gmcq_categories',
		$update_data,
		array( 'id' => $category_id ),
		$update_format,
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'db_error', 'Database error: ' . $wpdb->last_error );
	}

	gmcq_clear_dashboard_cache( 'category' );

	do_action( 'gmcq_category_updated', $category_id, $update_data );

	return true;
}

/**
 * Set category is_active = 0 (deactivate).
 *
 * @param int $category_id Category ID.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_deactivate_category( int $category_id ) {
	global $wpdb;

	$category = gmcq_get_category( $category_id );
	if ( ! $category ) {
		return new WP_Error( 'not_found', 'Category not found.' );
	}

	if ( 0 === (int) $category->is_active ) {
		return true; // Already inactive
	}

	// Check if category has children — allow deactivation but warn
	$has_children = gmcq_category_has_children( $category_id );
	if ( $has_children ) {
		// Deactivate all children recursively
		$children = gmcq_get_category_children_ids( $category_id );
		foreach ( $children as $child_id ) {
			gmcq_deactivate_category( $child_id );
		}
	}

	$updated = $wpdb->update(
		$wpdb->prefix . 'gmcq_categories',
		array( 'is_active' => 0 ),
		array( 'id' => $category_id ),
		array( '%d' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'db_error', 'Database error: ' . $wpdb->last_error );
	}

	gmcq_clear_dashboard_cache( 'category' );

	do_action( 'gmcq_category_deactivated', $category_id );

	return true;
}


/**
 * Set category is_active = 1 (activate).
 *
 * @param int $category_id Category ID.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_activate_category( int $category_id ) {
	global $wpdb;

	$category = gmcq_get_category( $category_id );
	if ( ! $category ) {
		return new WP_Error( 'not_found', 'Category not found.' );
	}

	if ( 1 === (int) $category->is_active ) {
		return true; // Already active
	}

	// Check parent — if parent is inactive, cannot activate
	if ( ! empty( $category->parent_id ) ) {
		$parent = gmcq_get_category( (int) $category->parent_id );
		if ( $parent && 0 === (int) $parent->is_active ) {
			return new WP_Error(
				'parent_inactive',
				'Cannot activate this category because its parent is inactive. Activate the parent first.'
			);
		}
	}

	$updated = $wpdb->update(
		$wpdb->prefix . 'gmcq_categories',
		array( 'is_active' => 1 ),
		array( 'id' => $category_id ),
		array( '%d' ),
		array( '%d' )
	);

	if ( false === $updated ) {
		return new WP_Error( 'db_error', 'Database error: ' . $wpdb->last_error );
	}

	gmcq_clear_dashboard_cache( 'category' );

	do_action( 'gmcq_category_activated', $category_id );

	return true;
}

/**
 * Permanently delete a category and its subcategories.
 *
 * Only allowed if the category and all its children are inactive and 
 * contain no questions.
 *
 * @param int $category_id Category ID.
 * @return bool|\WP_Error True on success, WP_Error on failure.
 */
function gmcq_delete_category( int $category_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'gmcq_categories';
	
	$category = gmcq_get_category( $category_id );
	if ( ! $category ) {
		return new WP_Error( 'not_found', __( 'Category not found.', 'gmcq' ) );
	}
	
	if ( 1 === (int) $category->is_active ) {
		return new WP_Error( 'active_category', __( 'Please deactivate first.', 'gmcq' ) );
	}
	
	if ( (int) $category->question_count > 0 ) {
		return new WP_Error( 'has_questions', __( 'Cannot remove category because it has questions.', 'gmcq' ) );
	}
	
	// Check for subcategories and their state/questions (Fix for cascading delete and Checklist 8.3)
	$subcategories = $wpdb->get_results( $wpdb->prepare(
		"SELECT name, is_active, question_count FROM $table_name WHERE parent_id = %d",
		$category_id
	) );

	if ( ! empty( $subcategories ) ) {
		$active_names = array();
		$has_questions = false;

		foreach ( $subcategories as $sub ) {
			if ( 1 === (int) $sub->is_active ) {
				$active_names[] = $sub->name;
			}
			if ( (int) $sub->question_count > 0 ) {
				$has_questions = true;
			}
		}

		if ( ! empty( $active_names ) ) {
			$names_list = implode( ', ', $active_names );
			return new WP_Error( 'has_active_children', sprintf( __( 'Cannot remove this category because it has active subcategories: %s. Please deactivate or move them first.', 'gmcq' ), $names_list ) );
		}

		if ( $has_questions ) {
			return new WP_Error( 'child_has_questions', __( 'Cannot remove category because one or more subcategories still have questions.', 'gmcq' ) );
		}

		// Safe to delete subcategories first
		$wpdb->delete( $table_name, array( 'parent_id' => $category_id ), array( '%d' ) );
	}

	// Hard delete (only allowed if no questions and no active children)
	$deleted = $wpdb->delete(
		$table_name,
		array( 'id' => $category_id ),
		array( '%d' )
	);
	
	if ( false === $deleted ) {
		return new WP_Error( 'db_error', __( 'Database error: ', 'gmcq' ) . $wpdb->last_error );
	}
	
	gmcq_clear_dashboard_cache( 'category' );
	do_action( 'gmcq_category_deleted', $category_id );
	
	return true;
}

/**
 * Bulk operations on categories.
 *
 * @param string $action  Action: 'delete', 'activate', 'deactivate'.
 * @param array  $ids     Array of category IDs.
 * @return array {
 *     @type int   $success Count of successful operations.
 *     @type array $errors  Array of WP_Error objects for failures.
 * }
 */
function gmcq_bulk_categories( string $action, array $ids ): array {
	$success = 0;
	$errors  = array();

	foreach ( $ids as $id ) {
		$id = (int) $id;
		if ( $id <= 0 ) {
			continue;
		}

		switch ( $action ) {
			case 'delete':
				$result = gmcq_delete_category( $id );
				break;
			case 'activate':
				$result = gmcq_activate_category( $id );
				break;
			case 'deactivate':
				$result = gmcq_deactivate_category( $id );
				break;
			default:
				$result = new WP_Error( 'invalid_action', 'Invalid bulk action: ' . $action );
				break;
		}

		if ( true === $result ) {
			$success++;
		} else {
			$errors[] = $result;
		}
	}

	return array(
		'success' => $success,
		'errors'  => $errors,
	);
}

// ========================================================================
// TREE OPERATIONS
// ========================================================================

/**
 * Get categories as a 2-level tree structure.
 *
 * @param array $args Optional. Arguments passed to gmcq_get_categories().
 * @return array Array of parent categories, each with a 'children' key.
 */
function gmcq_get_category_tree( array $args = array() ): array {
	$args['per_page']    = -1;
	$args['parent_only'] = false;

	$result     = gmcq_get_categories( $args );
	$categories = $result['categories'];

	if ( empty( $categories ) ) {
		return array();
	}

	$tree    = array();
	$indexed = array();

	// Index by ID
	foreach ( $categories as $cat ) {
		$cat_id          = (int) $cat->id;
		$indexed[ $cat_id ] = $cat;
		$indexed[ $cat_id ]->children = array();
	}

	// Build tree: attach children to parents
	foreach ( $indexed as $cat_id => $cat ) {
		if ( ! empty( $cat->parent_id ) && isset( $indexed[ (int) $cat->parent_id ] ) ) {
			$indexed[ (int) $cat->parent_id ]->children[] = $cat;
		} else {
			$tree[] = $cat;
		}
	}

	return $tree;
}

/**
 * Get category tree counts — computed on read with caching.
 *
 * Returns each category's direct count + sum of children's counts (total).
 * Cached for 5 minutes (300 seconds).
 *
 * @return array Associative array keyed by category ID.
 */
function gmcq_get_category_tree_counts(): array {
	$cache_key = 'gmcq_category_tree_counts';
	$counts    = get_transient( $cache_key );

	if ( false !== $counts ) {
		return $counts;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$rows = $wpdb->get_results(
		"SELECT c.id,
		        c.question_count AS direct_count,
		        COALESCE(SUM(child.question_count), 0) AS sub_count,
		        c.question_count + COALESCE(SUM(child.question_count), 0) AS total_count
		 FROM {$p}gmcq_categories c
		 LEFT JOIN {$p}gmcq_categories child ON child.parent_id = c.id AND child.is_active = 1
		 WHERE c.is_active = 1
		 GROUP BY c.id, c.question_count",
		OBJECT_K
	);

	$result = $rows ?: array();
	set_transient( $cache_key, $result, 300 );

	return $result;
}

/**
 * Get ancestors of a category (from child up to root).
 *
 * @param int $category_id Category ID.
 * @return array Array of ancestor category objects, ordered from closest to root.
 */
function gmcq_get_category_ancestors( int $category_id ): array {
	$ancestors = array();
	$current   = $category_id;

	while ( $current > 0 ) {
		$category = gmcq_get_category( $current );
		if ( ! $category || empty( $category->parent_id ) ) {
			break;
		}
		$parent    = gmcq_get_category( (int) $category->parent_id );
		if ( $parent ) {
			$ancestors[] = $parent;
			$current     = (int) $parent->id;
		} else {
			break;
		}
	}

	return $ancestors;
}

/**
 * Get descendants of a category (children and their children).
 *
 * @param int  $category_id     Category ID.
 * @param bool $include_self    Whether to include the category itself. Default false.
 * @return array Array of descendant category objects.
 */
function gmcq_get_category_descendants( int $category_id, bool $include_self = false ): array {
	global $wpdb;
	$p      = $wpdb->prefix;
	$result = array();

	// Get direct children
	$children = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$p}gmcq_categories WHERE parent_id = %d ORDER BY sort_order ASC",
			$category_id
		)
	);

	if ( $include_self ) {
		$self = gmcq_get_category( $category_id );
		if ( $self ) {
			$result[] = $self;
		}
	}

	foreach ( $children as $child ) {
		$result[] = $child;
		// Get grandchildren (max 2 levels)
		$grandchildren = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$p}gmcq_categories WHERE parent_id = %d ORDER BY sort_order ASC",
				(int) $child->id
			)
		);
		foreach ( $grandchildren as $grandchild ) {
			$result[] = $grandchild;
		}
	}

	return $result;
}

/**
 * Check if a category has children.
 *
 * @param int $category_id Category ID.
 * @return bool True if the category has children.
 */
function gmcq_category_has_children( int $category_id ): bool {
	global $wpdb;

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}gmcq_categories WHERE parent_id = %d",
			$category_id
		)
	);

	return $count > 0;
}

/**
 * Get IDs of all children of a category.
 *
 * @param int $category_id Category ID.
 * @return array Array of child category IDs.
 */
function gmcq_get_category_children_ids( int $category_id ): array {
	global $wpdb;

	return $wpdb->get_col(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gmcq_categories WHERE parent_id = %d",
			$category_id
		)
	);
}

/**
 * Get stats for category dashboard card.
 *
 * @return array {
 *     @type int $top_level_active Top-level active categories count.
 *     @type int $child_active     Child active categories count.
 * }
 */
function gmcq_get_category_stats(): array {
	$cache_key = 'gmcq_category_stats';
	$stats     = get_transient( $cache_key );

	if ( false !== $stats ) {
		return $stats;
	}

	global $wpdb;
	$p = $wpdb->prefix;

	$stats = array(
		'top_level_active' => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NULL AND is_active = 1"
		),
		'child_active'     => (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE parent_id IS NOT NULL AND is_active = 1"
		),
	);

	set_transient( $cache_key, $stats, 300 );

	return $stats;
}

// ========================================================================
// VALIDATION FUNCTIONS
// ========================================================================

/**
 * Validate category data before create/update.
 *
 * @param array $data        Category data to validate.
 * @param string $context    'create' or 'update'.
 * @param int    $category_id Optional. Category ID for update context.
 * @return true|\WP_Error True if valid, WP_Error with message if invalid.
 */
function gmcq_validate_category_data( array $data, string $context = 'create', int $category_id = 0 ) {
	global $wpdb;
	$p = $wpdb->prefix;

	// Name validation
	if ( isset( $data['name'] ) && '' === trim( $data['name'] ) ) {
		return new WP_Error( 'name_empty', 'Category name cannot be empty.' );
	}

	$name_len = isset( $data['name'] ) ? ( function_exists( 'mb_strlen' ) ? mb_strlen( $data['name'] ) : strlen( $data['name'] ) ) : 0;
	if ( $name_len > 255 ) {
		return new WP_Error( 'name_too_long', 'Category name is too long (maximum 255 characters).' );
	}

	// Slug validation — note: uniqueness is enforced by gmcq_make_category_slug_unique(),
	// so we only validate format here, not uniqueness
	if ( isset( $data['slug'] ) && '' !== trim( $data['slug'] ) ) {
		$slug = sanitize_title( $data['slug'] );
		if ( empty( $slug ) ) {
			return new WP_Error( 'slug_invalid', 'Category slug is invalid.' );
		}
	}

	// Parent validation
	if ( isset( $data['parent_id'] ) && ! empty( $data['parent_id'] ) ) {
		$parent_id = (int) $data['parent_id'];

		// Cannot be own parent
		if ( $parent_id === $category_id ) {
			return new WP_Error( 'self_parent', 'A category cannot be its own parent.' );
		}

		// Parent must exist
		$parent_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE id = %d",
				$parent_id
			)
		);
		if ( ! $parent_exists ) {
			return new WP_Error( 'parent_not_found', 'Parent category does not exist.' );
		}

		// Prevent circular reference: parent cannot be a descendant of this category
		if ( $category_id > 0 ) {
			$descendants = gmcq_get_category_descendants( $category_id, false );
			$desc_ids    = wp_list_pluck( $descendants, 'id' );
			if ( in_array( $parent_id, $desc_ids, true ) ) {
				return new WP_Error( 'circular_reference', 'Cannot set a child as parent (circular reference).' );
			}
		}

		// Max 2 levels: parent must be top-level (no parent)
		$parent_is_top = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT parent_id FROM {$p}gmcq_categories WHERE id = %d",
				$parent_id
			)
		);
		if ( ! empty( $parent_is_top ) ) {
			return new WP_Error( 'max_levels', 'Categories support only 2 levels. Only top-level categories can be parents.' );
		}
	}

	return true;
}

/**
 * Generate a unique slug for a category.
 *
 * @param string $slug        Desired slug.
 * @param int    $category_id Category ID to exclude from check.
 * @return string Unique slug.
 */
function gmcq_make_category_slug_unique( string $slug, int $category_id ): string {
	global $wpdb;
	$p        = $wpdb->prefix;
	$original = $slug;
	$counter  = 1;

	while ( $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$p}gmcq_categories WHERE slug = %s AND id != %d",
			$slug,
			$category_id
		)
	) > 0 ) {
		$slug = $original . '-' . $counter;
		$counter++;
	}

	return $slug;
}

// ========================================================================
// AJAX HANDLERS (Phase 1)
// ========================================================================

/**
 * Register AJAX handlers for categories.
 */
function gmcq_register_category_ajax_handlers(): void {
	add_action( 'wp_ajax_gmcq_add_category', 'gmcq_ajax_add_category' );
	add_action( 'wp_ajax_gmcq_update_category', 'gmcq_ajax_update_category' );
	add_action( 'wp_ajax_gmcq_deactivate_category', 'gmcq_ajax_deactivate_category' );
	add_action( 'wp_ajax_gmcq_activate_category', 'gmcq_ajax_activate_category' );
	add_action( 'wp_ajax_gmcq_bulk_categories', 'gmcq_ajax_bulk_categories' );
	add_action( 'wp_ajax_gmcq_search_categories', 'gmcq_ajax_search_categories' );
	add_action( 'wp_ajax_gmcq_get_subcategories', 'gmcq_ajax_get_subcategories' );
	add_action( 'wp_ajax_gmcq_delete_category', 'gmcq_ajax_delete_category' );
}

/**
 * AJAX handler: Add category.
 */
function gmcq_ajax_add_category(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		if ( ob_get_length() ) { ob_clean(); }
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$main_category = isset( $_POST['main_category'] ) ? sanitize_text_field( $_POST['main_category'] ) : '';
	$sub_category  = isset( $_POST['sub_category'] ) ? sanitize_text_field( $_POST['sub_category'] ) : '';
	$description   = isset( $_POST['description'] ) ? sanitize_textarea_field( $_POST['description'] ) : '';

	if ( empty( $main_category ) ) {
		wp_send_json_error( array( 'message' => __( 'Main category is required.', 'gmcq' ) ) );
	}

	$main_id = 0;
	if ( is_numeric( $main_category ) && (int) $main_category > 0 ) {
		$main_id = (int) $main_category;
	} else {
		global $wpdb;
		// Check if a parent category with this name already exists to avoid duplicates
		$existing_main = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}gmcq_categories WHERE name = %s AND parent_id IS NULL LIMIT 1",
			$main_category
		) );

		if ( $existing_main ) {
			$main_id = (int) $existing_main;
		} else {
			$main_id = gmcq_create_category( array(
				'name'        => $main_category,
				'description' => $description,
			) );
			if ( is_wp_error( $main_id ) ) {
				wp_send_json_error( array( 'message' => $main_id->get_error_message() ) );
			}
		}
	}

	$sub_id = 0;
	if ( ! empty( $sub_category ) ) {
		if ( ! $main_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parent category context.', 'gmcq' ) ) );
		}

		if ( is_numeric( $sub_category ) && (int) $sub_category > 0 ) {
			$sub_id = (int) $sub_category;
			// Ensure the existing sub-category is linked to the chosen parent
			$update_res = gmcq_update_category( $sub_id, array( 'parent_id' => $main_id ) );
			if ( is_wp_error( $update_res ) ) {
				wp_send_json_error( array( 'message' => $update_res->get_error_message() ) );
			}
		} else {
			global $wpdb;
			// Check if this sub-category name already exists under the parent or as top-level
			$existing_sub = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gmcq_categories WHERE name = %s AND (parent_id = %d OR parent_id IS NULL) LIMIT 1",
				$sub_category,
				$main_id
			) );

			if ( $existing_sub ) {
				gmcq_update_category( (int) $existing_sub, array( 'parent_id' => $main_id ) );
			} else {
				$sub_id = gmcq_create_category( array(
					'name'        => $sub_category,
					'parent_id'   => $main_id,
					'description' => $description,
				) );
				if ( is_wp_error( $sub_id ) ) {
					wp_send_json_error( array( 'message' => $sub_id->get_error_message() ) );
				}
			}
		}
	}

	if ( ob_get_length() ) {
		ob_clean(); // Strip any PHP warnings/notices so JSON doesn't get corrupted
	}

	wp_send_json_success( array( 'message' => __( 'Category added successfully.', 'gmcq' ) ) );
}

/**
 * AJAX handler: Update category.
 */
function gmcq_ajax_update_category(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$category_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $category_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'Invalid category ID.' ) );
	}

	$result = gmcq_update_category( $category_id, $_POST );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => 'Category updated successfully.' ) );
}

/**
 * AJAX handler: Deactivate category.
 */
function gmcq_ajax_deactivate_category(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$category_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $category_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'Invalid category ID.' ) );
	}

	$result = gmcq_deactivate_category( $category_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => 'Category deactivated successfully.' ) );
}

/**
 * AJAX handler: Activate category.
 */
function gmcq_ajax_activate_category(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$category_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $category_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'Invalid category ID.' ) );
	}

	$result = gmcq_activate_category( $category_id );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => 'Category activated successfully.' ) );
}

/**
 * AJAX handler: Bulk categories.
 */
function gmcq_ajax_bulk_categories(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
	$ids    = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();

	if ( empty( $action ) || empty( $ids ) ) {
		wp_send_json_error( array( 'message' => 'Invalid request.' ) );
	}

	$result = gmcq_bulk_categories( $action, $ids );

	wp_send_json_success( $result );
}

/**
 * AJAX handler: Search categories.
 */
function gmcq_ajax_search_categories(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$search = isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '';
	$filter = isset( $_GET['filter'] ) ? sanitize_key( $_GET['filter'] ) : 'all';

	$args = array(
		'search' => $search,
		'filter' => $filter,
	);

	$result = gmcq_get_categories( $args );

	wp_send_json_success( $result );
}

// ========================================================================
// HOOKS & FILTERS
// ========================================================================

/**
 * Register hooks for category question count maintenance.
 */
function gmcq_register_category_hooks(): void {
	// Update question_count when questions are created, deleted, or reassigned
	add_action( 'gmcq_before_save_question', 'gmcq_handle_category_question_change' );
	add_action( 'gmcq_question_deleted', 'gmcq_handle_category_question_change' );
	add_action( 'gmcq_question_restored', 'gmcq_handle_category_question_change' );
}

/**
 * Handle category question count change.
 * Recalculates the question_count for a category.
 *
 * @param array|int $data Question data array or question ID.
 */
function gmcq_handle_category_question_change( $data ): void {
	global $wpdb;
	$p = $wpdb->prefix;
	$category_id = null;

	if ( is_array( $data ) && isset( $data['category_id'] ) ) {
		$category_id = (int) $data['category_id'];
	} elseif ( is_numeric( $data ) ) {
		$question = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT category_id FROM {$p}gmcq_questions WHERE id = %d",
				(int) $data
			)
		);
		if ( $question ) {
			$category_id = (int) $question->category_id;
		}
	}

	if ( $category_id && $category_id > 0 ) {
		gmcq_recalculate_category_count( $category_id );
	}
}

/**
 * Recalculate question_count for a specific category.
 *
 * @param int $category_id Category ID.
 * @return void
 */
function gmcq_recalculate_category_count( int $category_id ): void {
	global $wpdb;
	$p = $wpdb->prefix;

	$count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$p}gmcq_questions WHERE category_id = %d AND is_active = 1",
			$category_id
		)
	);

	$wpdb->update(
		$p . 'gmcq_categories',
		array( 'question_count' => $count ),
		array( 'id' => $category_id ),
		array( '%d' ),
		array( '%d' )
	);

	gmcq_clear_dashboard_cache( 'category' );
}

/**
 * Daily cron: Recalculate all category question counts.
 */
function gmcq_recalculate_category_counts(): void {
	global $wpdb;
	$p = $wpdb->prefix;

	$wpdb->query(
		"UPDATE {$p}gmcq_categories c
		 JOIN (
		     SELECT category_id, COUNT(*) AS cnt
		     FROM {$p}gmcq_questions
		     WHERE is_active = 1 AND category_id IS NOT NULL
		     GROUP BY category_id
		 ) q ON q.category_id = c.id
		 SET c.question_count = q.cnt"
	);

	gmcq_clear_dashboard_cache( 'category' );
}

/**
 * AJAX handler: Get subcategories.
 */
/**
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

	wp_send_json_success( array( 'message' => __( 'Category removed successfully.', 'gmcq' ) ) );
}

function gmcq_ajax_get_subcategories(): void {
	check_ajax_referer( 'gmcq_category_nonce' );

	if ( ! current_user_can( 'manage_gmcq' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gmcq' ) ) );
	}

	$parent_id = isset( $_GET['parent_id'] ) ? (int) $_GET['parent_id'] : 0;
	if ( $parent_id <= 0 ) {
		wp_send_json_error( array( 'message' => __( 'Invalid parent ID.', 'gmcq' ) ) );
	}

	$result = gmcq_get_categories( array(
		'parent_id' => $parent_id,
		'filter'    => 'active',
	) );

	wp_send_json_success( array(
		'subcategories' => $result['categories']
	) );
}

// Automatically initialize category hooks and AJAX endpoints when this file is loaded
gmcq_register_category_ajax_handlers();
gmcq_register_category_hooks();
