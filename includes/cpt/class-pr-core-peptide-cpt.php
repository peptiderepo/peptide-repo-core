<?php
/**
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Defines peptide CPT with REST support, archive, and meta fields
 *
 * @package Peptide_Repo_Core
 */

/**
 * Registers the peptide custom post type and associated taxonomy.
 *
 * What: Defines peptide CPT with REST support, archive, and meta fields.
 * Who calls it: PR_Core::init() on plugins_loaded.
 * Dependencies: None.
 *
 * Ownership: As of v0.2.0, PR Core owns the `peptide` CPT and `peptide_category` taxonomy
 * (previously PSA v4.5.0). Registration guarded by post_type_exists/taxonomy_exists
 * so deploy order does not matter.
 *
 * @see ARCHITECTURE.md
 * @see CONVENTIONS.md
 */
class PR_Core_Peptide_CPT {

	/**
	 * Post type slug (owned by PR Core since v0.2.0; PSA previously registered this).
	 *
	 * @var string Post type slug (owned by PR Core since v0.2.0; PSA previously registered this).
	 */
	public const POST_TYPE = 'peptide';

	/**
	 * Taxonomy: category (e.g., GLP-1 agonist).
	 *
	 * @var string Taxonomy: category (e.g., GLP-1 agonist).
	 */
	public const TAX_CATEGORY = 'peptide_category';

	/**
	 * Capability required for editing peptide data.
	 *
	 * @var string Capability required for editing peptide data.
	 */
	public const CAPABILITY = 'manage_peptide_content';

	/**
	 * Evidence strength enum values, ordered weakest to strongest.
	 *
	 * @var string[]
	 */
	public const EVIDENCE_STRENGTHS = array(
		'preclinical',
		'case-series',
		'observational',
		'rct-small',
		'rct-large',
		'meta-analysis',
	);

	/**
	 * Editorial review status enum values.
	 *
	 * @var string[]
	 */
	public const REVIEW_STATUSES = array(
		'draft',
		'in-review',
		'published',
		'retired',
	);

	/**
	 * Post-meta field definitions (1:1 with peptide).
	 * Key => [ 'type', 'default', 'sanitize_callback', 'show_in_rest' ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_meta_fields(): array {
		return PR_Core_Peptide_Meta_Schema::get_fields();
	}

	/**
	 * Register WordPress hooks for CPT and taxonomy registration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'register_peptide_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( __CLASS__, 'register_meta_fields' ) );
	}

	/**
	 * Register the `peptide` CPT. Guarded with post_type_exists() so deploy order is irrelevant.
	 *
	 * Side effects: registers CPT with WordPress.
	 *
	 * @return void
	 */
	public static function register_peptide_post_type(): void {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		$labels = array(
			'name'               => __( 'Peptides', 'peptide-repo-core' ),
			'singular_name'      => __( 'Peptide', 'peptide-repo-core' ),
			'add_new_item'       => __( 'Add New Peptide', 'peptide-repo-core' ),
			'edit_item'          => __( 'Edit Peptide', 'peptide-repo-core' ),
			'new_item'           => __( 'New Peptide', 'peptide-repo-core' ),
			'view_item'          => __( 'View Peptide', 'peptide-repo-core' ),
			'search_items'       => __( 'Search Peptides', 'peptide-repo-core' ),
			'not_found'          => __( 'No peptides found', 'peptide-repo-core' ),
			'not_found_in_trash' => __( 'No peptides found in trash', 'peptide-repo-core' ),
			'all_items'          => __( 'All Peptides', 'peptide-repo-core' ),
			'menu_name'          => __( 'Peptides', 'peptide-repo-core' ),
		);

		// Args harmonized superset of PR Core + PSA for 89 existing posts.
		// Supports union, capability/role perms preserved, slugs unchanged.
		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'peptides',
			// Note: 'rest_namespace' is deliberately omitted. Custom namespaces prevent.
			// Gutenberg's block editor from loading posts for editing (it fetches the.
			// hardcoded wp/v2 REST route). WordPress defaults to wp/v2 — appropriate for.
			// this CPT — and keeps the REST endpoint at /wp-json/wp/v2/peptides/.
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-database',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'has_archive'        => true,
			'rewrite'            => array(
				'slug'       => 'peptides',
				'with_front' => false,
			),
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ),
		);
		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the `peptide_category` taxonomy. Guarded with taxonomy_exists().
	 *
	 * Side effects: registers taxonomy with WordPress.
	 *
	 * @return void
	 */
	public static function register_taxonomies(): void {
		if ( taxonomy_exists( self::TAX_CATEGORY ) ) {
			return;
		}

		register_taxonomy(
			self::TAX_CATEGORY,
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Peptide Categories', 'peptide-repo-core' ),
					'singular_name' => __( 'Peptide Category', 'peptide-repo-core' ),
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'hierarchical'       => true,
				'rewrite'            => array(
					'slug'       => 'peptide-category',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register post-meta fields. Auth gated on manage_peptide_content.
	 *
	 * @return void
	 */
	public static function register_meta_fields(): void {
		foreach ( self::get_meta_fields() as $key => $config ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => $config['type'],
					'single'            => true,
					'default'           => $config['default'],
					'show_in_rest'      => true,
					'sanitize_callback' => $config['sanitize'],
					'auth_callback'     => static function () {
						return current_user_can( self::CAPABILITY );
					},
				)
			);
		}
	}

	/**
	 * Sanitize evidence_strength to allowed enum values.
	 *
	 * @param mixed $value Raw input value.
	 * @return string
	 */
	public static function sanitize_evidence_strength( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, self::EVIDENCE_STRENGTHS, true ) ? $value : 'preclinical';
	}

	/**
	 * Sanitize editorial_review_status to allowed enum values.
	 *
	 * @param mixed $value Raw input value.
	 * @return string
	 */
	public static function sanitize_review_status( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, self::REVIEW_STATUSES, true ) ? $value : 'draft';
	}
}
