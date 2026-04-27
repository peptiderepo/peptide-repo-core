<?php
/**
 * Unit tests for PR_Core_Settings verification methods.
 *
 * Run: php tests/unit/test-verification-settings.php
 * Exit code 0 = all pass, 1 = any failure.
 *
 * What's covered:
 *   - sanitize_cadence() accepts valid values, rejects invalid → 'weekly'.
 *   - sanitize_emails() filters invalid addresses, preserves valid ones.
 *   - reschedule_cron() no-ops when old === new value.
 *   - reschedule_cron() clears + reschedules when value changes.
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// ── Additional stubs needed by the settings class ───────────────────────

$GLOBALS['pr_core_options']    = [];
$GLOBALS['pr_core_cron_calls'] = [];

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $GLOBALS['pr_core_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value ): bool {
		$GLOBALS['pr_core_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $group, string $option, array $args = [] ): void {}
}

if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, $cb, string $page ): void {}
}

if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, $cb, string $page, string $section = '' ): void {}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ): bool {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook ): void {
		$GLOBALS['pr_core_cron_calls'][] = [ 'action' => 'clear', 'hook' => $hook ];
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook ): void {
		$GLOBALS['pr_core_cron_calls'][] = [ 'action' => 'schedule', 'hook' => $hook, 'recurrence' => $recurrence ];
	}
}

// ── Load the settings class ─────────────────────────────────────────────

require_once __DIR__ . '/../../includes/cpt/class-pr-core-peptide-cpt.php';
require_once __DIR__ . '/../../includes/admin/class-pr-core-settings.php';

echo "== PR_Core_Settings verification methods unit tests ==\n\n";

// ── sanitize_cadence() ──────────────────────────────────────────────────

echo "sanitize_cadence():\n";
pr_assert_equals( 'daily',   PR_Core_Settings::sanitize_cadence( 'daily' ),   'daily → daily' );
pr_assert_equals( 'weekly',  PR_Core_Settings::sanitize_cadence( 'weekly' ),  'weekly → weekly' );
pr_assert_equals( 'monthly', PR_Core_Settings::sanitize_cadence( 'monthly' ), 'monthly → monthly' );
pr_assert_equals( 'weekly',  PR_Core_Settings::sanitize_cadence( 'hourly' ),  'hourly (invalid) → weekly default' );
pr_assert_equals( 'weekly',  PR_Core_Settings::sanitize_cadence( '' ),        'empty → weekly default' );
pr_assert_equals( 'weekly',  PR_Core_Settings::sanitize_cadence( 'DAILY' ),   'uppercase → weekly (case-sensitive)' );

// ── sanitize_emails() ───────────────────────────────────────────────────

echo "\nsanitize_emails():\n";
pr_assert_equals( '',                    PR_Core_Settings::sanitize_emails( '' ),                              'empty → empty' );
pr_assert_equals( 'admin@example.com',   PR_Core_Settings::sanitize_emails( 'admin@example.com' ),            'valid single → kept' );
pr_assert_equals( '',                    PR_Core_Settings::sanitize_emails( 'not-an-email' ),                  'invalid single → empty' );
pr_assert_equals( 'a@test.com, b@test.com', PR_Core_Settings::sanitize_emails( 'a@test.com, b@test.com' ),    'valid pair → both kept' );
pr_assert_equals( 'good@test.com',       PR_Core_Settings::sanitize_emails( 'good@test.com, bad-email' ),     'mixed → only valid kept' );
pr_assert_equals( 'trimmed@test.com',    PR_Core_Settings::sanitize_emails( '  trimmed@test.com  ' ),         'whitespace trimmed around valid email' );

// ── reschedule_cron(): no-op when same value ────────────────────────────

echo "\nreschedule_cron() — no-op when value unchanged:\n";
$GLOBALS['pr_core_cron_calls'] = [];
PR_Core_Settings::reschedule_cron( 'weekly', 'weekly' );
pr_assert( empty( $GLOBALS['pr_core_cron_calls'] ), 'no cron calls when old === new' );

// ── reschedule_cron(): clears + reschedules on change ───────────────────

echo "\nreschedule_cron() — reschedules when value changes:\n";
$GLOBALS['pr_core_cron_calls'] = [];
PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );

$actions = array_column( $GLOBALS['pr_core_cron_calls'], 'action' );
pr_assert( in_array( 'clear', $actions, true ),    'wp_clear_scheduled_hook was called' );
pr_assert( in_array( 'schedule', $actions, true ),  'wp_schedule_event was called' );

$schedule_call = null;
foreach ( $GLOBALS['pr_core_cron_calls'] as $call ) {
	if ( 'schedule' === $call['action'] ) {
		$schedule_call = $call;
		break;
	}
}
pr_assert( null !== $schedule_call,                                                    'schedule call found' );
pr_assert_equals( 'pr_core_verification_scan', $schedule_call['hook'] ?? '',           'rescheduled hook = pr_core_verification_scan' );
pr_assert_equals( 'daily', $schedule_call['recurrence'] ?? '',                         'rescheduled with new cadence "daily"' );

// Clear fires before schedule (correct ordering).
$clear_idx    = array_search( 'clear', $actions, true );
$schedule_idx = array_search( 'schedule', $actions, true );
pr_assert( $clear_idx < $schedule_idx, 'clear fires before schedule' );

// ── Summary ─────────────────────────────────────────────────────────────

exit( pr_test_summary() );
