<?php
/**
 * Prab Meta Reader.
 *
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Reads and validates _prab_* post-meta written by PRAutoBlogger.
 *
 * All meta treated as untrusted: URLs validated (http/https), DOIs regex-checked,
 * JSON arrays decoded per-element, user IDs resolved via get_userdata(). Never throws.
 *
 * @see frontend/class-pr-core-jsonld-article.php — Consumer.
 * @see convo/prcore/decisions/2026-06-11-jsonld-contract-v1.md — Meta contract.
 *
 * @package Peptide_Repo_Core
 */
class PR_Core_Prab_Meta_Reader {
	/**
	 * Schema version meta key; presence opts the post in, value must equal 1.
	 *
	 * @var string
	 */
	public const META_SCHEMA_VERSION = '_prab_schema_version';

	/**
	 * Citations meta key; JSON array of {url, title, doi?, quality_score?}.
	 *
	 * @var string
	 */
	public const META_CITATIONS = '_prab_citations';

	/**
	 * About-peptides meta key; JSON array of peptide post IDs for schema `about`.
	 *
	 * @var string
	 */
	public const META_ABOUT_PEPTIDES = '_prab_about_peptides';

	/**
	 * Review mode meta key; value is 'human' or 'editorial-system'.
	 *
	 * @var string
	 */
	public const META_REVIEW_MODE = '_prab_review_mode';

	/**
	 * Reviewed-at meta key; ISO 8601 datetime of last review.
	 *
	 * @var string
	 */
	public const META_REVIEWED_AT = '_prab_reviewed_at';

	/**
	 * Reviewed-by meta key; WP user ID of the Review Queue approver.
	 *
	 * @var string
	 */
	public const META_REVIEWED_BY = '_prab_reviewed_by';

	/**
	 * The only schema version this reader currently supports.
	 *
	 * @var int
	 */
	private const SUPPORTED_VERSION = 1;

	/**
	 * Per-request cache for is_triggered(), keyed by post ID (avoids triple meta reads).
	 *
	 * @var array<int, bool>
	 */
	private array $triggered_cache = array();

	/**
	 * Return true when this post carries a supported _prab_schema_version meta.
	 *
	 * Cached per post ID: all three Yoast hooks call this for the same post,
	 * so caching avoids triple get_post_meta reads per request.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return bool
	 */
	public function is_triggered( int $post_id ): bool {
		if ( isset( $this->triggered_cache[ $post_id ] ) ) {
			return $this->triggered_cache[ $post_id ];
		}
		$raw = get_post_meta( $post_id, self::META_SCHEMA_VERSION, true );
		if ( '' === $raw || false === $raw ) {
			$this->triggered_cache[ $post_id ] = false;
			return false;
		}
		$version = (int) $raw;
		if ( self::SUPPORTED_VERSION === $version ) {
			$this->triggered_cache[ $post_id ] = true;
			return true;
		}
		error_log( sprintf( '[PR Core] Unsupported _prab_schema_version=%d on post %d. Supports v%d only. Emission suppressed.', $version, $post_id, self::SUPPORTED_VERSION ) );
		$this->triggered_cache[ $post_id ] = false;
		return false;
	}

	/**
	 * Read and validate _prab_citations meta.
	 *
	 * Each entry needs valid http/https `url` and non-empty `title`; others skipped.
	 * `quality_score` never emitted — no schema.org mapping (contract v1).
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<int, array{url: string, title: string, doi: string|null}> Validated.
	 */
	public function get_citations( int $post_id ): array {
		$decoded = $this->decode_json_meta( $post_id, self::META_CITATIONS );
		if ( empty( $decoded ) ) {
			return array();
		}
		$valid = array();
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$url   = $this->sanitize_url( (string) ( $item['url'] ?? '' ) );
			$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
			if ( '' === $url || '' === $title ) {
				continue;
			}
			$valid[] = array(
				'url'   => $url,
				'title' => $title,
				'doi'   => isset( $item['doi'] ) ? $this->sanitize_doi( (string) $item['doi'] ) : null,
			);
		}
		return $valid;
	}

	/**
	 * Read and validate _prab_about_peptides meta.
	 *
	 * Returns Drug stubs for schema `about`. Each ID must resolve to a published
	 * `peptide` post; unresolvable IDs and empty-permalink posts are skipped.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<int, array<string, string>> Drug stubs.
	 */
	public function get_about_peptides( int $post_id ): array {
		$decoded = $this->decode_json_meta( $post_id, self::META_ABOUT_PEPTIDES );
		if ( empty( $decoded ) ) {
			return array();
		}
		$stubs = array();
		foreach ( $decoded as $raw_id ) {
			if ( ! is_numeric( $raw_id ) ) {
				continue;
			}
			$pid  = absint( $raw_id );
			$post = $pid > 0 ? get_post( $pid ) : null;
			if ( ! $post instanceof WP_Post
				|| 'publish' !== $post->post_status
				|| PR_Core_Peptide_CPT::POST_TYPE !== $post->post_type ) {
				continue;
			}
			$link = (string) get_permalink( $pid );
			if ( '' === $link ) {
				continue;
			}
			$stubs[] = array(
				'@type' => 'Drug',
				'@id'   => $link . '#drug',
				'name'  => sanitize_text_field( $post->post_title ),
				'url'   => $link,
			);
		}
		return $stubs;
	}

	/**
	 * Read and validate _prab_review_mode meta.
	 *
	 * Unknown values are treated as 'editorial-system' (safe floor, contract v1).
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string 'human' | 'editorial-system'.
	 */
	public function get_review_mode( int $post_id ): string {
		$raw = sanitize_text_field( (string) get_post_meta( $post_id, self::META_REVIEW_MODE, true ) );
		if ( 'human' === $raw ) {
			return 'human';
		}
		if ( '' !== $raw && 'editorial-system' !== $raw ) {
			error_log( sprintf( '[PR Core] Unknown _prab_review_mode="%s" on post %d. Treating as editorial-system.', $raw, $post_id ) );
		}
		return 'editorial-system';
	}

	/**
	 * Read and validate _prab_reviewed_at meta.
	 *
	 * Returns null when absent, empty, or unparseable as a date.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string|null Sanitised datetime string, or null.
	 */
	public function get_reviewed_at( int $post_id ): ?string {
		$raw = sanitize_text_field( (string) get_post_meta( $post_id, self::META_REVIEWED_AT, true ) );
		if ( '' === $raw || false === strtotime( $raw ) ) {
			return null;
		}
		return $raw;
	}

	/**
	 * Resolve the honest reviewedBy value for a PRAB article.
	 *
	 * Person only when mode='human', user resolves, and display_name non-empty.
	 * All other cases → Organization. Never fabricated. Core contract guarantee.
	 *
	 * @param int    $post_id     WordPress post ID.
	 * @param string $review_mode Result of get_review_mode() for this post.
	 * @return array<string, string> Schema Person or Organization node.
	 */
	public function get_reviewed_by( int $post_id, string $review_mode ): array {
		if ( 'human' === $review_mode ) {
			$uid  = absint( get_post_meta( $post_id, self::META_REVIEWED_BY, true ) );
			$user = $uid > 0 ? get_userdata( $uid ) : false;
			if ( $user instanceof WP_User ) {
				$display = sanitize_text_field( $user->display_name );
				if ( '' !== $display ) {
					return array(
						'@type' => 'Person',
						'@id'   => home_url( '/#/schema/person/' . $uid ),
						'name'  => $display,
					);
				}
			}
			error_log( sprintf( '[PR Core] _prab_review_mode=human but reviewed_by user (ID=%d) unresolvable on post %d. Emitting Organization.', $uid, $post_id ) );
		}
		return array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => 'Peptide Repo',
			'url'   => home_url(),
		);
	}

	// ── Private helpers ──────────────────────────────────────────────────

	/**
	 * Decode a JSON array from post meta, returning empty array on any error.
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param string $meta_key Meta key to read.
	 * @return array<mixed> Decoded array, or empty array on failure.
	 */
	private function decode_json_meta( int $post_id, string $meta_key ): array {
		$raw = get_post_meta( $post_id, $meta_key, true );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Sanitize and validate a URL, requiring http or https scheme.
	 *
	 * @param string $raw Raw URL string.
	 * @return string Sanitised URL, or '' when invalid.
	 */
	private function sanitize_url( string $raw ): string {
		$url = esc_url_raw( trim( $raw ) );
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		$scheme = (string) wp_parse_url( $url, PHP_URL_SCHEME );
		return in_array( $scheme, array( 'http', 'https' ), true ) ? $url : '';
	}

	/**
	 * Sanitize a DOI, returning a https://doi.org/ URI or null.
	 *
	 * Accepts bare DOI ("10.1234/abc"), doi:-prefixed, or full URI forms.
	 *
	 * @param string $raw Raw DOI string.
	 * @return string|null DOI URI, or null if pattern does not match.
	 */
	private function sanitize_doi( string $raw ): ?string {
		$doi = trim( $raw );
		foreach ( array( 'https://doi.org/', 'http://doi.org/', 'doi:' ) as $pfx ) {
			if ( str_starts_with( strtolower( $doi ), strtolower( $pfx ) ) ) {
				$doi = substr( $doi, strlen( $pfx ) );
				break;
			}
		}
		return preg_match( '/^10\.\d{4,}\/\S+$/', $doi ) ? 'https://doi.org/' . $doi : null;
	}
}
