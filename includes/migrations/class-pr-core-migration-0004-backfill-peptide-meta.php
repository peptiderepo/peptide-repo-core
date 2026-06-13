<?php
declare(strict_types=1);

/**
 * Migration 0004: Backfill peptide schema meta from PSA psa_* keys.
 *
 * What: Copies PSA-stored PubChem data (molecular formula, weight, aliases)
 *       into PR Core's own _pr_molecular_formula, _pr_molecular_weight,
 *       _pr_aliases meta keys on all published peptide posts.
 *       For posts missing PSA data, attempts a PubChem REST lookup via
 *       PR_Core_Pubchem_Client.
 *
 * Who calls it: PR_Core_Migration_Runner::run_pending().
 * Dependencies: WordPress get_post_meta(), update_post_meta(), get_posts().
 *               PR_Core_Pubchem_Client for PubChem fallback.
 *               PR_Core_Schema_Sanitizers for formula sanitization.
 *               PR_Core_Peptide_CPT::POST_TYPE for query.
 *
 * Idempotent: skips posts where all three PR Core meta keys are already non-empty.
 * Re-runnable: safe to run multiple times.
 *
 * PSA source keys (all treated as UNTRUSTED — sanitized before writing):
 *   psa_molecular_formula  => _pr_molecular_formula  (string)
 *   psa_molecular_weight   => _pr_molecular_weight   (float string, strip "Da")
 *   psa_aliases            => _pr_aliases            (JSON array string)
 *
 * @see ARCHITECTURE.md     — §v0.6.0 schema sprint.
 * @see CONVENTIONS.md      — How To: Add a New Migration.
 * @see class-pr-core-pubchem-client.php — HTTP client for PubChem REST.
 */
class PR_Core_Migration_0004_Backfill_Peptide_Meta {

	/** @var string Meta key: molecular formula (PR Core namespace). */
	public const META_FORMULA = '_pr_molecular_formula';

	/** @var string Meta key: molecular weight as float string (PR Core namespace). */
	public const META_WEIGHT  = '_pr_molecular_weight';

	/** @var string Meta key: aliases JSON array (PR Core namespace). */
	public const META_ALIASES = '_pr_aliases';

	/** @var PR_Core_Pubchem_Client HTTP client for PubChem REST API. */
	private PR_Core_Pubchem_Client $pubchem;

	/**
	 * Construct migration with optional PubChem client (injectable for testing).
	 *
	 * @param PR_Core_Pubchem_Client|null $pubchem PubChem client; creates default if null.
	 */
	public function __construct( ?PR_Core_Pubchem_Client $pubchem = null ) {
		$this->pubchem = $pubchem ?? new PR_Core_Pubchem_Client();
	}

	/**
	 * Run the backfill migration.
	 *
	 * Side effects: reads post meta, writes post meta, logs via error_log.
	 *
	 * @return void
	 */
	public function up(): void {
		$peptide_ids = $this->get_all_peptide_ids();

		if ( empty( $peptide_ids ) ) {
			error_log( '[PR Core Migration 0004] No peptide posts found — nothing to backfill.' );
			return;
		}

		$counts = [ 'skipped' => 0, 'copied_psa' => 0, 'pubchem_ok' => 0, 'pubchem_skip' => 0 ];

		foreach ( $peptide_ids as $post_id ) {
			$result             = $this->backfill_post( $post_id );
			$counts[ $result ] += 1;
		}

		error_log( sprintf(
			'[PR Core Migration 0004] Complete. Skipped: %d, PSA-copied: %d, PubChem-ok: %d, PubChem-skip: %d',
			$counts['skipped'],
			$counts['copied_psa'],
			$counts['pubchem_ok'],
			$counts['pubchem_skip']
		) );
	}

	/**
	 * Backfill meta for a single peptide post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string Result: skipped|copied_psa|pubchem_ok|pubchem_skip.
	 */
	private function backfill_post( int $post_id ): string {
		$existing_formula = (string) get_post_meta( $post_id, self::META_FORMULA, true );
		$existing_weight  = (string) get_post_meta( $post_id, self::META_WEIGHT, true );
		$existing_aliases = (string) get_post_meta( $post_id, self::META_ALIASES, true );

		if ( '' !== $existing_formula && '' !== $existing_weight && '' !== $existing_aliases ) {
			return 'skipped';
		}

		$psa_formula = (string) get_post_meta( $post_id, 'psa_molecular_formula', true );

		if ( '' !== $psa_formula ) {
			$psa_weight  = (string) get_post_meta( $post_id, 'psa_molecular_weight', true );
			$psa_aliases = (string) get_post_meta( $post_id, 'psa_aliases', true );
			$this->write_meta_from_psa( $post_id, $psa_formula, $psa_weight, $psa_aliases );
			return 'copied_psa';
		}

		// No PSA data — attempt PubChem REST lookup.
		$psa_cid = (string) get_post_meta( $post_id, 'psa_pubchem_cid', true );
		$post    = get_post( $post_id );
		$name    = $post ? sanitize_text_field( $post->post_title ) : '';

		$data = ( '' !== $psa_cid && ctype_digit( $psa_cid ) )
			? $this->pubchem->fetch_by_cid( $psa_cid )
			: null;

		if ( null === $data && '' !== $name ) {
			$data = $this->pubchem->fetch_by_name( $name );
		}

		if ( null === $data ) {
			error_log( sprintf(
				'[PR Core Migration 0004] Post ID %d (%s): PubChem returned no data — leaving meta empty.',
				$post_id,
				esc_html( $name )
			) );
			return 'pubchem_skip';
		}

		$formula      = PR_Core_Schema_Sanitizers::sanitize_molecular_formula( $data['formula'] );
		$weight       = (float) $data['weight'];
		$aliases_json = $this->synonyms_to_json( $data['synonyms'] ?? [] );
		$this->write_pr_meta( $post_id, $formula, $weight, $aliases_json );

		error_log( sprintf(
			'[PR Core Migration 0004] Post ID %d (%s): PubChem-sourced formula=%s weight=%s',
			$post_id,
			esc_html( $name ),
			$formula,
			(string) $weight
		) );

		return 'pubchem_ok';
	}

	/**
	 * Write PR Core meta from PSA source values.
	 *
	 * Input is UNTRUSTED — sanitizes before writing.
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param string $formula  Raw psa_molecular_formula value.
	 * @param string $weight   Raw psa_molecular_weight value (may include " Da").
	 * @param string $aliases  Raw psa_aliases value (comma-separated).
	 * @return void
	 */
	private function write_meta_from_psa( int $post_id, string $formula, string $weight, string $aliases ): void {
		$clean_formula = PR_Core_Schema_Sanitizers::sanitize_molecular_formula( $formula );
		$clean_weight  = $this->parse_weight( $weight );
		$clean_aliases = $this->parse_aliases_string( $aliases );
		$this->write_pr_meta( $post_id, $clean_formula, $clean_weight, $clean_aliases );
	}

	/**
	 * Write the three PR Core meta keys for a post.
	 *
	 * Only writes a field when it has a usable value to avoid overwriting
	 * existing data with empty placeholders.
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param string $formula  Sanitized formula (empty = skip).
	 * @param float  $weight   Parsed weight in g/mol (0 = skip).
	 * @param string $aliases  JSON array string (empty array = skip).
	 * @return void
	 */
	private function write_pr_meta( int $post_id, string $formula, float $weight, string $aliases ): void {
		if ( '' !== $formula ) {
			update_post_meta( $post_id, self::META_FORMULA, $formula );
		}

		if ( $weight > 0.0 ) {
			update_post_meta( $post_id, self::META_WEIGHT, (string) $weight );
		} elseif ( '' !== $formula ) {
			error_log( '[PR Core Migration 0004] Post ID ' . $post_id . ': weight parsed to 0 — check source.' );
		}

		$decoded = json_decode( $aliases, true );
		if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
			update_post_meta( $post_id, self::META_ALIASES, $aliases );
		}
	}

	/**
	 * Parse a molecular weight string to a float.
	 *
	 * Strips trailing " Da" suffix (case-insensitive) and whitespace.
	 * Returns 0.0 on parse failure.
	 *
	 * @param string $value Raw weight value (e.g., "1419.5 Da").
	 * @return float Parsed weight in g/mol, 0.0 on failure.
	 */
	public function parse_weight( string $value ): float {
		$clean = trim( preg_replace( '/\s*[Dd][Aa]\s*$/', '', trim( $value ) ) ?? '' );
		return (float) $clean;
	}

	/**
	 * Parse a comma-separated alias string into a sanitized JSON array string.
	 *
	 * Input is UNTRUSTED — each alias is sanitized individually.
	 *
	 * @param string $value Comma-separated aliases.
	 * @return string JSON array of sanitized strings, or '[]' if empty.
	 */
	public function parse_aliases_string( string $value ): string {
		if ( '' === trim( $value ) ) {
			return '[]';
		}

		$clean = [];
		foreach ( explode( ',', $value ) as $part ) {
			$alias = sanitize_text_field( trim( $part ) );
			if ( '' !== $alias ) {
				$clean[] = $alias;
			}
		}

		return wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) ?: '[]';
	}

	/**
	 * Convert a raw synonyms array from PubChem to a JSON string.
	 *
	 * Sanitizes each synonym. Returns '[]' on empty input.
	 *
	 * @param string[] $synonyms Raw synonym strings from PubChem.
	 * @return string JSON array of sanitized strings.
	 */
	private function synonyms_to_json( array $synonyms ): string {
		$clean = [];
		foreach ( $synonyms as $syn ) {
			$alias = sanitize_text_field( (string) $syn );
			if ( '' !== $alias ) {
				$clean[] = $alias;
			}
		}
		return wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) ?: '[]';
	}

	/**
	 * Get IDs of all published peptide posts.
	 *
	 * @return int[]
	 */
	private function get_all_peptide_ids(): array {
		$posts = get_posts( [
			'post_type'      => PR_Core_Peptide_CPT::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		] );
		return array_map( 'intval', is_array( $posts ) ? $posts : [] );
	}
}
