<?php
declare(strict_types=1);

/**
 * REST API controller for peptide data (read-only public, write requires capability).
 *
 * What: Exposes peptides, dosing rows, and legal cells via REST endpoints.
 * Who calls it: PR_Core::init() registers rest_api_init hook.
 * Dependencies: PR_Core_Peptide_Repository, PR_Core_Dosing_Repository,
 *               PR_Core_Legal_Repository.
 *
 * Namespace: pr-core/v1
 * Endpoints:
 *   GET  /peptides                          — List peptides (public)
 *   GET  /peptides/{id}                     — Single peptide (public)
 *   GET  /peptides/{id}/dosing              — Dosing rows for peptide (public)
 *   GET  /peptides/{id}/legal               — Legal cells for peptide (public)
 *   POST /peptides/{id}/dosing              — Add dosing row (auth)
 *   POST /peptides/{id}/legal               — Add legal cell (auth)
 *
 * @see ARCHITECTURE.md — REST API specification.
 */
class PR_Core_Rest_Controller {

	/** @var string REST namespace. */
	private const NAMESPACE = 'pr-core/v1';

	/**
	 * Register the rest_api_init hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register all REST routes.
	 *
	 * Side effects: registers routes with WordPress REST API.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		// GET /peptides
		register_rest_route( self::NAMESPACE, '/peptides', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_peptides' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'category'          => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'evidence_strength' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'search'            => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'per_page'          => [ 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ],
				'page'              => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			],
		] );

		// GET /peptides/{id}
		register_rest_route( self::NAMESPACE, '/peptides/(?P<id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_peptide' ],
			'permission_callback' => '__return_true',
		] );

		// GET /peptides/{id}/dosing
		register_rest_route( self::NAMESPACE, '/peptides/(?P<id>\d+)/dosing', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_dosing' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'route'      => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
				'population' => [ 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ],
			],
		] );

		// POST /peptides/{id}/dosing (auth required)
		register_rest_route( self::NAMESPACE, '/peptides/(?P<id>\d+)/dosing', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_dosing' ],
			'permission_callback' => [ $this, 'check_write_permission' ],
		] );

		// GET /peptides/{id}/legal
		register_rest_route( self::NAMESPACE, '/peptides/(?P<id>\d+)/legal', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_legal' ],
			'permission_callback' => '__return_true',
		] );

		// POST /peptides/{id}/legal (auth required)
		register_rest_route( self::NAMESPACE, '/peptides/(?P<id>\d+)/legal', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_legal' ],
			'permission_callback' => [ $this, 'check_write_permission' ],
		] );
	}

	/**
	 * List peptides with optional filters.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_peptides( \WP_REST_Request $request ): \WP_REST_Response {
		$repo = new PR_Core_Peptide_Repository();

		$search = $request->get_param( 'search' );
		if ( $search ) {
			$peptides = $repo->search( $search, (int) $request->get_param( 'per_page' ) );
		} else {
			$peptides = $repo->find_all( [
				'category'          => $request->get_param( 'category' ),
				'evidence_strength' => $request->get_param( 'evidence_strength' ),
				'per_page'          => $request->get_param( 'per_page' ),
				'page'              => $request->get_param( 'page' ),
			] );
		}

		$data = array_map( static fn( PR_Core_Peptide_DTO $p ) => $p->to_array(), $peptides );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get a single peptide by ID.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_peptide( \WP_REST_Request $request ) {
		$repo    = new PR_Core_Peptide_Repository();
		$peptide = $repo->find_by_id( (int) $request->get_param( 'id' ) );

		if ( ! $peptide ) {
			return new \WP_Error( 'not_found', __( 'Peptide not found.', 'peptide-repo-core' ), [ 'status' => 404 ] );
		}

		return new \WP_REST_Response( $peptide->to_array(), 200 );
	}

	/**
	 * List dosing rows for a peptide.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_dosing( \WP_REST_Request $request ): \WP_REST_Response {
		$repo = new PR_Core_Dosing_Repository();
		$rows = $repo->find_by_peptide(
			(int) $request->get_param( 'id' ),
			array_filter( [
				'route'      => $request->get_param( 'route' ),
				'population' => $request->get_param( 'population' ),
			] )
		);

		$data = array_map( static fn( PR_Core_Dosing_Row_DTO $r ) => $r->to_array(), $rows );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Create a new dosing row for a peptide.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_dosing( \WP_REST_Request $request ) {
		$repo = new PR_Core_Dosing_Repository();

		$data               = $request->get_json_params();
		$data['peptide_id'] = (int) $request->get_param( 'id' );
		$data['added_by']   = get_current_user_id();

		$id = $repo->insert( $data );

		if ( 0 === $id ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to insert dosing row.', 'peptide-repo-core' ), [ 'status' => 500 ] );
		}

		$row = $repo->find_by_id( $id );

		return new \WP_REST_Response( $row ? $row->to_array() : [ 'id' => $id ], 201 );
	}

	/**
	 * List legal cells for a peptide.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_legal( \WP_REST_Request $request ): \WP_REST_Response {
		$repo  = new PR_Core_Legal_Repository();
		$cells = $repo->find_by_peptide( (int) $request->get_param( 'id' ) );

		$data = array_map( static fn( PR_Core_Legal_Cell_DTO $c ) => $c->to_array(), $cells );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Create a new legal cell for a peptide.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_legal( \WP_REST_Request $request ) {
		$repo = new PR_Core_Legal_Repository();

		$data               = $request->get_json_params();
		$data['peptide_id'] = (int) $request->get_param( 'id' );
		$data['reviewer_id'] = get_current_user_id();

		$id = $repo->insert( $data );

		if ( 0 === $id ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to insert legal cell.', 'peptide-repo-core' ), [ 'status' => 500 ] );
		}

		$cell = $repo->find_by_id( $id );

		return new \WP_REST_Response( $cell ? $cell->to_array() : [ 'id' => $id ], 201 );
	}

	/**
	 * Permission check: user must have manage_peptide_content capability.
	 *
	 * @return bool
	 */
	public function check_write_permission(): bool {
		return current_user_can( PR_Core_Peptide_CPT::CAPABILITY );
	}
}
