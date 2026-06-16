<?php
/**
 * Unit tests for PR_Core_Migration_0004_Backfill_Peptide_Meta.
 *
 * Ported from tests/unit/test-migration-0004.php.
 *
 * Coverage:
 *   - parse_weight(): strips "Da"/"DA"/"da" suffix, handles plain float,
 *     empty string → 0.0, suffix-only → 0.0.
 *   - parse_aliases_string(): splits CSV, trims, drops empty, returns '[]' for empty.
 *   - PR_Core_Schema_Sanitizers::sanitize_molecular_formula(): valid, HTML, groups,
 *     injection, fully-invalid, empty.
 *   - Idempotency: backfill_post() returns 'skipped' when all three keys populated.
 *   - PSA source path: copies and transforms psa_* → _pr_* keys.
 *   - PubChem skip path: 'pubchem_skip' when PubChem network fails.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for PR_Core_Migration_0004_Backfill_Peptide_Meta.
 */
class Migration0004Test extends TestCase {

	/** @var PR_Core_Migration_0004_Backfill_Peptide_Meta */
	private $migration;

	protected function setUp(): void {
		$GLOBALS['pr_test_postmeta']     = [];
		$GLOBALS['pr_test_updated_meta'] = [];
		$GLOBALS['pr_test_posts']        = [];
		$GLOBALS['pr_core_test_state']   = [
			'existing_post_types'   => [],
			'existing_taxonomies'   => [],
			'registered_post_types' => [],
			'registered_taxonomies' => [],
			'registered_meta'       => [],
			'added_actions'         => [],
			'added_filters'         => [],
		];
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-schema-sanitizers.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/migrations/class-pr-core-pubchem-client.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/migrations/class-pr-core-migration-0004-backfill-peptide-meta.php';
		$this->migration = new PR_Core_Migration_0004_Backfill_Peptide_Meta();
	}

	// ── Helper: call private method via reflection ────────────────────.

	/**
	 * @param mixed[] $args
	 * @return mixed
	 */
	private function call_private( object $obj, string $method, array $args = [] ) {
		$ref = new ReflectionMethod( $obj, $method );
		$ref->setAccessible( true );
		return $ref->invokeArgs( $obj, $args );
	}

	// ── parse_weight() ────────────────────────────────────────────────.

	public function test_parse_weight_strips_da_suffix(): void {
		$this->assertSame( 1419.5, $this->call_private( $this->migration, 'parse_weight', [ '1419.5 Da' ] ) );
	}

	public function test_parse_weight_strips_uppercase_da(): void {
		$this->assertSame( 1419.5, $this->call_private( $this->migration, 'parse_weight', [ '1419.5 DA' ] ) );
	}

	public function test_parse_weight_plain_float(): void {
		$this->assertSame( 1419.5, $this->call_private( $this->migration, 'parse_weight', [ '1419.5' ] ) );
	}

	public function test_parse_weight_empty_returns_zero(): void {
		$this->assertSame( 0.0, $this->call_private( $this->migration, 'parse_weight', [ '' ] ) );
	}

	public function test_parse_weight_suffix_only_returns_zero(): void {
		$this->assertSame( 0.0, $this->call_private( $this->migration, 'parse_weight', [ '  Da  ' ] ) );
	}

	public function test_parse_weight_lowercase_da_with_whitespace(): void {
		$this->assertSame( 800.123, $this->call_private( $this->migration, 'parse_weight', [ '  800.123 da  ' ] ) );
	}

	// ── parse_aliases_string() ────────────────────────────────────────.

	public function test_parse_aliases_string_splits_csv(): void {
		$result = json_decode(
			$this->call_private( $this->migration, 'parse_aliases_string', [ 'Body Protection Compound-157, PL 14736, PL-10, Bepecin' ] ),
			true
		);
		$this->assertIsArray( $result );
		$this->assertCount( 4, $result );
		$this->assertSame( 'Body Protection Compound-157', $result[0] );
		$this->assertSame( 'PL 14736', $result[1] );
	}

	public function test_parse_aliases_string_empty_returns_json_empty_array(): void {
		$this->assertSame( '[]', $this->call_private( $this->migration, 'parse_aliases_string', [ '' ] ) );
	}

	public function test_parse_aliases_string_whitespace_only_returns_json_empty_array(): void {
		$this->assertSame( '[]', $this->call_private( $this->migration, 'parse_aliases_string', [ '  ,  ,  ' ] ) );
	}

	public function test_parse_aliases_string_single_alias(): void {
		$result = json_decode( $this->call_private( $this->migration, 'parse_aliases_string', [ 'Only One' ] ), true );
		$this->assertCount( 1, $result );
	}

	// ── sanitize_molecular_formula() ──────────────────────────────────.

	public function test_sanitize_formula_valid_passes_unchanged(): void {
		$this->assertSame( 'C62H98N16O22', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( 'C62H98N16O22' ) );
	}

	public function test_sanitize_formula_strips_html_tags(): void {
		$this->assertSame( 'C62H98N16O22', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( '<b>C62H98N16O22</b>' ) );
	}

	public function test_sanitize_formula_preserves_parentheses(): void {
		$this->assertSame( 'C2H5(OH)', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( 'C2H5(OH)' ) );
	}

	public function test_sanitize_formula_strips_non_formula_chars(): void {
		$this->assertSame( 'C10H12', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( "C10H12\ninjection" ) );
	}

	public function test_sanitize_formula_fully_invalid_returns_empty(): void {
		$this->assertSame( '', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( '<script>alert(1)</script>' ) );
	}

	public function test_sanitize_formula_empty_returns_empty(): void {
		$this->assertSame( '', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( '' ) );
	}

	public function test_sanitize_formula_whitespace_only_returns_empty(): void {
		$this->assertSame( '', PR_Core_Schema_Sanitizers::sanitize_molecular_formula( '   ' ) );
	}

	// ── Idempotency ───────────────────────────────────────────────────.

	public function test_backfill_post_skipped_when_all_keys_populated(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '["Alias One"]',
		];
		$result = $this->call_private( $this->migration, 'backfill_post', [ 1 ] );
		$this->assertSame( 'skipped', $result );
		$this->assertEmpty( $GLOBALS['pr_test_updated_meta'] );
	}

	// ── PSA source path ───────────────────────────────────────────────.

	public function test_backfill_post_copies_psa_data(): void {
		$GLOBALS['pr_test_postmeta'][2] = [
			'psa_molecular_formula' => 'C62H98N16O22',
			'psa_molecular_weight'  => '1419.5 Da',
			'psa_aliases'           => 'Body Protection Compound-157, PL 14736',
		];
		$GLOBALS['pr_test_posts'][2] = (object) [ 'post_title' => 'BPC-157', 'ID' => 2 ];
		$result                      = $this->call_private( $this->migration, 'backfill_post', [ 2 ] );
		$this->assertSame( 'copied_psa', $result );
	}

	public function test_backfill_post_psa_writes_formula_correctly(): void {
		$GLOBALS['pr_test_postmeta'][2] = [
			'psa_molecular_formula' => 'C62H98N16O22',
			'psa_molecular_weight'  => '1419.5 Da',
			'psa_aliases'           => 'Body Protection Compound-157',
		];
		$GLOBALS['pr_test_posts'][2] = (object) [ 'post_title' => 'BPC-157', 'ID' => 2 ];
		$this->call_private( $this->migration, 'backfill_post', [ 2 ] );
		$written = null;
		foreach ( $GLOBALS['pr_test_updated_meta'] as $entry ) {
			if ( '_pr_molecular_formula' === $entry['key'] ) {
				$written = $entry['value'];
			}
		}
		$this->assertSame( 'C62H98N16O22', $written );
	}

	public function test_backfill_post_psa_strips_da_from_weight(): void {
		$GLOBALS['pr_test_postmeta'][2] = [
			'psa_molecular_formula' => 'C62H98N16O22',
			'psa_molecular_weight'  => '1419.5 Da',
			'psa_aliases'           => 'Body Protection Compound-157',
		];
		$GLOBALS['pr_test_posts'][2] = (object) [ 'post_title' => 'BPC-157', 'ID' => 2 ];
		$this->call_private( $this->migration, 'backfill_post', [ 2 ] );
		$written = null;
		foreach ( $GLOBALS['pr_test_updated_meta'] as $entry ) {
			if ( '_pr_molecular_weight' === $entry['key'] ) {
				$written = $entry['value'];
			}
		}
		$this->assertSame( '1419.5', $written );
	}

	public function test_backfill_post_psa_writes_aliases_as_json_array(): void {
		$GLOBALS['pr_test_postmeta'][2] = [
			'psa_molecular_formula' => 'C62H98N16O22',
			'psa_molecular_weight'  => '1419.5 Da',
			'psa_aliases'           => 'Body Protection Compound-157, PL 14736',
		];
		$GLOBALS['pr_test_posts'][2] = (object) [ 'post_title' => 'BPC-157', 'ID' => 2 ];
		$this->call_private( $this->migration, 'backfill_post', [ 2 ] );
		$written = null;
		foreach ( $GLOBALS['pr_test_updated_meta'] as $entry ) {
			if ( '_pr_aliases' === $entry['key'] ) {
				$written = $entry['value'];
			}
		}
		$aliases = json_decode( $written, true );
		$this->assertIsArray( $aliases );
		$this->assertCount( 2, $aliases );
	}

	// ── PubChem skip path ─────────────────────────────────────────────.

	public function test_backfill_post_returns_pubchem_skip_when_network_fails(): void {
		$mock_pubchem = new class extends PR_Core_Pubchem_Client {
			public function fetch_by_cid( string $cid ): ?array { return null; }
			public function fetch_by_name( string $name ): ?array { return null; }
		};
		$mock_migration = new PR_Core_Migration_0004_Backfill_Peptide_Meta( $mock_pubchem );

		$GLOBALS['pr_test_postmeta'][10] = [];
		$GLOBALS['pr_test_posts'][10]    = (object) [ 'post_title' => 'IGF-1 LR3', 'ID' => 10 ];

		$result = $this->call_private( $mock_migration, 'backfill_post', [ 10 ] );
		$this->assertSame( 'pubchem_skip', $result );
		$this->assertEmpty( $GLOBALS['pr_test_updated_meta'] );
	}
}
