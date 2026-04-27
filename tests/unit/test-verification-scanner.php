<?php
/**
 * Unit tests for PR_Core_Verification_Scanner::compute_status().
 *
 * Run: php tests/unit/test-verification-scanner.php
 * Exit code 0 = all pass, 1 = any failure.
 *
 * What's covered:
 *   - Empty last_verified → overdue.
 *   - Days < 90% of threshold → current.
 *   - Days >= 90% and < 100% of threshold → due.
 *   - Days >= threshold → overdue.
 *   - Boundary: exactly at 90% → due (not current).
 *   - high velocity applies 60-day threshold from option.
 *   - low velocity applies 365-day constant (not option).
 *   - medium/default applies 180-day threshold from option.
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// ── Additional stubs needed by the scanner ──────────────────────────────

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

/** @var array<string, mixed> $GLOBALS['pr_core_options'] Mocked option store. */
$GLOBALS['pr_core_options'] = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $GLOBALS['pr_core_options'][ $key ] ?? $default;
	}
}

// ── Load the scanner class ──────────────────────────────────────────────

require_once __DIR__ . '/../../includes/scanner/class-pr-core-verification-scanner.php';

// ── Helper: build a date N days ago ────────────────────────────────────

function days_ago( int $days ): string {
	return gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
}

echo "== PR_Core_Verification_Scanner::compute_status() unit tests ==\n\n";

// ── Empty last_verified ─────────────────────────────────────────────────

echo "Empty last_verified:\n";
pr_assert_equals(
	'overdue',
	PR_Core_Verification_Scanner::compute_status( '', 'medium' ),
	'empty string → overdue'
);

// ── Medium velocity (default 180-day threshold) ─────────────────────────

echo "\nMedium velocity (threshold=180, option default):\n";
$GLOBALS['pr_core_options'] = [];

// 160 days ago → 160 < 162 (90% of 180) → current.
pr_assert_equals( 'current', PR_Core_Verification_Scanner::compute_status( days_ago( 160 ), 'medium' ), '160 days → current' );

// 163 days ago → 163 >= 162 and < 180 → due.
pr_assert_equals( 'due', PR_Core_Verification_Scanner::compute_status( days_ago( 163 ), 'medium' ), '163 days → due' );

// 180 days ago → 180 >= 180 → overdue.
pr_assert_equals( 'overdue', PR_Core_Verification_Scanner::compute_status( days_ago( 180 ), 'medium' ), '180 days → overdue' );

// 200 days ago → overdue.
pr_assert_equals( 'overdue', PR_Core_Verification_Scanner::compute_status( days_ago( 200 ), 'medium' ), '200 days → overdue' );

// ── 90% boundary exactness ──────────────────────────────────────────────

echo "\n90% boundary (threshold=100):\n";
$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 100 ];

// 89 days → 89 < 90 (90% of 100) → current.
pr_assert_equals( 'current', PR_Core_Verification_Scanner::compute_status( days_ago( 89 ), 'medium' ), '89 days (threshold 100) → current' );

// 90 days → 90 >= 90 and < 100 → due.
pr_assert_equals( 'due', PR_Core_Verification_Scanner::compute_status( days_ago( 90 ), 'medium' ), '90 days (threshold 100) → due (at boundary)' );

// 100 days → 100 >= 100 → overdue.
pr_assert_equals( 'overdue', PR_Core_Verification_Scanner::compute_status( days_ago( 100 ), 'medium' ), '100 days (threshold 100) → overdue' );

// ── High velocity (60-day threshold from option) ────────────────────────

echo "\nHigh velocity (threshold=60 from option):\n";
$GLOBALS['pr_core_options'] = [ 'pr_core_high_velocity_threshold' => 60 ];

// 50 days → 50 < 54 (90% of 60) → current.
pr_assert_equals( 'current', PR_Core_Verification_Scanner::compute_status( days_ago( 50 ), 'high' ), 'high: 50 days → current' );

// 55 days → 55 >= 54, < 60 → due.
pr_assert_equals( 'due', PR_Core_Verification_Scanner::compute_status( days_ago( 55 ), 'high' ), 'high: 55 days → due' );

// 61 days → overdue.
pr_assert_equals( 'overdue', PR_Core_Verification_Scanner::compute_status( days_ago( 61 ), 'high' ), 'high: 61 days → overdue' );

// ── Low velocity (365-day constant — ignores option) ────────────────────

echo "\nLow velocity (threshold=365, constant, ignores option):\n";
$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 999 ]; // Should be ignored for low.

// 300 days → 300 < 328.5 (90% of 365) → current.
pr_assert_equals( 'current', PR_Core_Verification_Scanner::compute_status( days_ago( 300 ), 'low' ), 'low: 300 days → current' );

// 340 days → 340 >= 328.5, < 365 → due.
pr_assert_equals( 'due', PR_Core_Verification_Scanner::compute_status( days_ago( 340 ), 'low' ), 'low: 340 days → due' );

// 366 days → overdue.
pr_assert_equals( 'overdue', PR_Core_Verification_Scanner::compute_status( days_ago( 366 ), 'low' ), 'low: 366 days → overdue' );

// ── Unknown velocity falls through to medium/default ────────────────────

echo "\nUnknown velocity (falls to default/medium):\n";
$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 180 ];
pr_assert_equals( 'current', PR_Core_Verification_Scanner::compute_status( days_ago( 100 ), 'unknown' ), 'unknown velocity: 100 days → current (uses default 180)' );

// ── Summary ─────────────────────────────────────────────────────────────

exit( pr_test_summary() );
