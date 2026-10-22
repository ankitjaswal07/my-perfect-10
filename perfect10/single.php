<?php
/**
 * Single Editorial Journal / Blog article.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$cats      = get_the_category();
	$cat_label = ( ! empty( $cats ) && 'Uncategorized' !== $cats[0]->name ) ? $cats[0]->name : __( 'Editorial Journal', 'perfect10' );
	$cover_url = p10_blog_image( get_post(), 'p10-hero' );
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'p10-article' ); ?>>

		<header class="p10-page-head">
			<div class="p10-shell">
				<?php p10_breadcrumb(); ?>
				<?php p10_section_eyebrow( '', $cat_label ); ?>
				<h1 class="p10-page-head__title"><?php the_title(); ?></h1>
				<p class="p10-page-head__meta">
					<span><?php echo esc_html( get_the_date() ); ?></span>
					<span aria-hidden="true">◆</span>
					<span><?php echo esc_html( p10_reading_time( get_post() ) ); ?></span>
					<span aria-hidden="true">◆</span>
					<span><?php echo esc_html( p10_option( 'brand_name', 'Perfect 10' ) . ' ' . __( 'Editorial Desk', 'perfect10' ) ); ?></span>
				</p>
				<?php if ( has_excerpt() ) : ?>
					<p class="p10-page-head__blurb"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php endif; ?>
			</div>
		</header>

		<div class="p10-shell">
			<figure class="p10-article__hero p10-reveal">
				<img
					src="<?php echo esc_url( $cover_url ); ?>"
					alt="<?php echo esc_attr( get_the_title() ); ?>"
					width="1400"
					height="875"
					decoding="async">
			</figure>
		</div>

		<section class="p10-section p10-section--tight">
			<div class="p10-shell">
				<div class="p10-article__body p10-content">
					<?php
					the_content();
					wp_link_pages(
						array(
							'before' => '<nav class="p10-pagination">',
							'after'  => '</nav>',
						)
					);
					?>
				</div>
			</div>
		</section>

	</article>

	<?php
	$more_posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'post__not_in'   => array( get_the_ID() ),
			'no_found_rows'  => true,
		)
	);
	if ( ! empty( $more_posts ) ) :
		?>
		<section class="p10-section p10-section--panel p10-section--gold-line">
			<div class="p10-shell">
				<?php
				get_template_part(
					'template-parts/section-head',
					null,
					array(
						'kicker'     => __( 'Continue Reading', 'perfect10' ),
						'title'      => __( 'More from the', 'perfect10' ),
						'accent'     => __( 'Journal', 'perfect10' ),
						'action'     => __( 'All articles', 'perfect10' ),
						'action_url' => p10_journal_url(),
					)
				);
				?>
				<div class="p10-grid p10-grid--blogs">
					<?php foreach ( $more_posts as $idx => $item ) : ?>
						<?php
						get_template_part(
							'template-parts/card-blog',
							null,
							array(
								'post'  => $item,
								'index' => $idx + 1,
							)
						);
						?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
endwhile;

get_template_part( 'template-parts/callband' );
get_footer();
