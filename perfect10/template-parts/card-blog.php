<?php
/**
 * Editorial Journal / Blog article card.
 *
 * Expected $args:
 *   post  WP_Post|array Either a published WP_Post or a preview array from p10_demo_blog_posts_data().
 *   index int           1-based index for staggered scroll reveal.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args  = isset( $args ) ? $args : array();
$entry = isset( $args['post'] ) ? $args['post'] : get_post();
$index = isset( $args['index'] ) ? (int) $args['index'] : 0;
$delay = $index ? ( ( $index - 1 ) % 6 ) : 0;

if ( $entry instanceof WP_Post ) {
	$title     = get_the_title( $entry );
	$permalink = get_permalink( $entry );
	$image     = p10_blog_image( $entry, 'p10-card-wide' );
	$date      = get_the_date( '', $entry );
	$read_time = p10_reading_time( $entry );
	$cats      = get_the_category( $entry->ID );
	$category  = ( ! empty( $cats ) && 'Uncategorized' !== $cats[0]->name ) ? $cats[0]->name : __( 'Editorial Journal', 'perfect10' );
	$excerpt   = has_excerpt( $entry )
		? get_the_excerpt( $entry )
		: wp_trim_words( wp_strip_all_tags( $entry->post_content ), 24 );
} elseif ( is_array( $entry ) ) {
	$title     = isset( $entry['title'] ) ? (string) $entry['title'] : '';
	$permalink = p10_journal_url();
	$image     = ! empty( $entry['image'] ) ? P10_URI . 'assets/images/demo/' . $entry['image'] : P10_URI . 'assets/images/demo/city-beverly-hills.jpg';
	$date      = ! empty( $entry['date'] ) ? mysql2date( get_option( 'date_format' ), $entry['date'] ) : wp_date( get_option( 'date_format' ) );
	$read_time = __( '3 min read', 'perfect10' );
	$category  = ! empty( $entry['category'] ) ? (string) $entry['category'] : __( 'Editorial Journal', 'perfect10' );
	$excerpt   = ! empty( $entry['excerpt'] ) ? (string) $entry['excerpt'] : '';
} else {
	return;
}
?>
<article class="p10-blog-card p10-reveal" data-p10-tilt data-delay="<?php echo esc_attr( $delay ); ?>">
	<a class="p10-blog-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( $title ); ?>">
		<img
			class="p10-blog-card__img"
			src="<?php echo esc_url( $image ); ?>"
			alt="<?php echo esc_attr( $title ); ?>"
			loading="lazy"
			decoding="async"
			width="1200"
			height="750">
		<span class="p10-blog-card__cat"><?php echo esc_html( $category ); ?></span>
	</a>

	<div class="p10-blog-card__body">
		<p class="p10-blog-card__meta">
			<span><?php echo esc_html( $date ); ?></span>
			<span aria-hidden="true">◆</span>
			<span><?php echo esc_html( $read_time ); ?></span>
		</p>

		<h3 class="p10-blog-card__title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
		</h3>

		<?php if ( $excerpt ) : ?>
			<p class="p10-blog-card__excerpt"><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>

		<div class="p10-blog-card__foot">
			<a class="p10-link" href="<?php echo esc_url( $permalink ); ?>">
				<?php esc_html_e( 'Read Article', 'perfect10' ); ?>
				<span class="p10-link__arrow" aria-hidden="true">→</span>
			</a>
		</div>
	</div>
</article>
