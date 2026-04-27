<?php
/**
 * Plugin Name: Peptide Repo Core
 * Plugin URI:  https://peptiderepo.com
 * Description: Canonical peptide schema — shared data layer for the peptiderepo.com ecosystem. Provides the peptide CPT, dosing rows, legal status cells, AI candidate queue, disclaimer component, and JSON-LD output.
 * Version:     0.4.0
 * Author:      peptiderepo
 * Author URI:  https://peptiderepo.com
 * License:     GPL-2.0-or-later
 * Text Domain: peptide-repo-core
 * Requires PHP: 8.1
 *
 * @see ARCHITECTURE.md — Full data flow and file tree.
 * @see CONVENTIONS.md  — Naming patterns and extension guides.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Constants ────────────────────────────────────────────────────────── */

define( 'PR_CORE_VERSION', '0.4.0' );
define( 'PR_CORE_PLUGIN_FILE', __FILE__ );
define( 'PR_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PR_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PR_CORE_TARGET_SCHEMA_VERSION', 3 );

/* ── Autoloader ───────────────────────────────────────────────────────── */

require_once PR_CORE_PLUGIN_DIR . 'includes/class-pr-core-autoloader.php';
PR_Core_Autoloader::register();

// The main orchestrator class is loaded explicitly because its name (PR_Core)
// equals the autoloader prefix exactly — stripping "PR_Core_" yields an empty
// suffix, producing "class-pr-core-.php" instead of "class-pr-core.php".
require_once PR_CORE_PLUGIN_DIR . 'includes/class-pr-core.php';

/* ── Activation / Deactivation ────────────────────────────────────────── */

register_activation_hook( __FILE__, [ 'PR_Core_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'PR_Core_Deactivator', 'deactivate' ] );

/* ── Boot ──────────────────────────────────────────────────────────────── */

add_action( 'plugins_loaded', static function (): void {
	$plugin = new PR_Core();
	$plugin->init();
} );
