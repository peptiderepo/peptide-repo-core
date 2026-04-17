<?php
declare(strict_types=1);

/**
 * Registers the pr_peptide custom post type and associated taxonomies.
 *
 * What: Defines the peptide CPT with REST support, archive, and monograph meta fields.
 * Who calls it: PR_Core::init() on plugins_loaded.
 * Dependencies: None.
 *
 * @see ARCHITECTURE.md — CPT specification and post-meta field definitions.
 * @see cpt/class-pr-core-peptide-taxonomies.php — Not used; taxonomies registered here for simplicity.
 */
class PR_Core_Peptide_CPT {

	/** @var string Post type slug. */
	public const POST_TYPE = 'pr_peptide';

	/** @var string Taxonomy: category (e.g., GLP-1 agonist). */
	public const TAX_CATEGORY = 'pr_peptide_category';

	/** @var string Taxonomy: family grouping. */
	public const TAX_FAMILY = 'pr_peptide_family';

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
	 * Register the pr_peptide custom post type.
	 *
	 * Side effects: registers CPT with WordPress.
	 *
	 * @return void
	 */
	public static function register_peptide_post_type(): void {
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

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'rewrite'            => [ 'slug' => 'peptides', 'with_front' => false ],
			'show_in_rest'       => true,
			'rest_base'          => 'peptides',
			'rest_namespace'     => 'pr-core/v1',
			'supports'           => [ 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ],
			'menu_icon'          => 'dashicons-database',
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'show_in_menu'       => true,
			'menu_position'      => 25,
		];

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Register the pr_peptide_category and pr_peptide_family taxonomies.
	 *
	 * Side effects: registers taxonomies with WordPress.
	 *
	 * @return void
	 */
	public static function register_taxonomies(): void {
		register_taxonomy( self::TAX_CATEGORY, self::POST_TYPE, [
			'labels'            => [
				'name'          => __( 'Peptide Categories', 'peptide-repo-core' ),
				'singular_name' => __( 'Peptide Category', 'peptide-repo-core' ),
			],
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'peptide-category' ],
		] );

		register_taxonomy( self::TAX_FAMILY, self::POST_TYPE, [
			'labels'            => [
				'name'          => __( 'Peptide Families', 'peptide-repo-core' ),
				'singular_name' => __( 'Peptide Family', 'peptide-repo-core' ),
			],
			'hierarchical'      => false,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => [ 'slug' => 'peptide-family' ],
		] );
	}

	/**
	 * Register post-meta fields with the REST API.
	 *
	 * Side effects: registers meta with WordPress.
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

	/**
	 * Sanitize a JSON array string (e.g., aliases field).
	 *
	 * @param mixed $value Raw input.
	 * @return string Valid JSON array string.
	 */
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

	/**
	 * Sanitize evidence_strength to allowed enum values.
	 *
	 * @param mixed $value Raw input.
	 * @return string Valid enum value.
	 */
	public static function sanitize_evidence_strength( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, self::EVIDENCE_STRENGTHS, true ) ? $value : 'preclinical';
	}

	/**
	 * Sanitize editorial_review_status to allowed enum values.
	 *
	 * @param mixed $value Raw input.
	 * @return string Valid enum value.
	 */
	public static function sanitize_review_status( $value ): string {
		$value = sanitize_text_field( (string) $value );
		return in_array( $value, self::REVIEW_STATUSES, true ) ? $value : 'draft';
	}
}
