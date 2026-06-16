<?php
/**
 * Unit tests for PR_Core_Peptide_CPT (v0.2.0 CPT consolidation).
 *
 * Ported from tests/unit/test-peptide-cpt.php.
 *
 * Coverage:
 *   - Constants match the v0.2.0 consolidated contract.
 *   - TAX_FAMILY constant is removed.
 *   - register_peptide_post_type() guard: no-op when post_type_exists.
 *   - register_peptide_post_type() payload: args match contract.
 *   - register_taxonomies() guard: no-op when taxonomy_exists.
 *   - register_taxonomies() only registers peptide_category.
 *   - register_hooks() adds action on 'init'.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for PR_Core_Peptide_CPT.
 */
class PeptideCptTest extends TestCase {

	/**
	 * Reset global WP state before each test.
	 */
	protected function setUp(): void {
		$GLOBALS['pr_core_test_state'] = [
			'existing_post_types'   => [],
			'existing_taxonomies'   => [],
			'registered_post_types' => [],
			'registered_taxonomies' => [],
			'registered_meta'       => [],
			'added_actions'         => [],
			'added_filters'         => [],
		];
		// Ensure CPT class is loaded.
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
	}

	// ── Constants ─────────────────────────────────────────────────────.

	public function test_post_type_constant(): void {
		$this->assertSame( 'peptide', PR_Core_Peptide_CPT::POST_TYPE );
	}

	public function test_tax_category_constant(): void {
		$this->assertSame( 'peptide_category', PR_Core_Peptide_CPT::TAX_CATEGORY );
	}

	public function test_capability_constant(): void {
		$this->assertSame( 'manage_peptide_content', PR_Core_Peptide_CPT::CAPABILITY );
	}

	public function test_tax_family_constant_removed(): void {
		$reflection = new ReflectionClass( PR_Core_Peptide_CPT::class );
		$this->assertFalse(
			$reflection->hasConstant( 'TAX_FAMILY' ),
			'TAX_FAMILY constant should have been removed in v0.2.0'
		);
	}

	// ── CPT registration guard ────────────────────────────────────────.

	public function test_register_post_type_noop_when_already_exists(): void {
		$GLOBALS['pr_core_test_state']['existing_post_types'] = [ 'peptide' ];
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$this->assertEmpty(
			$GLOBALS['pr_core_test_state']['registered_post_types'],
			'register_peptide_post_type() must no-op when post_type_exists("peptide") is true'
		);
	}

	// ── CPT registration payload ──────────────────────────────────────.

	public function test_register_post_type_registers_peptide_slug(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$this->assertArrayHasKey(
			'peptide',
			$GLOBALS['pr_core_test_state']['registered_post_types']
		);
	}

	public function test_register_post_type_args_public(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertTrue( $args['public'] ?? null );
	}

	public function test_register_post_type_args_publicly_queryable(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertTrue( $args['publicly_queryable'] ?? null );
	}

	public function test_register_post_type_args_show_in_rest(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertTrue( $args['show_in_rest'] ?? null );
	}

	public function test_register_post_type_args_rest_base(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertSame( 'peptides', $args['rest_base'] ?? null );
	}

	public function test_register_post_type_args_no_rest_namespace(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertArrayNotHasKey(
			'rest_namespace',
			$args,
			'rest_namespace must be absent — defaults to wp/v2 for Gutenberg compatibility'
		);
	}

	public function test_register_post_type_args_capability_type(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertSame( 'post', $args['capability_type'] ?? null );
	}

	public function test_register_post_type_args_map_meta_cap(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertTrue( $args['map_meta_cap'] ?? null );
	}

	public function test_register_post_type_args_not_hierarchical(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertFalse( $args['hierarchical'] ?? null );
	}

	public function test_register_post_type_args_has_archive(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertTrue( $args['has_archive'] ?? null );
	}

	public function test_register_post_type_args_rewrite_slug(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertSame( 'peptides', $args['rewrite']['slug'] ?? null );
	}

	public function test_register_post_type_args_rewrite_with_front(): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$this->assertFalse( $args['rewrite']['with_front'] ?? null );
	}

	/**
	 * @dataProvider supports_provider
	 */
	public function test_register_post_type_supports_feature( string $feature ): void {
		PR_Core_Peptide_CPT::register_peptide_post_type();
		$args     = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'];
		$supports = $args['supports'] ?? [];
		$this->assertContains( $feature, $supports );
	}

	/**
	 * @return array<array<string>>
	 */
	public static function supports_provider(): array {
		return [
			[ 'title' ],
			[ 'editor' ],
			[ 'thumbnail' ],
			[ 'excerpt' ],
			[ 'revisions' ],
			[ 'custom-fields' ],
		];
	}

	// ── Taxonomy guard ────────────────────────────────────────────────.

	public function test_register_taxonomies_noop_when_already_exists(): void {
		$GLOBALS['pr_core_test_state']['existing_taxonomies'] = [ 'peptide_category' ];
		PR_Core_Peptide_CPT::register_taxonomies();
		$this->assertEmpty( $GLOBALS['pr_core_test_state']['registered_taxonomies'] );
	}

	// ── Taxonomy registration payload ─────────────────────────────────.

	public function test_register_taxonomies_registers_peptide_category(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$this->assertArrayHasKey(
			'peptide_category',
			$GLOBALS['pr_core_test_state']['registered_taxonomies']
		);
	}

	public function test_register_taxonomies_does_not_register_pr_peptide_family(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$this->assertArrayNotHasKey(
			'pr_peptide_family',
			$GLOBALS['pr_core_test_state']['registered_taxonomies'],
			'pr_peptide_family taxonomy must not be registered (removed in v0.2.0)'
		);
	}

	public function test_register_taxonomies_does_not_register_peptide_family(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$this->assertArrayNotHasKey(
			'peptide_family',
			$GLOBALS['pr_core_test_state']['registered_taxonomies']
		);
	}

	public function test_register_taxonomies_attached_to_peptide_cpt(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$tax = $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_category'];
		$this->assertSame( 'peptide', $tax['object_type'] ?? null );
	}

	public function test_register_taxonomies_is_hierarchical(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$tax = $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_category'];
		$this->assertTrue( $tax['args']['hierarchical'] ?? null );
	}

	public function test_register_taxonomies_show_in_rest(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$tax = $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_category'];
		$this->assertTrue( $tax['args']['show_in_rest'] ?? null );
	}

	public function test_register_taxonomies_rewrite_slug(): void {
		PR_Core_Peptide_CPT::register_taxonomies();
		$tax = $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_category'];
		$this->assertSame( 'peptide-category', $tax['args']['rewrite']['slug'] ?? null );
	}

	// ── Hook registration ─────────────────────────────────────────────.

	public function test_register_hooks_adds_init_action(): void {
		$cpt = new PR_Core_Peptide_CPT();
		$cpt->register_hooks();
		$hooks = array_column( $GLOBALS['pr_core_test_state']['added_actions'], 'hook' );
		$this->assertContains( 'init', $hooks );
	}
}
