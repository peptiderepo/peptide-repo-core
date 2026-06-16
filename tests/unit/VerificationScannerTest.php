<?php
/**
 * Unit tests for PR_Core_Verification_Scanner::compute_status().
 *
 * Ported from tests/unit/test-verification-scanner.php.
 *
 * Coverage:
 *   - Empty last_verified → overdue.
 *   - Days < 90% of threshold → current.
 *   - Days >= 90% and < 100% → due.
 *   - Days >= threshold → overdue.
 *   - Boundary at exactly 90% → due.
 *   - high velocity applies 60-day threshold from option.
 *   - low velocity applies 365-day constant (ignores option).
 *   - medium/default applies 180-day threshold from option.
 *   - Unknown velocity falls to medium/default.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for PR_Core_Verification_Scanner::compute_status().
 */
class VerificationScannerTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_core_options'] = [];
		require_once PR_CORE_PLUGIN_DIR . 'includes/scanner/class-pr-core-verification-scanner.php';
	}

	// ── Helper ────────────────────────────────────────────────────────.

	private function days_ago( int $days ): string {
		return gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
	}

	// ── Empty last_verified ───────────────────────────────────────────.

	public function test_empty_last_verified_returns_overdue(): void {
		$this->assertSame(
			'overdue',
			PR_Core_Verification_Scanner::compute_status( '', 'medium' )
		);
	}

	// ── Medium velocity (default 180-day threshold) ───────────────────.

	public function test_medium_160_days_is_current(): void {
		$this->assertSame( 'current', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 160 ), 'medium' ) );
	}

	public function test_medium_163_days_is_due(): void {
		$this->assertSame( 'due', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 163 ), 'medium' ) );
	}

	public function test_medium_180_days_is_overdue(): void {
		$this->assertSame( 'overdue', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 180 ), 'medium' ) );
	}

	public function test_medium_200_days_is_overdue(): void {
		$this->assertSame( 'overdue', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 200 ), 'medium' ) );
	}

	// ── 90% boundary exactness ────────────────────────────────────────.

	public function test_boundary_89_days_threshold_100_is_current(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 100 ];
		$this->assertSame( 'current', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 89 ), 'medium' ) );
	}

	public function test_boundary_90_days_threshold_100_is_due(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 100 ];
		$this->assertSame( 'due', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 90 ), 'medium' ) );
	}

	public function test_boundary_100_days_threshold_100_is_overdue(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 100 ];
		$this->assertSame( 'overdue', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 100 ), 'medium' ) );
	}

	// ── High velocity (60-day threshold from option) ──────────────────.

	public function test_high_50_days_is_current(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_high_velocity_threshold' => 60 ];
		$this->assertSame( 'current', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 50 ), 'high' ) );
	}

	public function test_high_55_days_is_due(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_high_velocity_threshold' => 60 ];
		$this->assertSame( 'due', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 55 ), 'high' ) );
	}

	public function test_high_61_days_is_overdue(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_high_velocity_threshold' => 60 ];
		$this->assertSame( 'overdue', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 61 ), 'high' ) );
	}

	// ── Low velocity (365-day constant — ignores option) ──────────────.

	public function test_low_300_days_is_current(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 999 ];
		$this->assertSame( 'current', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 300 ), 'low' ) );
	}

	public function test_low_340_days_is_due(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 999 ];
		$this->assertSame( 'due', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 340 ), 'low' ) );
	}

	public function test_low_366_days_is_overdue(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 999 ];
		$this->assertSame( 'overdue', PR_Core_Verification_Scanner::compute_status( $this->days_ago( 366 ), 'low' ) );
	}

	// ── Unknown velocity falls to medium/default ──────────────────────.

	public function test_unknown_velocity_uses_default_threshold(): void {
		$GLOBALS['pr_core_options'] = [ 'pr_core_default_threshold' => 180 ];
		$this->assertSame(
			'current',
			PR_Core_Verification_Scanner::compute_status( $this->days_ago( 100 ), 'unknown' )
		);
	}
}
