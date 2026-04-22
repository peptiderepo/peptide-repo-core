<?php
declare(strict_types=1);

/**
 * Plugin activation handler.
 *
 * What: Runs migrations, adds capabilities, flushes rewrite rules.
 *       Also detects version changes on subsequent init cycles and re-flushes
 *       rewrite rules once so in-place plugin updates (without a deactivate +
 *       reactivate cycle) still pick up CPT/taxonomy slug changes.
 * Who calls it: register_activation_hook() in peptide-repo-core.php; and
 *               PR_Core::init() registers maybe_flush_on_version_change on init:999.
 * Dependencies: PR_Core_Migration_Runner, PR_Core_Peptide_CPT.
 *
 * @see peptide-repo-core.php — Registers this class on activation.
 * @see includes/class-pr-core.php — Wires the init:999 version-change handler.
 */
class PR_Core_Activator {

	/** @var string Option key storing the last activated/seen plugin version. */
	private const VERSION_OPTION = 'pr_core_version';

	/**
	 * Run on plugin activation.
	 *
	 * Side effects: database table creation, capability grants, rewrite flush,
	 *               pr_core_version option write.
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
		flush_rewrite_rules( false );

		// Record the activated version so the init:999 drift handler can
		// detect future in-place updates and re-flush without a manual
		// deactivate/reactivate cycle.
		update_option( self::VERSION_OPTION, PR_CORE_VERSION, false );
	}

	/**
	 * Detect in-place plugin version changes and flush rewrite rules once.
	 *
	 * Wired to init:999 so all CPTs + taxonomies (ours and anyone else's)
	 * are registered before the flush. When the option is absent (first
	 * install that bypassed the activation hook — e.g., manual SCP deploy)
	 * or is older than the current constant, we flush once and update.
	 *
	 * Side effects: may call flush_rewrite_rules( false ); updates option.
	 *
	 * @return void
	 */
	public static function maybe_flush_on_version_change(): void {
		if ( ! defined( 'PR_CORE_VERSION' ) ) {
			return;
		}

		$recorded = get_option( self::VERSION_OPTION, '' );

		if ( PR_CORE_VERSION === $recorded ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::VERSION_OPTION, PR_CORE_VERSION, false );
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
