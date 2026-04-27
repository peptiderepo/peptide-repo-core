<?php
declare(strict_types=1);

/**
 * Registers the repo_daily_category taxonomy for Repo Daily articles.
 *
 * What: Defines the `repo_daily_category` taxonomy attached to repo_daily CPT.
 *       Seeds four content categories on activation: article, guide, comparison, news.
 * Who calls it: PR_Core::init() on plugins_loaded; activation hook seeding.
 * Dependencies: None.
 *
 * Non-hierarchical taxonomy with REST support. Used by editorial and publishing
 * workflows to categorize Repo Daily content.
 *
 * @see ARCHITECTURE.md — Content type taxonomy specification.
 * @see CONVENTIONS.md — Taxonomy ownership pattern.
 */
class PR_Core_Repo_Daily_Taxonomy {

	/** @var string Taxonomy: repo_daily_category. */
	public const TAX_CATEGORY = 'repo_daily_category';

	/**
	 * Register WordPress hooks for taxonomy and seeding.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', [ __CLASS__, 'register_taxonomy' ] );
	}

	/**
	 * Register the `repo_daily_category` taxonomy.
	 *
	 * Side effects: registers taxonomy with WordPress.
	 *
	 * @return void
	 */
	public static function register_taxonomy(): void {
		if ( taxonomy_exists( self::TAX_CATEGORY ) ) {
			return;
		}

		register_taxonomy( self::TAX_CATEGORY, PR_Core_Repo_Daily_CPT::POST_TYPE, [
			'labels'             => [
				'name'          => __( 'Category', 'peptide-repo-core' ),
				'singular_name' => __( 'Category', 'peptide-repo-core' ),
			],
			'public'             => true,
			'publicly_queryable' => true,
			'show_in_rest'       => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'hierarchical'       => false,
			'rewrite'            => [ 'slug' => 'daily/category', 'with_front' => false ],
		] );
	}

	/**
	 * Seed default category terms on activation.
	 *
	 * Idempotent: checks for existence of each term before inserting.
	 * Called by PR_Core_Activator during plugin activation.
	 *
	 * Side effects: may insert terms into wp_terms and wp_term_taxonomy.
	 *
	 * @return void
	 */
	public static function seed_terms(): void {
		$terms = [ 'article', 'guide', 'comparison', 'news' ];

		foreach ( $terms as $term ) {
			if ( ! term_exists( $term, self::TAX_CATEGORY ) ) {
				wp_insert_term( $term, self::TAX_CATEGORY );
			}
		}
	}
}
