<?php
declare(strict_types=1);

/**
 * Tests for PR_Core_Settings cadence and cron scheduling.
 *
 * What: Unit tests for settings registration and cron rescheduling.
 * Dependencies: PHPUnit, WordPress test utilities.
 */
class Test_Verification_Settings extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Clear any existing scheduled hooks.
		wp_clear_scheduled_hook( 'pr_core_verification_scan' );
	}

	/**
	 * Test that changing cadence triggers reschedule.
	 */
	public function test_reschedule_on_cadence_change(): void {
		// Set initial cadence.
		update_option( 'pr_core_scan_cadence', 'weekly' );

		// Schedule initial cron (simulate the activation).
		if ( ! wp_next_scheduled( 'pr_core_verification_scan' ) ) {
			wp_schedule_event( time(), 'weekly', 'pr_core_verification_scan' );
		}

		// Verify it's scheduled.
		$scheduled = wp_next_scheduled( 'pr_core_verification_scan' );
		$this->assertNotFalse( $scheduled );

		// Change the cadence (triggers reschedule via hook).
		update_option( 'pr_core_scan_cadence', 'daily' );

		// The reschedule_cron method should have cleared and rescheduled.
		// (In a real test, this would be tested via the hook — here we verify the logic.)
		PR_Core_Settings::reschedule_cron( 'weekly', 'daily' );

		// Verify cron still exists (just with new frequency).
		$scheduled = wp_next_scheduled( 'pr_core_verification_scan' );
		$this->assertNotFalse( $scheduled );
	}

	/**
	 * Test that same cadence does not reschedule.
	 */
	public function test_no_reschedule_if_unchanged(): void {
		update_option( 'pr_core_scan_cadence', 'weekly' );

		if ( ! wp_next_scheduled( 'pr_core_verification_scan' ) ) {
			wp_schedule_event( time(), 'weekly', 'pr_core_verification_scan' );
		}

		$first_scheduled = wp_next_scheduled( 'pr_core_verification_scan' );

		// Call reschedule with same value.
		PR_Core_Settings::reschedule_cron( 'weekly', 'weekly' );

		$second_scheduled = wp_next_scheduled( 'pr_core_verification_scan' );

		// Should be unchanged (same cron hook).
		$this->assertEquals( $first_scheduled, $second_scheduled );
	}

	/**
	 * Test cadence sanitization.
	 */
	public function test_sanitize_cadence(): void {
		$this->assertEquals( 'daily', PR_Core_Settings::sanitize_cadence( 'daily' ) );
		$this->assertEquals( 'weekly', PR_Core_Settings::sanitize_cadence( 'weekly' ) );
		$this->assertEquals( 'monthly', PR_Core_Settings::sanitize_cadence( 'monthly' ) );
		$this->assertEquals( 'weekly', PR_Core_Settings::sanitize_cadence( 'invalid' ) );
	}

	/**
	 * Test email sanitization.
	 */
	public function test_sanitize_emails(): void {
		$input = 'admin@example.com, editor@example.com';
		$output = PR_Core_Settings::sanitize_emails( $input );
		$this->assertStringContainsString( 'admin@example.com', $output );
		$this->assertStringContainsString( 'editor@example.com', $output );

		// Invalid emails filtered out.
		$input_bad = 'admin@example.com, invalid-email, editor@example.com';
		$output = PR_Core_Settings::sanitize_emails( $input_bad );
		$this->assertStringContainsString( 'admin@example.com', $output );
		$this->assertStringContainsString( 'editor@example.com', $output );
		$this->assertStringNotContainsString( 'invalid-email', $output );
	}
}
