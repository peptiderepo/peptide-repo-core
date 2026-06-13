<?php
/**
 * Unit tests for PR_Core_Migration_0004_Backfill_Peptide_Meta.
 *
 * Run: php tests/unit/test-migration-0004.php
 * Exit 0 = all pass, 1 = any failure.
 *
 * Coverage:
 *   - parse_weight(): strips "Da" suffix, strips "DA" variant, handles plain float,
 *     handles empty string (returns 0.0), handles zero-value string.
 *   - parse_aliases_string(): splits comma-separated string, trims whitespace,
 *     drops empty segments, returns '[]' for empty input.
 *   - PR_Core_Schema_Sanitizers::sanitize_molecular_formula(): valid chars, strips HTML, strips invalid.
 *   - Idempotency: backfill_post() returns 'skipped' when all three PR Core keys populated.
 *   - PSA source path: copies and transforms psa_* values to _pr_* keys.
 *   - Empty PSA data: skips to pubchem path, returns 'pubchem_skip' on no data.
 *
 * Not covered here (require live WP + DB or network):
 *   - PubChem HTTP round-trip (mocked at the http_get_once level in integration tests).
 *   - Actual update_post_meta() persistence.
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

// Bootstrap the lightweight WP stubs.
require_once __DIR__ . '/../bootstrap.php';

// ── Additional stubs needed for migration 0004 ──────────────────────────

if ( ! defined( 'PR_CORE_TARGET_SCHEMA_VERSION' ) ) {
	define( 'PR_CORE_TARGET_SCHEMA_VERSION', 4 );
}

// Post meta store for testing.
$GLOBALS['pr_test_postmeta'] = [];
$GLOBALS['pr_test_updated_meta'] = [];

function get_post_meta( int $post_id, string $key, bool $single = false ) {
	$store = $GLOBALS['pr_test_postmeta'][ $post_id ] ?? [];
	return $single ? ( $store[ $key ] ?? '' ) : array_values( $store );
}

function update_post_meta( int $post_id, string $key, $value ): bool {
	$GLOBALS['pr_test_postmeta'][ $post_id ][ $key ] = $value;
	$GLOBALS['pr_test_updated_meta'][]                = [ 'post_id' => $post_id, 'key' => $key, 'value' => $value ];
	return true;
}

function get_post( int $id ) {
	if ( isset( $GLOBALS['pr_test_posts'][ $id ] ) ) {
		return $GLOBALS['pr_test_posts'][ $id ];
	}
	return null;
}

function get_posts( array $args = [] ): array {
	return $GLOBALS['pr_test_all_post_ids'] ?? [];
}

function is_wp_error( $value ): bool {
	return false;
}

function wp_strip_all_tags( $value ): string {
	return strip_tags( (string) $value );
}

function esc_html( string $val ): string {
	return htmlspecialchars( $val, ENT_QUOTES, 'UTF-8' );
}

if ( ! function_exists( 'error_log' ) ) {
	function error_log( string $msg ): bool {
		// Suppress during tests.
		return true;
	}
}

// Load dependencies.
require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-schema-sanitizers.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/migrations/class-pr-core-pubchem-client.php';
// Load migration class under test.
require_once PR_CORE_PLUGIN_DIR . 'includes/migrations/class-pr-core-migration-0004-backfill-peptide-meta.php';

// ── Expose private methods via reflection ────────────────────────────────

/**
 * Call a private/protected method on an object via reflection.
 *
 * @param object $obj        Instance.
 * @param string $method     Method name.
 * @param array  $args       Arguments.
 * @return mixed Return value.
 */
function call_private( object $obj, string $method, array $args = [] ) {
	$ref = new ReflectionMethod( $obj, $method );
	$ref->setAccessible( true );
	return $ref->invokeArgs( $obj, $args );
}

$migration = new PR_Core_Migration_0004_Backfill_Peptide_Meta();

echo "== PR_Core_Migration_0004 unit tests ==\n\n";

// ── parse_weight() ───────────────────────────────────────────────────────

echo "parse_weight():\n";

pr_assert_equals(
	1419.5,
	$migration->parse_weight( '1419.5 Da' ),
	'strips " Da" suffix and parses float'
);

pr_assert_equals(
	1419.5,
	$migration->parse_weight( '1419.5 DA' ),
	'strips uppercase " DA" suffix'
);

pr_assert_equals(
	1419.5,
	$migration->parse_weight( '1419.5' ),
	'parses plain float string without suffix'
);

pr_assert_equals(
	0.0,
	$migration->parse_weight( '' ),
	'returns 0.0 for empty string'
);

pr_assert_equals(
	0.0,
	$migration->parse_weight( '  Da  ' ),
	'returns 0.0 for suffix-only string'
);

pr_assert_equals(
	800.123,
	$migration->parse_weight( '  800.123 da  ' ),
	'strips whitespace and lowercase "da"'
);

// ── parse_aliases_string() ───────────────────────────────────────────────

echo "\nparse_aliases_string():\n";

$result = json_decode(
	$migration->parse_aliases_string( 'Body Protection Compound-157, PL 14736, PL-10, Bepecin' ),
	true
);
pr_assert( is_array( $result ), 'returns a JSON-decodable array' );
pr_assert_equals( 4, count( $result ), 'correct count: 4 aliases' );
pr_assert_equals( 'Body Protection Compound-157', $result[0], 'first alias trimmed correctly' );
pr_assert_equals( 'PL 14736', $result[1], 'second alias trimmed correctly' );

$empty_result = $migration->parse_aliases_string( '' );
pr_assert_equals( '[]', $empty_result, 'empty string returns "[]"' );

$whitespace_result = $migration->parse_aliases_string( '  ,  ,  ' );
pr_assert_equals( '[]', $whitespace_result, 'whitespace-only segments dropped, returns "[]"' );

$single = json_decode( $migration->parse_aliases_string( 'Only One' ), true );
pr_assert_equals( 1, count( $single ), 'single alias produces array of 1' );

// ── sanitize_formula() ───────────────────────────────────────────────────

echo "\nPR_Core_Schema_Sanitizers::sanitize_molecular_formula():\n";

pr_assert_equals(
	'C62H98N16O22',
	PR_Core_Schema_Sanitizers::sanitize_molecular_formula( 'C62H98N16O22' ),
	'valid formula passes unchanged'
);

pr_assert_equals(
	'C62H98N16O22',
	PR_Core_Schema_Sanitizers::sanitize_molecular_formula( '<b>C62H98N16O22</b>' ),
	'HTML tags stripped from formula'
);

pr_assert_equals(
	'C10H12',
	PR_Core_Schema_Sanitizers::sanitize_molecular_formula( "C10H12\ninjection" ),
	'non-formula characters removed'
);

pr_assert_equals(
	'',
	PR_Core_Schema_Sanitizers::sanitize_molecular_formula( '' ),
	'empty string returns empty string'
);

// ── Idempotency: skips when all three PR Core keys populated ─────────────

echo "\nIdempotency (all _pr_* keys populated):\n";

$GLOBALS['pr_test_postmeta'] = [];
$GLOBALS['pr_test_updated_meta'] = [];
$GLOBALS['pr_test_postmeta'][1] = [
	'_pr_molecular_formula' => 'C62H98N16O22',
	'_pr_molecular_weight'  => '1419.5',
	'_pr_aliases'           => '["Alias One"]',
];

$result = call_private( $migration, 'backfill_post', [ 1 ] );
pr_assert_equals( 'skipped', $result, 'returns "skipped" when all three keys non-empty' );
pr_assert( empty( $GLOBALS['pr_test_updated_meta'] ), 'no update_post_meta calls when skipped' );

// ── PSA source path ──────────────────────────────────────────────────────

echo "\nPSA source path:\n";

$GLOBALS['pr_test_postmeta'] = [];
$GLOBALS['pr_test_updated_meta'] = [];
$GLOBALS['pr_test_postmeta'][2] = [
	'psa_molecular_formula' => 'C62H98N16O22',
	'psa_molecular_weight'  => '1419.5 Da',
	'psa_aliases'           => 'Body Protection Compound-157, PL 14736',
];
$GLOBALS['pr_test_posts'][2] = (object) [ 'post_title' => 'BPC-157', 'ID' => 2 ];

$result = call_private( $migration, 'backfill_post', [ 2 ] );
pr_assert_equals( 'copied_psa', $result, 'returns "copied_psa" when PSA data present' );

$updated = $GLOBALS['pr_test_updated_meta'];
$formula_written = false;
$weight_written  = false;
$aliases_written = false;

foreach ( $updated as $entry ) {
	if ( '_pr_molecular_formula' === $entry['key'] ) {
		$formula_written = true;
		pr_assert_equals( 'C62H98N16O22', $entry['value'], 'formula written correctly' );
	}
	if ( '_pr_molecular_weight' === $entry['key'] ) {
		$weight_written = true;
		pr_assert_equals( '1419.5', $entry['value'], 'weight written as float string without Da' );
	}
	if ( '_pr_aliases' === $entry['key'] ) {
		$aliases_written = true;
		$aliases_arr     = json_decode( $entry['value'], true );
		pr_assert( is_array( $aliases_arr ), 'aliases stored as JSON array' );
		pr_assert_equals( 2, count( $aliases_arr ), 'correct number of aliases' );
	}
}

pr_assert( $formula_written, 'molecular formula meta was written' );
pr_assert( $weight_written, 'molecular weight meta was written' );
pr_assert( $aliases_written, 'aliases meta was written' );

// ── Partial re-run: existing values preserved ────────────────────────────

echo "\nIdempotency: only some keys populated (partial re-run):\n";

$GLOBALS['pr_test_postmeta'] = [];
$GLOBALS['pr_test_updated_meta'] = [];
// Only formula already set; weight and aliases are empty.
$GLOBALS['pr_test_postmeta'][3] = [
	'_pr_molecular_formula' => 'C10H12',
	'_pr_molecular_weight'  => '',
	'_pr_aliases'           => '',
	'psa_molecular_formula' => 'C62H98N16O22',
	'psa_molecular_weight'  => '1419.5 Da',
	'psa_aliases'           => 'Body Protection Compound-157',
];
$GLOBALS['pr_test_posts'][3] = (object) [ 'post_title' => 'BPC-157', 'ID' => 3 ];

$result = call_private( $migration, 'backfill_post', [ 3 ] );
pr_assert_equals( 'copied_psa', $result, 'runs PSA copy even when only some keys populated' );

// ── PubChem skip path: no PSA data, no network ──────────────────────────

echo "\nPubChem skip path (no PSA data, no network in unit tests):\n";

// Inject a null-returning PubChem client mock to simulate network failure.
$mock_pubchem = new class extends PR_Core_Pubchem_Client {
	public function fetch_by_cid( string $cid ): ?array { return null; }
	public function fetch_by_name( string $name ): ?array { return null; }
};
$mock_migration = new PR_Core_Migration_0004_Backfill_Peptide_Meta( $mock_pubchem );

$GLOBALS['pr_test_postmeta'] = [];
$GLOBALS['pr_test_updated_meta'] = [];
$GLOBALS['pr_test_postmeta'][10] = []; // No PSA data, no PR Core data.
$GLOBALS['pr_test_posts'][10] = (object) [ 'post_title' => 'IGF-1 LR3', 'ID' => 10 ];

$result = call_private( $mock_migration, 'backfill_post', [ 10 ] );
pr_assert_equals( 'pubchem_skip', $result, 'returns "pubchem_skip" when PubChem network fails' );
pr_assert( empty( $GLOBALS['pr_test_updated_meta'] ), 'no meta written on pubchem_skip' );

// ── Summary ──────────────────────────────────────────────────────────────

exit( pr_test_summary() );
