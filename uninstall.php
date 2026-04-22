<?php
/**
 * Peptide Repo Core — Selective data teardown on uninstall.
 *
 * Removes plugin-owned data only: custom tables, PR Core-authored peptide
 * posts, pr_core_ options, and the manage_peptide_content capability.
 * Shared data (the 89 canonical peptide posts authored by PSA, and the
 * peptide_category taxonomy terms) is preserved.
 *
 * @see ARCHITECTURE.md — §2.9 Uninstall specification.
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

/* ── 2. Delete only peptide posts authored through PR Core's UI ───────── */

/*
 * As of v0.2.0, PR Core shares the `peptide` CPT with Peptide Search AI.
 * PSA authored the 89 canonical peptide posts on peptiderepo.com; those
 * predate PR Core and do NOT carry the `_pr_core_authored` flag. A blanket
 * DELETE here would destroy site-owned content, not plugin-owned content.
 *
 * This loop only deletes posts that PR Core itself created (flagged with
 * post-meta `_pr_core_authored = '1'`). If no post carries the flag, the
 * loop no-ops and shared content is preserved.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$post_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} pm
		   ON p.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s
		 WHERE p.post_type = %s",
		'_pr_core_authored',
		'1',
		'peptide'
	)
);

foreach ( $post_ids as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}

/* ── 3. Preserve taxonomy terms (shared ownership post-v0.2.0) ────────── */

/*
 * `peptide_category` is shared between PR Core and PSA. Term metadata
 * (descriptions, parent relationships, counts) is data the site owns —
 * removing it on PR Core uninstall would strip categorization from the
 * peptide posts that remain. So we intentionally do NOT enumerate or
 * delete terms here. `pr_peptide_family` is gone in v0.2.0 so no cleanup
 * is needed for it either.
 */

/* ── 4. Delete all pr_core_ prefixed options ──────────────────────────── */

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'pr\_core\_%'"
);

/* ── 5. Remove manage_peptide_content capability from all roles ───────── */

$editable_roles = wp_roles()->roles;

foreach ( array_keys( $editable_roles ) as $role_name ) {
	$role = get_role( $role_name );
	if ( $role && $role->has_cap( 'manage_peptide_content' ) ) {
		$role->remove_cap( 'manage_peptide_content' );
	}
}
