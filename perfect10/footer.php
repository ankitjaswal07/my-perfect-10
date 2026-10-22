<?php
/**
 * Executive site footer, Hollywood Elite Escorts child-website showcase,
 * and floating concierge chatbot.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$brand          = (string) p10_option( 'brand_name', get_bloginfo( 'name' ) );
$city_count     = max( 6, (int) p10_option( 'footer_columns', 10 ) );
$cities         = array_slice( p10_get_featured_cities( $city_count ), 0, $city_count );
$categories     = p10_get_categories();
$year           = gmdate( 'Y' );
$child_enabled  = (bool) p10_option( 'child_site_enabled', 1 );
$child_name     = (string) p10_option( 'child_site_name', 'Hollywood Elite Escorts' );
$child_url      = (string) p10_option( 'child_site_url', 'https://hollywoodseliteescorts.com/' );
$child_kicker   = (string) p10_option( 'child_site_kicker', __( 'Official Child Website · Sister Publication', 'perfect10' ) );
$child_desc     = (string) p10_option( 'child_site_desc', '' );
$child_cta      = (string) p10_option( 'child_site_cta', __( 'Visit Hollywood Elite Escorts', 'perfect10' ) );
$hours          = (string) p10_option( 'hours', __( '24 hours · 7 days a week', 'perfect10' ) );
?>
</main><!-- #p10-main -->

<?php if ( ! p10_elementor_location( 'footer' ) ) : ?>
<footer class="p10-footer" role="contentinfo">
	<div class="p10-shell">

		<!-- Executive Concierge Strip -->
		<div class="p10-footer__concierge-bar">
			<div class="p10-footer__concierge-copy">
				<span class="p10-footer__concierge-kicker">
					<?php echo p10_premium_icon_svg( 'p10-icon-crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php esc_html_e( '24/7 Private Concierge & Referral Desk', 'perfect10' ); ?>
				</span>
				<p class="p10-footer__concierge-lead">
					<?php esc_html_e( 'Immediate hotel suite & private residence introductions across Los Angeles County & Orange County.', 'perfect10' ); ?>
				</p>
			</div>
			<div class="p10-footer__concierge-actions">
				<?php if ( p10_phone_display() ) : ?>
					<a class="p10-btn p10-btn--gold" href="<?php echo esc_url( p10_phone_href() ); ?>">
						<?php echo esc_html( p10_phone_display() ); ?>
					</a>
				<?php endif; ?>
				<a class="p10-btn p10-btn--ghost" href="<?php echo esc_url( p10_utility_url( 'booking' ) ); ?>">
					<?php esc_html_e( 'Private Reservation', 'perfect10' ); ?>
				</a>
			</div>
		</div>

		<!-- 4-Column Executive Directory Grid -->
		<div class="p10-footer__top">

			<div class="p10-footer__brand">
				<a class="p10-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="p10-brand__name"><?php echo esc_html( $brand ); ?></span>
					<?php if ( p10_option( 'brand_suffix' ) ) : ?>
						<span class="p10-brand__sub"><?php echo esc_html( p10_option( 'brand_suffix' ) ); ?></span>
					<?php endif; ?>
				</a>

				<?php if ( p10_option( 'footer_blurb' ) ) : ?>
					<p class="p10-footer__blurb"><?php echo esc_html( p10_option( 'footer_blurb' ) ); ?></p>
				<?php endif; ?>

				<div class="p10-footer__contact-card">
					<?php if ( p10_phone_display() ) : ?>
						<p class="p10-footer__phone">
							<span class="p10-footer__contact-label"><?php esc_html_e( 'Direct Line', 'perfect10' ); ?></span>
							<?php p10_phone_link(); ?>
						</p>
					<?php endif; ?>

					<?php if ( p10_option( 'email' ) ) : ?>
						<p class="p10-footer__email">
							<span class="p10-footer__contact-label"><?php esc_html_e( 'Concierge Email', 'perfect10' ); ?></span>
							<a href="mailto:<?php echo esc_attr( antispambot( (string) p10_option( 'email' ) ) ); ?>">
								<?php echo esc_html( antispambot( (string) p10_option( 'email' ) ) ); ?>
							</a>
						</p>
					<?php endif; ?>

					<?php if ( p10_option( 'address' ) || $hours ) : ?>
						<p class="p10-footer__address">
							<?php echo esc_html( (string) p10_option( 'address' ) ); ?>
							<?php if ( $hours ) : ?>
								<span> · <?php echo esc_html( $hours ); ?></span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<?php if ( p10_option( 'footer_show_tags', 1 ) && p10_option( 'footer_tags' ) ) : ?>
					<p class="p10-footer__tags"><?php echo esc_html( p10_option( 'footer_tags' ) ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( p10_option( 'footer_show_cities', 1 ) ) : ?>
				<div class="p10-footer__col">
					<h3 class="p10-footer__title"><?php esc_html_e( 'Popular Destinations', 'perfect10' ); ?></h3>
					<ul class="p10-footer__list">
						<?php if ( ! empty( $cities ) ) : ?>
							<?php foreach ( $cities as $city ) : ?>
								<li>
									<a href="<?php echo esc_url( p10_term_url( $city ) ); ?>"><?php echo esc_html( $city->name ); ?></a>
								</li>
							<?php endforeach; ?>
						<?php endif; ?>
						<li>
							<a class="p10-footer__list-accent" href="<?php echo esc_url( p10_utility_url( 'cities' ) ); ?>">
								<?php esc_html_e( 'All 211+ Cities →', 'perfect10' ); ?>
							</a>
						</li>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( p10_option( 'footer_show_browse', 1 ) ) : ?>
				<div class="p10-footer__col">
					<h3 class="p10-footer__title"><?php esc_html_e( 'Browse Companion Types', 'perfect10' ); ?></h3>
					<ul class="p10-footer__list">
						<li>
							<a class="p10-footer__list-accent" href="<?php echo esc_url( p10_directory_url( array( 'premium' => 1 ) ) ); ?>">
								<?php esc_html_e( 'Premium Collection', 'perfect10' ); ?>
							</a>
						</li>
						<?php if ( ! empty( $categories ) ) : ?>
							<?php foreach ( $categories as $category ) : ?>
								<li>
									<a href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
								</li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( p10_option( 'footer_show_explore', 1 ) ) : ?>
				<div class="p10-footer__col">
					<h3 class="p10-footer__title"><?php esc_html_e( 'Explore & Client Desk', 'perfect10' ); ?></h3>
					<?php if ( has_nav_menu( 'footer' ) ) : ?>
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'footer',
								'container'      => false,
								'menu_class'     => 'p10-footer__list',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
						?>
					<?php else : ?>
						<ul class="p10-footer__list">
							<li><a href="<?php echo esc_url( p10_directory_url() ); ?>"><?php esc_html_e( 'Full Directory', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_journal_url() ); ?>"><?php esc_html_e( 'Editorial Journal / Blog', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_utility_url( 'reviews' ) ); ?>"><?php esc_html_e( 'Verified Guest Reviews', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_utility_url( 'packages' ) ); ?>"><?php esc_html_e( 'Premium Packages', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_utility_url( 'booking' ) ); ?>"><?php esc_html_e( 'Reservation Form', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_utility_url( 'register' ) ); ?>"><?php esc_html_e( 'Create a Listing', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_utility_url( 'about' ) ); ?>"><?php esc_html_e( 'About Perfect 10', 'perfect10' ); ?></a></li>
							<li><a href="<?php echo esc_url( p10_utility_url( 'faq' ) ); ?>"><?php esc_html_e( 'Client Etiquette & FAQ', 'perfect10' ); ?></a></li>
						</ul>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</div>

		<!-- Official Child Website Showcase: Hollywood Elite Escorts -->
		<?php if ( $child_enabled && $child_name && $child_url ) : ?>
			<div class="p10-footer__sister" data-p10-tilt>
				<div class="p10-footer__sister-badge">
					<?php echo p10_premium_icon_svg( 'p10-icon-crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php echo esc_html( $child_kicker ); ?></span>
				</div>
				<div class="p10-footer__sister-grid">
					<div class="p10-footer__sister-copy">
						<h3 class="p10-footer__sister-title">
							<a href="<?php echo esc_url( $child_url ); ?>" target="_blank" rel="noopener">
								<?php echo esc_html( $child_name ); ?>
							</a>
						</h3>
						<?php if ( $child_desc ) : ?>
							<p class="p10-footer__sister-desc"><?php echo esc_html( $child_desc ); ?></p>
						<?php endif; ?>
					</div>
					<div class="p10-footer__sister-action">
						<a class="p10-btn p10-btn--gold" href="<?php echo esc_url( $child_url ); ?>" target="_blank" rel="noopener">
							<?php echo esc_html( $child_cta ); ?>
							<span aria-hidden="true">↗</span>
						</a>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( p10_option( 'footer_mark', 1 ) ) : ?>
			<div class="p10-footer__mark" aria-hidden="true"><?php echo esc_html( $brand ); ?></div>
		<?php endif; ?>

		<!-- Full-Width Legal Base Bar -->
		<div class="p10-footer__base">
			<p class="p10-footer__copy">
				<?php
				$copyright = (string) p10_option( 'footer_copyright', '' );
				if ( $copyright ) {
					echo esc_html( $copyright );
				} else {
					printf(
						/* translators: 1: year, 2: brand name. */
						esc_html__( '© %1$s %2$s · In partnership with %3$s. All rights reserved. 18+ only.', 'perfect10' ),
						esc_html( $year ),
						esc_html( $brand ),
						esc_html( $child_name ? $child_name : 'Hollywood Elite Escorts' )
					);
				}
				?>
			</p>

			<nav class="p10-footer__legal-nav" aria-label="<?php esc_attr_e( 'Legal', 'perfect10' ); ?>">
				<?php if ( has_nav_menu( 'legal' ) ) : ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'legal',
							'container'      => false,
							'items_wrap'     => '%3$s',
							'depth'          => 1,
							'fallback_cb'    => false,
						)
					);
					?>
				<?php else : ?>
					<a href="<?php echo esc_url( p10_utility_url( 'privacy' ) ); ?>"><?php esc_html_e( 'Privacy & 18+ Policy', 'perfect10' ); ?></a>
					<a href="<?php echo esc_url( p10_utility_url( 'faq' ) ); ?>"><?php esc_html_e( 'Etiquette & Terms', 'perfect10' ); ?></a>
					<?php if ( $child_enabled && $child_url ) : ?>
						<a href="<?php echo esc_url( $child_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $child_name ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</nav>
		</div>

		<?php if ( p10_option( 'footer_show_disclaimer', 1 ) && p10_option( 'footer_legal' ) ) : ?>
			<p class="p10-footer__disclaimer"><?php echo wp_kses_post( (string) p10_option( 'footer_legal' ) ); ?></p>
		<?php endif; ?>

	</div>
</footer>
<?php endif; ?>

<?php get_template_part( 'template-parts/concierge' ); ?>

<?php wp_footer(); ?>
</body>
</html>
