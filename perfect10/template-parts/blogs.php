<?php
/**
 * Homepage Section 06 — Editorial Journal / Latest Blogs.
 *
 * Accepts optional $args overrides when invoked from P10_Elementor_Blogs.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = isset( $args ) ? $args : array();

if ( ! isset( $args['force_show'] ) && ! p10_option( 'blogs_enabled', 1 ) ) {
	return;
}

$number  = isset( $args['number'] ) ? (string) $args['number'] : (string) p10_option( 'blogs_number', '06' );
$kicker  = isset( $args['kicker'] ) ? (string) $args['kicker'] : (string) p10_option( 'blogs_kicker', __( 'Editorial Journal', 'perfect10' ) );
$title   = isset( $args['title'] ) ? (string) $args['title'] : (string) p10_option( 'blogs_title', __( 'Latest from the', 'perfect10' ) );
$accent  = isset( $args['accent'] ) ? (string) $args['accent'] : (string) p10_option( 'blogs_accent', __( 'Journal', 'perfect10' ) );
$action  = isset( $args['action'] ) ? (string) $args['action'] : (string) p10_option( 'blogs_action', __( 'View all articles', 'perfect10' ) );
$url     = isset( $args['action_url'] ) && $args['action_url'] ? (string) $args['action_url'] : p10_journal_url();
$count   = isset( $args['count'] ) ? max( 1, (int) $args['count'] ) : max( 1, (int) p10_option( 'blogs_count', 3 ) );
$columns = isset( $args['columns'] ) ? max( 1, min( 4, (int) $args['columns'] ) ) : 3;

$posts = p10_get_blog_posts( $count );

// Filter out WordPress's default unedited "Hello world!" post if present before Demo Import.
$real_posts = array();
foreach ( $posts as $item ) {
	if ( 'hello-world' === $item->post_name && count( $posts ) === 1 ) {
		continue;
	}
	$real_posts[] = $item;
}

if ( empty( $real_posts ) && function_exists( 'p10_demo_blog_posts_data' ) ) {
	$real_posts = array_slice( p10_demo_blog_posts_data(), 0, $count );
}

if ( empty( $real_posts ) ) {
	return;
}
?>
<section class="p10-section p10-blogs" id="p10-blogs">
	<div class="p10-shell">

		<?php
		get_template_part(
			'template-parts/section-head',
			null,
			array(
				'number'     => $number,
				'kicker'     => $kicker,
				'title'      => $title,
				'accent'     => $accent,
				'action'     => $action,
				'action_url' => $url,
			)
		);
		?>

		<div class="p10-grid p10-grid--blogs" style="--p10-blog-cols:<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $real_posts as $i => $article ) : ?>
				<?php
				get_template_part(
					'template-parts/card-blog',
					null,
					array(
						'post'  => $article,
						'index' => $i + 1,
					)
				);
				?>
			<?php endforeach; ?>
		</div>

	</div>
</section>
