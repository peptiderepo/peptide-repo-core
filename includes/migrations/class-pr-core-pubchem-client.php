<?php
declare(strict_types=1);

/**
 * Lightweight PubChem REST client for the backfill migration.
 *
 * What: Fetches molecular formula, molecular weight, and synonyms from the
 *       PubChem REST API for a given compound CID or name. Used exclusively
 *       by migration 0004 for the 4 peptide posts that lack PSA-sourced data.
 *
 * Who calls it: PR_Core_Migration_0004_Backfill_Peptide_Meta::fetch_pubchem().
 * Dependencies: WordPress wp_remote_get() when available; falls back to
 *               file_get_contents with stream context (WP-CLI context).
 *
 * All values from PubChem are returned unsanitized — callers must sanitize
 * before writing to post_meta.
 *
 * @see migrations/class-pr-core-migration-0004-backfill-peptide-meta.php — Caller.
 * @see ARCHITECTURE.md — §v0.6.0 schema sprint.
 */
class PR_Core_Pubchem_Client {

	/** @var string PubChem REST base URL. */
	private const BASE = 'https://pubchem.ncbi.nlm.nih.gov/rest/pug';

	/** @var int HTTP timeout per request (seconds). */
	private const TIMEOUT = 8;

	/** @var int Maximum retry attempts with exponential backoff. */
	private const MAX_RETRIES = 3;

	/** @var int Maximum synonyms to return (keeps post-meta size reasonable). */
	private const MAX_SYNONYMS = 10;

	/**
	 * Fetch properties and synonyms by PubChem CID.
	 *
	 * @param string $cid Numeric PubChem CID string.
	 * @return array{formula: string, weight: float, synonyms: string[]}|null Null on failure.
	 */
	public function fetch_by_cid( string $cid ): ?array {
		if ( '' === $cid || ! ctype_digit( $cid ) ) {
			return null;
		}

		$props = $this->fetch_properties( 'cid', $cid );
		if ( null === $props ) {
			return null;
		}

		$props['synonyms'] = $this->fetch_synonyms( 'cid', $cid );
		return $props;
	}

	/**
	 * Fetch properties and synonyms by compound name.
	 *
	 * @param string $name Compound name (e.g., peptide post title).
	 * @return array{formula: string, weight: float, synonyms: string[]}|null Null on failure.
	 */
	public function fetch_by_name( string $name ): ?array {
		if ( '' === trim( $name ) ) {
			return null;
		}

		$props = $this->fetch_properties( 'name', $name );
		if ( null === $props ) {
			return null;
		}

		// If a CID was returned in the property response, use it for synonyms (more reliable).
		$cid  = $props['cid'] ?? '';
		$type = ( '' !== $cid ) ? 'cid' : 'name';
		$ref  = ( '' !== $cid ) ? $cid : $name;

		$props['synonyms'] = $this->fetch_synonyms( $type, $ref );
		unset( $props['cid'] );
		return $props;
	}

	/**
	 * Fetch MolecularFormula and MolecularWeight from PubChem.
	 *
	 * @param string $lookup_type 'cid' or 'name'.
	 * @param string $identifier  CID number or compound name.
	 * @return array{formula: string, weight: float, cid: string}|null
	 */
	private function fetch_properties( string $lookup_type, string $identifier ): ?array {
		$url  = self::BASE . '/compound/' . $lookup_type . '/' . rawurlencode( $identifier )
			. '/property/MolecularFormula,MolecularWeight/JSON';
		$body = $this->get_with_retry( $url );

		if ( null === $body ) {
			return null;
		}

		$data  = json_decode( $body, true );
		$props = $data['PropertyTable']['Properties'][0] ?? null;

		if ( ! is_array( $props ) ) {
			return null;
		}

		$formula = (string) ( $props['MolecularFormula'] ?? '' );
		$weight  = (float) ( $props['MolecularWeight'] ?? 0 );
		$cid     = (string) ( $props['CID'] ?? '' );

		if ( '' === $formula || $weight <= 0.0 ) {
			return null;
		}

		return [
			'formula' => $formula,
			'weight'  => $weight,
			'cid'     => $cid,
		];
	}

	/**
	 * Fetch synonyms for a compound, limited to MAX_SYNONYMS.
	 *
	 * Returns an empty array on failure — callers treat this gracefully.
	 *
	 * @param string $lookup_type 'cid' or 'name'.
	 * @param string $identifier  CID or name value.
	 * @return string[] Raw synonym strings (unsanitized).
	 */
	private function fetch_synonyms( string $lookup_type, string $identifier ): array {
		$url  = self::BASE . '/compound/' . $lookup_type . '/' . rawurlencode( $identifier ) . '/synonyms/JSON';
		$body = $this->get_with_retry( $url );

		if ( null === $body ) {
			return [];
		}

		$data     = json_decode( $body, true );
		$synonyms = $data['InformationList']['Information'][0]['Synonym'] ?? [];

		if ( ! is_array( $synonyms ) ) {
			return [];
		}

		return array_slice( $synonyms, 0, self::MAX_SYNONYMS );
	}

	/**
	 * GET request with exponential-backoff retry (up to MAX_RETRIES).
	 *
	 * @param string $url URL to fetch.
	 * @return string|null Response body on HTTP 200, null after all retries fail.
	 */
	private function get_with_retry( string $url ): ?string {
		for ( $attempt = 0; $attempt < self::MAX_RETRIES; $attempt++ ) {
			if ( $attempt > 0 ) {
				sleep( (int) pow( 2, $attempt - 1 ) ); // 1s, 2s.
			}

			$body = $this->get_once( $url );
			if ( null !== $body ) {
				return $body;
			}
		}

		error_log( '[PR Core PubChem] Request failed after ' . self::MAX_RETRIES . ' attempts: ' . $url );
		return null;
	}

	/**
	 * Single HTTP GET attempt.
	 *
	 * Uses wp_remote_get() when available; falls back to file_get_contents
	 * for WP-CLI contexts where the HTTP API may not be bootstrapped.
	 *
	 * @param string $url URL to fetch.
	 * @return string|null Body on HTTP 200, null on error or non-200.
	 */
	private function get_once( string $url ): ?string {
		if ( function_exists( 'wp_remote_get' ) ) {
			$response = wp_remote_get( $url, [
				'timeout' => self::TIMEOUT,
				'headers' => [ 'Accept' => 'application/json' ],
			] );

			if ( is_wp_error( $response ) ) {
				return null;
			}

			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return null;
			}

			$body = wp_remote_retrieve_body( $response );
			return is_string( $body ) ? $body : null;
		}

		// WP-CLI / plain PHP fallback.
		$context = stream_context_create( [
			'http' => [
				'timeout' => self::TIMEOUT,
				'header'  => "Accept: application/json\r\n",
				'method'  => 'GET',
			],
		] );

		$body = @file_get_contents( $url, false, $context );
		return ( false === $body ) ? null : $body;
	}
}
