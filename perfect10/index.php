<?php
/**
 * Editorial Journal / Blog archive & fallback index template.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$is_journal = is_home() || is_category() || is_tag() || is_date();

get_template_part(
	'template-parts/page-head',
	null,
	array(
		'kicker' => $is_journal ? __( 'Editorial Journal', 'perfect10' ) : __( 'Archive', 'perfect10' ),
		'title'  => is_home() && ! is_front_page() ? __( 'The Perfect 10 Journal', 'perfect10' ) : '',
		'blurb'  => $is_journal ? __( 'Destination guides, five-star dining etiquette, coastal weekend escapes, and private hospitality insights across Los Angeles County and Orange County.', 'perfect10' ) : '',
	)
);
?>

<section class="p10-section p10-section--tight">
	<div class="p10-shell">

		<?php if ( have_posts() ) : ?>

			<div class="p10-grid p10-grid--blogs">
				<?php
				$idx = 0;
				while ( have_posts() ) :
					the_post();
					$idx++;
					get_template_part(
						'template-parts/card-blog',
						null,
						array(
							'post'  => get_post(),
							'index' => $idx,
						)
					);
				endwhile;
				?>
			</div>

			<?php get_template_part( 'template-parts/pagination' ); ?>

		<?php elseif ( $is_journal && function_exists( 'p10_demo_blog_posts_data' ) ) : ?>

			<div class="p10-grid p10-grid--blogs">
				<?php foreach ( p10_demo_blog_posts_data() as $idx => $preview ) : ?>
					<?php
					get_template_part(
						'template-parts/card-blog',
						null,
						array(
							'post'  => $preview,
							'index' => $idx + 1,
						)
					);
					?>
				<?php endforeach; ?>
			</div>

		<?php else : ?>
			<?php get_template_part( 'template-parts/none' ); ?>
		<?php endif; ?>

	</div>
</section>

<?php
get_template_part( 'template-parts/callband' );
get_footer();
