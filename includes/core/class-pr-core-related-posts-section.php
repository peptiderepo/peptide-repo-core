<?php
declare(strict_types=1);

/**
 * Renders the related articles section on peptide single pages.
 *
 * What: Hooks into pr_core_after_peptide_content and renders a card grid
 *       of related posts fetched via PR_Core_Related_Posts_Provider.
 * Who calls it: PR_Core::init() instantiates and registers hooks.
 * Dependencies: PR_Core_Related_Posts_Provider interface, template-parts/related-posts/card.php.
 *
 * Settings read:
 * - pr_core_related_posts_enabled (bool, default true)
 * - pr_core_related_posts_limit (int, default 3, range 1-6)
 */
class PR_Core_Related_Posts_Section {

	/** @var PR_Core_Related_Posts_Provider */
	private $provider;

	/**
	 * Constructor.
	 *
	 * @param PR_Core_Related_Posts_Provider $provider Provider instance.
	 */
	public function __construct( PR_Core_Related_Posts_Provider $provider ) {
		$this->provider = $provider;
	}

	/**
	 * Register the action hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'pr_core_after_peptide_content', [ $this, 'render' ] );
		add_action( 'save_post_post', [ $this, 'invalidate_caches' ] );
	}

	/**
	 * Render the related posts section.
	 *
	 * Called via do_action( 'pr_core_after_peptide_content', $peptide_id ) in templates.
	 * If feature disabled or no posts found, renders nothing.
	 *
	 * @param int $peptide_id The peptide post ID.
	 * @return void
	 */
	public function render( int $peptide_id ): void {
		$enabled = (bool) get_option( 'pr_core_related_posts_enabled', true );
		if ( ! $enabled ) {
			return;
		}

		$limit = (int) get_option( 'pr_core_related_posts_limit', 3 );
		$limit = min( 6, max( 1, $limit ) );

		$posts = $this->provider->get_posts( $peptide_id, $limit + 5 );
		if ( empty( $posts ) ) {
			return;
		}

		$peptide = get_post( $peptide_id );
		?>
		<section class="pr-related-posts" aria-label="<?php esc_attr_e( 'Related Articles', 'peptide-repo-core' ); ?>">
			<h2><?php esc_html_e( 'Related Articles', 'peptide-repo-core' ); ?></h2>
			<div class="pr-related-posts__grid">
				<?php
				$count = 0;
				foreach ( $posts as $post ) {
					if ( $count >= $limit ) {
						break;
					}
					setup_postdata( $post );
					get_template_part( 'template-parts/related-posts/card', null, [ 'post' => $post ] );
					++$count;
				}
				wp_reset_postdata();
				?>
			</div>
			<?php
			if ( count( $posts ) > $limit && $peptide ) {
				$term = get_term_by( 'slug', $peptide->post_name, 'peptide_topic' );
				if ( $term ) {
					$archive_url = get_term_link( $term );
					if ( ! is_wp_error( $archive_url ) ) {
						?>
						<p class="pr-related-posts__view-all">
							<a href="<?php echo esc_url( $archive_url ); ?>">
								<?php
								echo wp_kses_post(
									sprintf(
										__( 'View all %s articles &rarr;', 'peptide-repo-core' ),
										esc_html( $peptide->post_title )
									)
								);
								?>
							</a>
						</p>
						<?php
					}
				}
			}
			?>
		</section>
		<?php
	}

	/**
	 * Invalidate related posts transient caches when a post is saved.
	 *
	 * Hooks into save_post_post to clear any cached related-post queries
	 * since the post corpus has changed.
	 *
	 * @return void
	 */
	public function invalidate_caches(): void {
		global $wpdb;
		$cache_keys = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'pr_core_related_%'"
		);
		foreach ( $cache_keys as $key ) {
			delete_transient( str_replace( '_transient_', '', $key ) );
		}
	}
}
