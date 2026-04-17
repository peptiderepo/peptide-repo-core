<?php
declare(strict_types=1);

/**
 * Schema migration runner.
 *
 * What: Compares current schema version against target, runs pending migrations.
 * Who calls it: PR_Core::init() on every plugins_loaded, PR_Core_Activator on activation.
 * Dependencies: WordPress $wpdb, dbDelta().
 *
 * Migrations are numbered sequentially (0001, 0002, ...). Each migration class
 * must implement up() and optionally down(). All migrations are idempotent.
 *
 * @see peptide-repo-core.php — Defines PR_CORE_TARGET_SCHEMA_VERSION.
 * @see CONVENTIONS.md        — "How To: Add a New Migration".
 */
class PR_Core_Migration_Runner {

	/** @var string Option key storing current schema version. */
	private const VERSION_OPTION = 'pr_core_schema_version';

	/**
	 * Ordered list of migration classes.
	 * Index 0 = migration 1 (bumps schema to version 1).
	 *
	 * @var string[]
	 */
	private const MIGRATIONS = [
		'PR_Core_Migration_0001_Dosing_Rows',
		'PR_Core_Migration_0002_Legal_Cells',
		'PR_Core_Migration_0003_Candidate_Queue',
	];

	/**
	 * Run all pending migrations up to PR_CORE_TARGET_SCHEMA_VERSION.
	 *
	 * Side effects: creates/alters database tables, updates schema version option.
	 *
	 * @return void
	 */
	public function run_pending(): void {
		$current = (int) get_option( self::VERSION_OPTION, 0 );
		$target  = PR_CORE_TARGET_SCHEMA_VERSION;

		if ( $current >= $target ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		for ( $i = $current; $i < $target; $i++ ) {
			if ( ! isset( self::MIGRATIONS[ $i ] ) ) {
				break;
			}

			$class = self::MIGRATIONS[ $i ];
			if ( ! class_exists( $class ) ) {
				// Migration file not autoloaded — load explicitly.
				$file = $this->class_to_file( $class );
				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}

			if ( class_exists( $class ) ) {
				$migration = new $class();
				$migration->up();
			}

			update_option( self::VERSION_OPTION, $i + 1 );
		}
	}

	/**
	 * Get current schema version.
	 *
	 * @return int
	 */
	public function get_current_version(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Convert migration class name to file path.
	 *
	 * PR_Core_Migration_0001_Dosing_Rows => migrations/class-pr-core-migration-0001-dosing-rows.php
	 *
	 * @param string $class Fully-qualified class name.
	 * @return string Absolute file path.
	 */
	private function class_to_file( string $class ): string {
		$suffix = substr( $class, 8 ); // Strip 'PR_Core_'.
		$filename = 'class-pr-core-' . str_replace( '_', '-', strtolower( $suffix ) ) . '.php';

		return PR_CORE_PLUGIN_DIR . 'includes/migrations/' . $filename;
	}
}
