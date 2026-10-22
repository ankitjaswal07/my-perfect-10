<?php
/**
 * Template Name: P10 — Create a Listing
 * Template Post Type: page
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<header class="p10-page-head">
		<div class="p10-shell">
			<?php p10_breadcrumb(); ?>
			<?php p10_section_eyebrow( '', (string) p10_option( 'ads_kicker', __( 'For Advertisers', 'perfect10' ) ) ); ?>
			<h1 class="p10-page-head__title"><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p class="p10-page-head__blurb"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php elseif ( p10_option( 'ads_copy' ) ) : ?>
				<p class="p10-page-head__blurb"><?php echo esc_html( p10_option( 'ads_copy' ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<section class="p10-section p10-section--tight">
		<div class="p10-shell">

			<div class="p10-search__panel p10-reveal">
				<?php p10_form_status( 'register' ); ?>
				<?php
				p10_the_form( 'register', array(
					'redirect' => get_permalink(),
					'submit'   => __( 'Submit application', 'perfect10' ),
				) );
				?>
			</div>

		</div>
	</section>

	<?php
	if ( function_exists( 'p10_get_page_editorial_html' ) ) {
		get_template_part(
			'template-parts/editorial-guide',
			null,
			array(
				'kicker'  => __( 'Advertiser Guide', 'perfect10' ),
				'title'   => __( 'Independent Companion', 'perfect10' ),
				'accent'  => __( 'Placement Standards', 'perfect10' ),
				'content' => p10_get_page_editorial_html( get_post(), 'register-independent-profile' ),
			)
		);
	}
	?>

	<?php
	$packages = p10_get_packages();
	if ( ! empty( $packages ) ) :
		?>
		<section class="p10-section p10-section--panel p10-section--gold-line">
			<div class="p10-shell">
				<?php
				get_template_part( 'template-parts/section-head', null, array(
					'kicker' => __( 'Placement', 'perfect10' ),
					'title'  => __( 'Premium', 'perfect10' ),
					'accent' => __( 'Packages', 'perfect10' ),
					'center' => true,
				) );
				?>
				<div class="p10-grid p10-grid--packages">
					<?php foreach ( $packages as $package ) : ?>
						<?php get_template_part( 'template-parts/card-package', null, array( 'package' => $package ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>
	<?php
endwhile;

get_footer();
