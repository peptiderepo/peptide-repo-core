<?php
/**
 * Peptide Repo Core — Full data teardown on uninstall.
 *
 * Removes ALL plugin data: custom tables, CPT posts + meta, taxonomy terms,
 * options, and capabilities. No orphaned data left behind.
 *
 * @see ARCHITECTURE.md — Section 2.9 Uninstall specification.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/* ── 1. Drop custom tables ────────────────────────────────────────────── */

$tables = [
	$wpdb->prefix . 'pr_dosing_rows',
	$wpdb->prefix . 'pr_legal_cells',
	$wpdb->prefix . 'pr_ai_candidate_queue',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

/* ── 2. Delete all pr_peptide posts (cascade deletes meta) ────────────── */

$post_ids = $wpdb->get_col(
	"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pr_peptide'"
);

foreach ( $post_ids as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

/* ── 3. Delete taxonomy terms ─────────────────────────────────────────── */

$taxonomies = [ 'pr_peptide_category', 'pr_peptide_family' ];

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms( [
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'fields'     => 'ids',
	] );

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, $taxonomy );
		}
	}
}

/* ── 4. Delete all pr_core_ prefixed options ──────────────────────────── */

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pr\_core\_%'"
);

/* ── 5. Remove manage_peptide_content capability from all roles ────────── */

$editable_roles = wp_roles()->roles;

foreach ( array_keys( $editable_roles ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role && $role->has_cap( 'manage_peptide_content' ) ) {
		$role->remove_cap( 'manage_peptide_content' );
	}
}
