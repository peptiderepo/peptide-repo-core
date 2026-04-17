<?php
declare(strict_types=1);

/**
 * Plugin activation handler.
 *
 * What: Runs migrations, adds capabilities, flushes rewrite rules.
 * Who calls it: register_activation_hook() in peptide-repo-core.php.
 * Dependencies: PR_Core_Migration_Runner.
 *
 * @see peptide-repo-core.php — Registers this class on activation.
 */
class PR_Core_Activator {

	/**
	 * Run on plugin activation.
	 *
	 * Side effects: database table creation, capability grants, rewrite flush.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Run all pending migrations (creates tables).
		$runner = new PR_Core_Migration_Runner();
		$runner->run_pending();

		// Register CPT so rewrite rules exist before flush.
		PR_Core_Peptide_CPT::register_peptide_post_type();
		PR_Core_Peptide_CPT::register_taxonomies();

		// Grant manage_peptide_content to administrators and editors.
		self::add_capabilities();

		// Flush rewrite rules for the new CPT.
		flush_rewrite_rules();
	}

	/**
	 * Grant the manage_peptide_content capability to appropriate roles.
	 *
	 * Side effects: modifies role capabilities in database.
	 *
	 * @return void
	 */
	private static function add_capabilities(): void {
		$cap   = 'manage_peptide_content';
		$roles = [ 'administrator', 'editor' ];

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( $cap ) ) {
				$role->add_cap( $cap );
			}
		}
	}
}
