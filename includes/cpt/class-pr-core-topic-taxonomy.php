<?php
declare(strict_types=1);

/**
 * Registers the peptide_topic taxonomy for linking blog posts to peptides.
 *
 * What: Defines the `peptide_topic` taxonomy attached to the post CPT.
 *       Non-hierarchical, uses peptide slugs as terms (e.g., 'bpc-157', 'tb-500').
 *       Enables the Related Articles feature for blog posts tagged with a peptide topic.
 * Who calls it: PR_Core::init() on plugins_loaded.
 * Dependencies: None.
 *
 * Ownership: As of v0.3.0, PR Core owns `peptide_topic` taxonomy.
 * Registration is guarded by `taxonomy_exists()` so deploy order does not matter.
 *
 * @see ARCHITECTURE.md — Taxonomy specification.
 * @see CONVENTIONS.md — Taxonomy ownership rule.
 */
class PR_Core_Topic_Taxonomy {

	/** @var string Taxonomy: topic (links blog posts to peptides by slug). */
	public const TAX_TOPIC = 'peptide_topic';

	/**
	 * Register WordPress hooks for taxonomy registration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', [ __CLASS__, 'register_topic_taxonomy' ] );
	}

	/**
	 * Register the `peptide_topic` taxonomy.
	 *
	 * Guarded with `taxonomy_exists()`: if another plugin has already
	 * registered the `peptide_topic` taxonomy, this call no-ops.
	 *
	 * Side effects: registers taxonomy with WordPress.
	 *
	 * @return void
	 */
	public static function register_topic_taxonomy(): void {
		if ( taxonomy_exists( self::TAX_TOPIC ) ) {
			return;
		}

		register_taxonomy( self::TAX_TOPIC, 'post', [
			'labels'             => [
				'name'          => __( 'Peptide Topics', 'peptide-repo-core' ),
				'singular_name' => __( 'Peptide Topic', 'peptide-repo-core' ),
			],
			'public'             => true,
			'publicly_queryable' => true,
			'show_in_rest'       => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'hierarchical'       => false,
			'rewrite'            => [ 'slug' => 'peptide-topic', 'with_front' => false ],
		] );
	}
}
