<?php
/**
 * Related posts card template part.
 *
 * Variables:
 * - $post: WP_Post object with thumbnail, title, excerpt, date.
 *
 * @package peptide-repo-core
 */

if ( ! isset( $args['post'] ) ) {
	return;
}

$post = $args['post'];
?>
<article class="pr-related-posts__card">
	<?php
	if ( has_post_thumbnail( $post ) ) {
		?>
		<div class="pr-related-posts__image">
			<?php
			echo wp_kses_post(
				get_the_post_thumbnail(
					$post,
					'post-thumbnail',
					[
						'class'    => 'pr-related-posts__image-element',
						'loading'  => 'lazy',
						'decoding' => 'async',
					]
				)
			);
			?>
		</div>
		<?php
	} else {
		?>
		<div class="pr-related-posts__image pr-related-posts__image--fallback">
			<img src="<?php echo esc_url( get_site_icon_url( 512 ) ); ?>" alt="<?php esc_attr_e( 'Site logo', 'peptide-repo-core' ); ?>" class="pr-related-posts__image-element" loading="lazy" decoding="async" />
		</div>
		<?php
	}
	?>
	<div class="pr-related-posts__content">
		<span class="pr-related-posts__badge"><?php esc_html_e( 'Article', 'peptide-repo-core' ); ?></span>
		<h3 class="pr-related-posts__title">
			<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
				<?php echo esc_html( $post->post_title ); ?>
			</a>
		</h3>
		<time class="pr-related-posts__date" datetime="<?php echo esc_attr( mysql2date( 'c', $post->post_date ) ); ?>">
			<?php echo esc_html( mysql2date( get_option( 'date_format' ), $post->post_date ) ); ?>
		</time>
		<?php
		if ( $post->post_excerpt ) {
			$excerpt = wp_strip_all_tags( $post->post_excerpt );
			$excerpt = wp_kses_post( wp_trim_words( $excerpt, 20 ) );
			?>
			<p class="pr-related-posts__excerpt">
				<?php echo wp_kses_post( $excerpt ); ?>
			</p>
			<?php
		}
		?>
	</div>
</article>
