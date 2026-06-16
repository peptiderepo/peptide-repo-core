<?php
/**
 * Unit tests for PR_Core_Settings verification methods.
 *
 * Ported from tests/unit/test-verification-settings.php.
 *
 * Coverage:
 *   - sanitize_cadence() accepts valid values, rejects invalid → 'weekly'.
 *   - sanitize_emails() filters invalid addresses, preserves valid ones.
 *   - reschedule_cron() no-ops when old === new value.
 *   - reschedule_cron() clears + reschedules when value changes.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for PR_Core_Settings verification methods.
 */
class VerificationSettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_core_options']    = [];
		$GLOBALS['pr_core_cron_calls'] = [];
		$GLOBALS['pr_core_test_state'] = [
			'existing_post_types'   => [],
			'existing_taxonomies'   => [],
			'registered_post_types' => [],
			'registered_taxonomies' => [],
			'registered_meta'       => [],
			'added_actions'         => [],
			'added_filters'         => [],
		];
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/admin/class-pr-core-settings.php';
	}

	// ── sanitize_cadence() ────────────────────────────────────────────.

	public function test_sanitize_cadence_daily(): void {
		$this->assertSame( 'daily', PR_Core_Settings::sanitize_cadence( 'daily' ) );
	}

	public function test_sanitize_cadence_weekly(): void {
		$this->assertSame( 'weekly', PR_Core_Settings::sanitize_cadence( 'weekly' ) );
	}

	public function test_sanitize_cadence_monthly(): void {
		$this->assertSame( 'monthly', PR_Core_Settings::sanitize_cadence( 'monthly' ) );
	}

	public function test_sanitize_cadence_invalid_defaults_to_weekly(): void {
		$this->assertSame( 'weekly', PR_Core_Settings::sanitize_cadence( 'hourly' ) );
	}

	public function test_sanitize_cadence_empty_defaults_to_weekly(): void {
		$this->assertSame( 'weekly', PR_Core_Settings::sanitize_cadence( '' ) );
	}

	public function test_sanitize_cadence_uppercase_defaults_to_weekly(): void {
		$this->assertSame( 'weekly', PR_Core_Settings::sanitize_cadence( 'DAILY' ) );
	}

	// ── sanitize_emails() ─────────────────────────────────────────────.

	public function test_sanitize_emails_empty_returns_empty(): void {
		$this->assertSame( '', PR_Core_Settings::sanitize_emails( '' ) );
	}

	public function test_sanitize_emails_valid_single_kept(): void {
		$this->assertSame( 'admin@example.com', PR_Core_Settings::sanitize_emails( 'admin@example.com' ) );
	}

	public function test_sanitize_emails_invalid_single_returns_empty(): void {
		$this->assertSame( '', PR_Core_Settings::sanitize_emails( 'not-an-email' ) );
	}

	public function test_sanitize_emails_valid_pair_both_kept(): void {
		$this->assertSame( 'a@test.com, b@test.com', PR_Core_Settings::sanitize_emails( 'a@test.com, b@test.com' ) );
	}

	public function test_sanitize_emails_mixed_only_valid_kept(): void {
		$this->assertSame( 'good@test.com', PR_Core_Settings::sanitize_emails( 'good@test.com, bad-email' ) );
	}

	public function test_sanitize_emails_whitespace_trimmed(): void {
		$this->assertSame( 'trimmed@test.com', PR_Core_Settings::sanitize_emails( '  trimmed@test.com  ' ) );
	}

	// ── reschedule_cron(): no-op when same value ──────────────────────.

	public function test_reschedule_cron_noop_when_value_unchanged(): void {
		PR_Core_Settings::reschedule_cron( 'weekly', 'weekly' );
		$this->assertEmpty( $GLOBALS['pr_core_cron_calls'] );
	}

	// ── reschedule_cron(): clears + reschedules on change ─────────────.

	public function test_reschedule_cron_calls_clear_when_changed(): void {
		PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );
		$actions = array_column( $GLOBALS['pr_core_cron_calls'], 'action' );
		$this->assertContains( 'clear', $actions );
	}

	public function test_reschedule_cron_calls_schedule_when_changed(): void {
		PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );
		$actions = array_column( $GLOBALS['pr_core_cron_calls'], 'action' );
		$this->assertContains( 'schedule', $actions );
	}

	public function test_reschedule_cron_schedules_correct_hook(): void {
		PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );
		$schedule_call = null;
		foreach ( $GLOBALS['pr_core_cron_calls'] as $call ) {
			if ( 'schedule' === $call['action'] ) {
				$schedule_call = $call;
				break;
			}
		}
		$this->assertNotNull( $schedule_call );
		$this->assertSame( 'pr_core_verification_scan', $schedule_call['hook'] ?? '' );
	}

	public function test_reschedule_cron_schedules_new_cadence(): void {
		PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );
		$schedule_call = null;
		foreach ( $GLOBALS['pr_core_cron_calls'] as $call ) {
			if ( 'schedule' === $call['action'] ) {
				$schedule_call = $call;
				break;
			}
		}
		$this->assertSame( 'daily', $schedule_call['recurrence'] ?? '' );
	}

	public function test_reschedule_cron_clear_fires_before_schedule(): void {
		PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );
		$actions      = array_column( $GLOBALS['pr_core_cron_calls'], 'action' );
		$clear_idx    = array_search( 'clear', $actions, true );
		$schedule_idx = array_search( 'schedule', $actions, true );
		$this->assertLessThan( $schedule_idx, $clear_idx );
	}
}
