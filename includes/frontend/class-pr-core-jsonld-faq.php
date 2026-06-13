<?php
declare(strict_types=1);

/**
 * Builds the schema.org FAQPage graph piece for a peptide page.
 *
 * What: Reads _pr_faq_items post-meta (JSON array of {question, answer} objects)
 *       and produces a FAQPage node suitable for injection into Yoast's graph.
 *       Emits nothing when the meta is empty or absent.
 *
 * Who calls it: PR_Core_Jsonld::build_faq_piece() during graph assembly.
 * Dependencies: WordPress get_post_meta(), get_permalink().
 *
 * @see frontend/class-pr-core-jsonld.php       — Orchestrator that calls this builder.
 * @see cpt/class-pr-core-schema-sanitizers.php — sanitize_faq_items() used on save.
 * @see ARCHITECTURE.md                         — §2.7 JSON-LD output.
 */
class PR_Core_Jsonld_Faq {

	/** @var string Meta key holding the FAQ items JSON array. */
	public const META_FAQ_ITEMS = '_pr_faq_items';

	/**
	 * Build a FAQPage node for a post, or return null if no items exist.
	 *
	 * @param int    $post_id   WordPress post ID.
	 * @param string $permalink Canonical permalink for @id construction.
	 * @return array<string, mixed>|null FAQPage schema node, or null when no items.
	 */
	public function build( int $post_id, string $permalink ): ?array {
		$items = $this->get_validated_items( $post_id );

		if ( empty( $items ) ) {
			return null;
		}

		return [
			'@type'      => 'FAQPage',
			'@id'        => $permalink . '#faq',
			'mainEntity' => array_map( [ $this, 'build_question' ], $items ),
		];
	}

	/**
	 * Build a single Question node from a FAQ item array.
	 *
	 * @param array{question: string, answer: string} $item Sanitized FAQ item.
	 * @return array<string, mixed> Schema.org Question node.
	 */
	private function build_question( array $item ): array {
		return [
			'@type' => 'Question',
			'name'  => esc_html( $item['question'] ),
			'acceptedAnswer' => [
				'@type' => 'Answer',
				'text'  => esc_html( $item['answer'] ),
			],
		];
	}

	/**
	 * Read and validate _pr_faq_items meta, returning only well-formed items.
	 *
	 * Returns empty array when meta is absent, empty, or malformed.
	 * Does not trust stored values — re-validates each item at read time.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<int, array{question: string, answer: string}> Validated items.
	 */
	private function get_validated_items( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META_FAQ_ITEMS, true );

		if ( ! is_string( $raw ) || '' === $raw ) {
			return [];
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return [];
		}

		$valid = [];
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = sanitize_text_field( (string) ( $item['question'] ?? '' ) );
			$answer   = sanitize_textarea_field( (string) ( $item['answer'] ?? '' ) );
			if ( '' !== $question && '' !== $answer ) {
				$valid[] = [
					'question' => $question,
					'answer'   => $answer,
				];
			}
		}

		return $valid;
	}
}
