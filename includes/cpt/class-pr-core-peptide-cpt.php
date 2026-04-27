<?php
declare(strict_types=1);

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

	/** @var string Post type slug (owned by PR Core since v0.2.0; PSA previously registered this). */
	public const POST_TYPE = 'peptide';

	/** @var string Taxonomy: category (e.g., GLP-1 agonist). */
	public const TAX_CATEGORY = 'peptide_category';

	/** @var string Capability required for editing peptide data. */
	public const CAPABILITY = 'manage_peptide_content';

	/**
	 * Evidence strength enum values, ordered weakest to strongest.
	 *
	 * @var string[]
	 */
	public const EVIDENCE_STRENGTHS = [
		'preclinical',
		'case-series',
		'observational',
		'rct-small',
		'rct-large',
		'meta-analysis',
	];

	/**
	 * Editorial review status enum values.
	 *
	 * @var string[]
	 */
	public const REVIEW_STATUSES = [
		'draft',
		'in-review',
		'published',
		'retired',
	];

	/**
	 * Post-meta field definitions (1:1 with peptide).
	 * Key => [ 'type', 'default', 'sanitize_callback', 'show_in_rest' ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_meta_fields(): array {
		return [
			'display_name'            => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'aliases'                 => [
				'type'     => 'string',
				'default'  => '[]',
				'sanitize' => [ __CLASS__, 'sanitize_json_array' ],
			],
			'molecular_formula'       => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'molecular_weight'        => [
				'type'     => 'number',
				'default'  => 0,
				'sanitize' => 'floatval',
			],
			'cas_number'              => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'drugbank_id'             => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'chembl_id'               => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'evidence_strength'       => [
				'type'     => 'string',
				'default'  => 'preclinical',
				'sanitize' => [ __CLASS__, 'sanitize_evidence_strength' ],
			],
			'editorial_review_status' => [
				'type'     => 'string',
				'default'  => 'draft',
				'sanitize' => [ __CLASS__, 'sanitize_review_status' ],
			],
			'last_editorial_review_at' => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'medical_editor_id'       => [
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			],
			'_pr_last_source_verified' => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'_pr_last_reviewed'        => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'_pr_next_review_by'       => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			],
			'_pr_verification_velocity' => [
				'type'     => 'string',
				'default'  => 'medium',
				'sanitize' => [ PR_Core_Verification_Sanitizers::class, 'sanitize_velocity' ],
			],
			'_pr_verification_notes'    => [
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_textarea_field',
			],
			'_pr_verification_status'   => [
				'type'     => 'string',
				'default'  => 'current',
				'sanitize' => [ PR_Core_Verification_Sanitizers::class, 'sanitize_status' ],
			],
		];
	}

	/**
	 * Register WordPress hooks for CPT and taxonomy registration.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', [ __CLASS__, 'register_peptide_post_type' ] );
		add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );
		add_action( 'init', [ __CLASS__, 'register_meta_fields' ] );
	}

	/**
	 * Register the `peptide` custom post type.
	 *
	 * Guarded with `post_type_exists()`: if another plugin (historically PSA)
	 * has already registered the `peptide` post type, this call no-ops. This
	 * makes deploy order between PR Core and PSA irrelevant during the
	 * PSA v4.5.0 consolidation transition.
	 *
	 * Side effects: registers CPT with WordPress.
	 *
	 * @return void
	 */
	public static function register_peptide_post_type(): void {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		$labels = [
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
		];

		// Args harmonized superset of PR Core + PSA for 89 existing posts.
		// Supports union, capability/role perms preserved, slugs unchanged.
		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => true,
			'rest_base'          => 'peptides',
			// Note: 'rest_namespace' is deliberately omitted. Custom namespaces prevent
			// Gutenberg's block editor from loading posts for editing (it fetches the
			// hardcoded wp/v2 REST route). WordPress defaults to wp/v2 — appropriate for
			// this CPT — and keeps the REST endpoint at /wp-json/wp/v2/peptides/.
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-database',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'has_archive'        => true,
			'rewrite'            => [ 'slug' => 'peptides', 'with_front' => false ],
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ],
		];
		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the `peptide_category` taxonomy.
	 *
	 * Guarded with `taxonomy_exists()` for the same reason CPT registration
	 * is guarded. The 8 existing terms + term_relationships stay intact —
	 * they key on taxonomy name `peptide_category` in wp_term_taxonomy,
	 * which is exactly what we register here.
	 *
	 * v0.2.0: `pr_peptide_family` taxonomy removed — never populated, never
	 * surfaced in UI.
	 *
	 * Side effects: registers taxonomy with WordPress.
	 *
	 * @return void
	 */
	public static function register_taxonomies(): void {
		if ( taxonomy_exists( self::TAX_CATEGORY ) ) {
			return;
		}

		register_taxonomy( self::TAX_CATEGORY, self::POST_TYPE, [
			'labels'             => [
				'name'          => __( 'Peptide Categories', 'peptide-repo-core' ),
				'singular_name' => __( 'Peptide Category', 'peptide-repo-core' ),
			],
			'public'             => true,
			'publicly_queryable' => true,
			'show_in_rest'       => true,
			'show_ui'            => true,
			'show_admin_column'  => true,
			'hierarchical'       => true,
			'rewrite'            => [ 'slug' => 'peptide-category', 'with_front' => false ],
		] );
	}

	/**
	 * Register post-meta fields. Auth gated on manage_peptide_content.
	 *
	 * @return void
	 */
	public static function register_meta_fields(): void {
		foreach ( self::get_meta_fields() as $key => $config ) {
			register_post_meta( self::POST_TYPE, $key, [
				'type'              => $config['type'],
				'single'            => true,
				'default'           => $config['default'],
				'show_in_rest'      => true,
				'sanitize_callback' => $config['sanitize'],
				'auth_callback'     => static function () {
					return current_user_can( self::CAPABILITY );
				},
			] );
		}
	}

	/** Sanitize a JSON array string (e.g., aliases field). */
	public static function sanitize_json_array( $value ): string {
		if ( is_array( $value ) ) {
			$value = wp_json_encode( array_map( 'sanitize_text_field', $value ) );
		}

		$decoded = json_decode( (string) $value, true );
		if ( ! is_array( $decoded ) ) {
			return '[]';
		}

		return wp_json_encode( array_values( array_map( 'sanitize_text_field', $decoded ) ) );
	}

	/** Sanitize evidence_strength to allowed enum values. */
	public static function sanitize_evidence_strength( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, self::EVIDENCE_STRENGTHS, true ) ? $value : 'preclinical';
	}

	/** Sanitize editorial_review_status to allowed enum values. */
	public static function sanitize_review_status( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, self::REVIEW_STATUSES, true ) ? $value : 'draft';
	}
}
