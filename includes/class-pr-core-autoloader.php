<?php
declare(strict_types=1);

/**
 * SPL autoloader for PR_Core_ prefixed classes.
 *
 * What: Resolves PR_Core_ class names to file paths under includes/.
 * Who calls it: peptide-repo-core.php bootstrap.
 * Dependencies: None.
 *
 * Naming convention: PR_Core_Foo_Bar => class-pr-core-foo-bar.php.
 * Scans includes/ and all immediate subdirectories.
 *
 * @see peptide-repo-core.php — Registers this autoloader at boot.
 */
class PR_Core_Autoloader {

	/** @var string[] Directories to scan for class files. */
	private static array $dirs = [];

	/**
	 * Register the autoloader with SPL.
	 *
	 * Side effects: calls spl_autoload_register().
	 *
	 * @return void
	 */
	public static function register(): void {
		$base = PR_CORE_PLUGIN_DIR . 'includes/';

		self::$dirs[] = $base;

		// Add all immediate subdirectories.
		foreach ( glob( $base . '*', GLOB_ONLYDIR ) as $dir ) {
			self::$dirs[] = trailingslashit( $dir );
		}

		spl_autoload_register( [ __CLASS__, 'load' ] );
	}

	/**
	 * Autoload callback. Only handles PR_Core_ prefixed classes.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public static function load( string $class ): void {
		if ( 0 !== strpos( $class, 'PR_Core_' ) ) {
			return;
		}

		// PR_Core_Foo_Bar => foo-bar => class-pr-core-foo-bar.php.
		$suffix   = substr( $class, 8 ); // Strip 'PR_Core_'.
		$filename = 'class-pr-core-' . str_replace( '_', '-', strtolower( $suffix ) ) . '.php';

		foreach ( self::$dirs as $dir ) {
			$path = $dir . $filename;
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
}
