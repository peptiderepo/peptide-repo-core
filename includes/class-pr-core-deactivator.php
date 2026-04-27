<?php
declare(strict_types=1);

/**
 * Plugin deactivation handler.
 *
 * What: Flushes rewrite rules on deactivation (data preserved).
 * Who calls it: register_deactivation_hook() in peptide-repo-core.php.
 * Dependencies: None.
 *
 * @see peptide-repo-core.php — Registers this class on deactivation.
 * @see uninstall.php         — Full data teardown happens on uninstall, not deactivation.
 */
class PR_Core_Deactivator {

	/**
	 * Run on plugin deactivation.
	 *
	 * Side effects: flushes rewrite rules.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clear verification scan cron.
		wp_clear_scheduled_hook( 'pr_core_verification_scan' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
