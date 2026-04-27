<?php
declare(strict_types=1);

/**
 * Frontend verification date display on peptide single pages.
 *
 * What: Appends last-verified date after verdict card on reader-facing pages.
 * Who calls it: PR_Core::init() hooks the_content filter.
 * Dependencies: None (hooked via the_content).
 *
 * @see includes/class-pr-core.php — Registers the_content hook.
 */
class PR_Core_Verification_Display {

	/**
	 * Initialize frontend hooks.
	 *
	 * Side effects: Registers the_content filter.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'the_content', [ __CLASS__, 'append_verification_display' ], 20 );
	}

	/**
	 * Append verification display after verdict card.
	 *
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public static function append_verification_display( string $content ): string {
		// Only on peptide CPT, not in admin.
		if ( is_admin() || ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Only if _pr_last_source_verified is set.
		$last_verified = get_post_meta( $post_id, '_pr_last_source_verified', true );
		if ( empty( $last_verified ) ) {
			return $content;
		}

		// Find the verdict card closing div and append after it.
		$verdict_close = '</div><!-- .pr-verdict -->';
		if ( strpos( $content, $verdict_close ) !== false ) {
			$display = self::render_verification_block( $last_verified );
			$content = str_replace( $verdict_close, $verdict_close . $display, $content );
		}

		return $content;
	}

	/**
	 * Render the verification block HTML.
	 *
	 * @param string $last_verified ISO date string.
	 * @return string HTML block.
	 */
	private static function render_verification_block( string $last_verified ): string {
		$date_obj = new \DateTime( $last_verified );
		$formatted_date = $date_obj->format( get_option( 'date_format' ) );

		$html = '<p class="pr-verification-date">';
		$html .= esc_html__( 'Last verified: ', 'peptide-repo-core' );
		$html .= sprintf(
			'<time datetime="%s">%s</time>',
			esc_attr( $date_obj->format( 'Y-m-d' ) ),
			esc_html( $formatted_date )
		);
		$html .= ' — <a href="' . esc_url( home_url( '/our-methodology/' ) ) . '">' . esc_html__( 'methodology', 'peptide-repo-core' ) . '</a>';
		$html .= '</p>';

		return $html;
	}
}
