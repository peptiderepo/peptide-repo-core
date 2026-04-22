<?php
/**
 * Minimal test bootstrap — stubs the WordPress functions used by the classes
 * under test so they can be exercised in plain PHP without a WP test install.
 *
 * This is intentionally NOT a full WP_UnitTestCase harness. It exists so the
 * targeted unit assertions in tests/unit/ can verify guard behavior, constant
 * shape, and args payload shape on CI (PHP lint job) without introducing a
 * PHPUnit + wp-env dependency. A proper integration test harness is a
 * follow-up (see CHANGELOG 0.2.0 notes).
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'PR_CORE_VERSION' ) ) {
	define( 'PR_CORE_VERSION', '0.2.0' );
}

/**
 * Global harness state. Tests reset this between cases.
 *
 * @var array<string, mixed>
 */
$GLOBALS['pr_core_test_state'] = [
	'existing_post_types'  => [],
	'existing_taxonomies'  => [],
	'registered_post_types' => [],
	'registered_taxonomies' => [],
	'registered_meta'      => [],
	'added_actions'        => [],
];

function pr_core_test_reset(): void {
	$GLOBALS['pr_core_test_state'] = [
		'existing_post_types'  => [],
		'existing_taxonomies'  => [],
		'registered_post_types' => [],
		'registered_taxonomies' => [],
		'registered_meta'      => [],
		'added_actions'        => [],
	];
}

function post_type_exists( string $post_type ): bool {
	return in_array( $post_type, $GLOBALS['pr_core_test_state']['existing_post_types'], true );
}

function taxonomy_exists( string $taxonomy ): bool {
	return in_array( $taxonomy, $GLOBALS['pr_core_test_state']['existing_taxonomies'], true );
}

function register_post_type( string $post_type, array $args = [] ) {
	$GLOBALS['pr_core_test_state']['registered_post_types'][ $post_type ] = $args;
	$GLOBALS['pr_core_test_state']['existing_post_types'][]                = $post_type;
	return (object) [ 'name' => $post_type, 'args' => $args ];
}

function register_taxonomy( string $taxonomy, $object_type, array $args = [] ): void {
	$GLOBALS['pr_core_test_state']['registered_taxonomies'][ $taxonomy ] = [
		'object_type' => $object_type,
		'args'        => $args,
	];
	$GLOBALS['pr_core_test_state']['existing_taxonomies'][] = $taxonomy;
}

function register_post_meta( string $post_type, string $key, array $args = [] ): bool {
	$GLOBALS['pr_core_test_state']['registered_meta'][ $post_type ][ $key ] = $args;
	return true;
}

function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	$GLOBALS['pr_core_test_state']['added_actions'][] = [
		'hook'     => $hook,
		'callback' => $callback,
		'priority' => $priority,
	];
	return true;
}

function __( string $text, string $domain = 'default' ): string {
	return $text;
}

function current_user_can( string $capability ): bool {
	return true;
}

function sanitize_text_field( $value ): string {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

function wp_json_encode( $data ): string {
	return json_encode( $data ) ?: '[]';
}

function absint( $value ): int {
	return abs( (int) $value );
}


/* ── Test assertion helpers ─────────────────────────────────────────── */

$GLOBALS['pr_core_test_report'] = [ 'pass' => 0, 'fail' => 0, 'failures' => [] ];

function pr_assert( bool $condition, string $label ): void {
	if ( $condition ) {
		$GLOBALS['pr_core_test_report']['pass']++;
		echo "  PASS: {$label}\n";
	} else {
		$GLOBALS['pr_core_test_report']['fail']++;
		$GLOBALS['pr_core_test_report']['failures'][] = $label;
		echo "  FAIL: {$label}\n";
	}
}

function pr_assert_equals( $expected, $actual, string $label ): void {
	$pass = ( $expected === $actual );
	pr_assert( $pass, $label . ( $pass ? '' : ' — expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ) );
}

function pr_test_summary(): int {
	$r = $GLOBALS['pr_core_test_report'];
	echo "\n---\n";
	echo "Totals: {$r['pass']} passed, {$r['fail']} failed\n";
	if ( $r['fail'] > 0 ) {
		echo "Failures:\n";
		foreach ( $r['failures'] as $f ) {
			echo "  - {$f}\n";
		}
		return 1;
	}
	return 0;
}

/* ── Load the plugin's autoloader + required classes ─────────────────── */

require_once __DIR__ . '/../includes/class-pr-core-autoloader.php';
PR_Core_Autoloader::register();
require_once __DIR__ . '/../includes/cpt/class-pr-core-peptide-cpt.php';
