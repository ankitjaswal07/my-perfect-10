<?php
/**
 * Nationality archive.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


get_header();

get_template_part( 'template-parts/page-head', null, array( 'kicker' => __( 'Nationality', 'perfect10' ) ) );
?>

<section class="p10-section p10-section--tight">
	<div class="p10-shell">
		<?php get_template_part( 'template-parts/directory' ); ?>
	</div>
</section>

<?php
$current_nat = get_queried_object();
if ( $current_nat instanceof WP_Term && function_exists( 'p10_get_term_editorial_html' ) ) {
	get_template_part(
		'template-parts/editorial-guide',
		null,
		array(
			'kicker'  => __( 'International Heritage', 'perfect10' ),
			'title'   => sprintf( '%s', $current_nat->name ),
			'accent'  => __( 'Companions', 'perfect10' ),
			'content' => p10_get_term_editorial_html( $current_nat ),
		)
	);
}

get_template_part( 'template-parts/callband' );
get_footer();
