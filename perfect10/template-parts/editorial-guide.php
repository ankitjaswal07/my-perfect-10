<?php
/**
 * Luxury 300–400 word editorial guide panel for locations, profile types,
 * nationalities, archives, and utility pages.
 *
 * Expected $args:
 *   kicker  string       Optional eyebrow kicker.
 *   title   string       Optional heading before accent.
 *   accent  string       Optional italic gold accent.
 *   content string       Rich HTML content (also accepts 'html').
 *   term    WP_Term|null Optional term context.
 *   slug    string       Optional page slug fallback.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args   = isset( $args ) ? $args : array();
$term   = isset( $args['term'] ) && $args['term'] instanceof WP_Term ? $args['term'] : ( is_tax() ? get_queried_object() : null );
$kicker = isset( $args['kicker'] ) ? (string) $args['kicker'] : '';
$title  = isset( $args['title'] ) ? (string) $args['title'] : '';
$accent = isset( $args['accent'] ) ? (string) $args['accent'] : '';
$html   = ! empty( $args['content'] ) ? (string) $args['content'] : ( isset( $args['html'] ) ? (string) $args['html'] : '' );
$slug   = isset( $args['slug'] ) ? (string) $args['slug'] : '';

if ( ! $html && $term instanceof WP_Term && function_exists( 'p10_get_term_editorial_html' ) ) {
	$html = p10_get_term_editorial_html( $term );
	if ( ! $kicker ) {
		$kicker = __( 'Editorial Guide & Concierge Notes', 'perfect10' );
	}
	if ( ! $title && ! $accent ) {
		$title  = __( 'About', 'perfect10' );
		$accent = $term->name;
	}
}

if ( ! $html && $slug && function_exists( 'p10_editorial_page_html' ) ) {
	$html = p10_editorial_page_html( $slug );
}

if ( ! $html && is_singular( 'page' ) && function_exists( 'p10_get_page_editorial_html' ) ) {
	$html = p10_get_page_editorial_html( get_queried_object() );
}

if ( ! $html ) {
	return;
}

if ( ! $kicker ) {
	$kicker = __( 'Boutique Referral Guide', 'perfect10' );
}
?>
<section class="p10-section p10-section--tight p10-editorial-guide">
	<div class="p10-shell">
		<div class="p10-editorial-guide__card p10-reveal" data-p10-tilt>
			<div class="p10-editorial-guide__head">
				<div>
					<?php p10_section_eyebrow( '', $kicker ); ?>
					<?php if ( $title || $accent ) : ?>
						<?php p10_the_heading( $title, $accent, 'h2', 'p10-display-md' ); ?>
					<?php endif; ?>
				</div>

				<div class="p10-editorial-guide__pills" aria-label="<?php esc_attr_e( 'Service highlights', 'perfect10' ); ?>">
					<span class="p10-editorial-guide__pill">
						<?php echo p10_premium_icon_svg( 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php esc_html_e( 'Verified 18+ Network', 'perfect10' ); ?>
					</span>
					<span class="p10-editorial-guide__pill"><?php esc_html_e( '24/7 Concierge Desk', 'perfect10' ); ?></span>
					<span class="p10-editorial-guide__pill"><?php esc_html_e( 'Strict Discretion', 'perfect10' ); ?></span>
				</div>
			</div>

			<div class="p10-editorial-guide__body p10-content">
				<?php echo wp_kses_post( $html ); ?>
			</div>

			<div class="p10-editorial-guide__foot">
				<div class="p10-editorial-guide__actions">
					<?php if ( p10_phone_display() ) : ?>
						<a class="p10-btn p10-btn--gold p10-btn--sm" href="<?php echo esc_url( p10_phone_href() ); ?>">
							<?php echo esc_html( sprintf( __( 'Call %s', 'perfect10' ), p10_phone_display() ) ); ?>
						</a>
					<?php endif; ?>
					<a class="p10-btn p10-btn--ghost p10-btn--sm" href="<?php echo esc_url( p10_utility_url( 'booking' ) ); ?>">
						<?php esc_html_e( 'Private Reservation Form', 'perfect10' ); ?>
					</a>
				</div>
				<p class="p10-editorial-guide__sister">
					<?php esc_html_e( 'Official Child Website:', 'perfect10' ); ?>
					<a href="<?php echo esc_url( p10_option( 'child_site_url', 'https://hollywoodseliteescorts.com/' ) ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( p10_option( 'child_site_name', 'Hollywood Elite Escorts' ) ); ?> ↗
					</a>
				</p>
			</div>
		</div>
	</div>
</section>
