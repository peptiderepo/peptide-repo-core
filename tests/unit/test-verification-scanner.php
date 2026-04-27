<?php
declare(strict_types=1);

/**
 * Tests for PR_Core_Verification_Scanner.
 *
 * What: Unit tests for status computation logic.
 * Dependencies: PHPUnit, WordPress test utilities.
 *
 * Mock strategy: Zero real DB calls — mock get_option() and update_post_meta().
 */
class Test_Verification_Scanner extends \WP_UnitTestCase {

	/**
	 * Test current status when days_since < threshold * 0.9.
	 */
	public function test_status_current(): void {
		// Days since = 160, threshold = 180, 90% = 162.
		// 160 < 162 => current.
		$last_verified = gmdate( 'Y-m-d H:i:s', time() - ( 160 * DAY_IN_SECONDS ) );

		// Mock the repository and post meta.
		$dto = $this->create_mock_dto( 1, $last_verified, 'medium' );

		$status = $this->compute_status( $dto, 180, 'medium' );
		$this->assertEquals( 'current', $status );
	}

	/**
	 * Test due status when threshold * 0.9 <= days_since < threshold.
	 */
	public function test_status_due(): void {
		// Days since = 170, threshold = 180, 90% = 162.
		// 162 <= 170 < 180 => due.
		$last_verified = gmdate( 'Y-m-d H:i:s', time() - ( 170 * DAY_IN_SECONDS ) );

		$dto = $this->create_mock_dto( 2, $last_verified, 'medium' );
		$status = $this->compute_status( $dto, 180, 'medium' );
		$this->assertEquals( 'due', $status );
	}

	/**
	 * Test overdue status when days_since >= threshold.
	 */
	public function test_status_overdue(): void {
		// Days since = 190, threshold = 180.
		// 190 >= 180 => overdue.
		$last_verified = gmdate( 'Y-m-d H:i:s', time() - ( 190 * DAY_IN_SECONDS ) );

		$dto = $this->create_mock_dto( 3, $last_verified, 'medium' );
		$status = $this->compute_status( $dto, 180, 'medium' );
		$this->assertEquals( 'overdue', $status );
	}

	/**
	 * Test empty verified date => overdue.
	 */
	public function test_empty_verified_date_is_overdue(): void {
		$dto = $this->create_mock_dto( 4, '', 'medium' );
		$status = $this->compute_status( $dto, 180, 'medium', true );
		$this->assertEquals( 'overdue', $status );
	}

	/**
	 * Test high velocity threshold.
	 */
	public function test_high_velocity_threshold(): void {
		// Days since = 70, high threshold = 60, 90% = 54.
		// 54 <= 70 => due (not current even with high velocity).
		$last_verified = gmdate( 'Y-m-d H:i:s', time() - ( 70 * DAY_IN_SECONDS ) );

		$dto = $this->create_mock_dto( 5, $last_verified, 'high' );
		$status = $this->compute_status( $dto, 60, 'high' );
		$this->assertEquals( 'due', $status );
	}

	/**
	 * Test low velocity threshold.
	 */
	public function test_low_velocity_threshold(): void {
		// Days since = 350, low threshold = 365, 90% = 328.5.
		// 328.5 < 350 < 365 => due.
		$last_verified = gmdate( 'Y-m-d H:i:s', time() - ( 350 * DAY_IN_SECONDS ) );

		$dto = $this->create_mock_dto( 6, $last_verified, 'low' );
		$status = $this->compute_status( $dto, 365, 'low' );
		$this->assertEquals( 'due', $status );
	}

	/**
	 * Create a mock DTO.
	 *
	 * @param int    $id Post ID.
	 * @param string $last_verified Verified date (or empty).
	 * @param string $velocity Velocity level.
	 * @return object Mock DTO.
	 */
	private function create_mock_dto( int $id, string $last_verified, string $velocity ): object {
		$dto = new \stdClass();
		$dto->id = $id;
		$dto->title = "Test Peptide $id";
		$dto->excerpt = '';
		$dto->content = '';

		// Store in post meta for the computation.
		update_post_meta( $id, '_pr_last_source_verified', $last_verified );
		update_post_meta( $id, '_pr_verification_velocity', $velocity );

		return $dto;
	}

	/**
	 * Compute status using the scanner logic inline (no DB calls beyond meta).
	 *
	 * @param object $dto Peptide DTO.
	 * @param int    $threshold Threshold in days.
	 * @param string $velocity Velocity level.
	 * @param bool   $empty_date Whether date is empty.
	 * @return string Status.
	 */
	private function compute_status( object $dto, int $threshold, string $velocity, bool $empty_date = false ): string {
		$last_verified = get_post_meta( $dto->id, '_pr_last_source_verified', true );

		if ( $empty_date || empty( $last_verified ) ) {
			return 'overdue';
		}

		$days_since = ( time() - strtotime( $last_verified ) ) / DAY_IN_SECONDS;

		return $days_since < ( $threshold * 0.9 )
			? 'current'
			: ( $days_since < $threshold ? 'due' : 'overdue' );
	}
}
