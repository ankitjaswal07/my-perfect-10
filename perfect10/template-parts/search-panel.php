<?php
/**
 * Search panel — Direct keyword search + cascading County / City lookup + live autocomplete.
 *
 * Degrades cleanly to a plain GET form against the directory when JavaScript is off;
 * main.js enhances it with instant live suggestions and routes clean County/City
 * selections without a keyword to pretty /county/city/ URLs.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$args = isset( $args ) ? $args : array();

if ( empty( $args['force_show'] ) && ! p10_option( 'search_enabled', 1 ) ) {
	return;
}

$grouped     = p10_get_cities_by_county();
$counties    = p10_get_counties();
$home        = trailingslashit( home_url( '/' ) );
$template    = $home . '__county__/__city__/';
$directory   = p10_directory_url();
$anchor      = isset( $args['anchor'] ) ? sanitize_title( (string) $args['anchor'] ) : 'p10-next';
$kicker      = isset( $args['kicker'] ) ? (string) $args['kicker'] : (string) p10_option( 'search_kicker', '' );
$title       = isset( $args['title'] ) ? (string) $args['title'] : (string) p10_option( 'search_title', '' );
$accent      = isset( $args['accent'] ) ? (string) $args['accent'] : (string) p10_option( 'search_accent', '' );
$label       = isset( $args['label'] ) ? (string) $args['label'] : (string) p10_option( 'search_label', __( 'Where are you looking?', 'perfect10' ) );
$region      = isset( $args['region'] ) ? (string) $args['region'] : (string) p10_option( 'search_region', '' );
$button      = isset( $args['button'] ) ? (string) $args['button'] : (string) p10_option( 'search_button', __( 'Search', 'perfect10' ) );
$show_direct = isset( $args['show_direct'] ) ? (bool) $args['show_direct'] : (bool) p10_option( 'search_direct_enabled', 1 );
$direct_lbl  = isset( $args['direct_label'] ) ? (string) $args['direct_label'] : (string) p10_option( 'search_direct_label', __( 'Direct Search', 'perfect10' ) );
$direct_ph   = isset( $args['direct_placeholder'] ) ? (string) $args['direct_placeholder'] : (string) p10_option( 'search_direct_placeholder', __( 'Companion name, city, type, blonde, VIP…', 'perfect10' ) );
$show_quick  = ! isset( $args['show_quick_links'] ) || (bool) $args['show_quick_links'];
$current_q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$uid         = wp_unique_id( 'p10s-' );
?>
<section class="p10-section p10-section--tight p10-search"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="p10-shell">

		<div class="p10-search__panel p10-reveal">

			<div class="p10-search__head">
				<div>
					<?php if ( $kicker ) : ?>
						<p class="p10-eyebrow"><span class="p10-eyebrow__text"><?php echo esc_html( $kicker ); ?></span></p>
					<?php endif; ?>
					<?php
					p10_the_heading(
						$title,
						$accent,
						'h2',
						'p10-display-md'
					);
					?>
				</div>

				<?php if ( $region ) : ?>
					<p class="p10-search__caption">
						<?php echo esc_html( $label ); ?><br>
						<b><?php echo esc_html( $region ); ?></b>
					</p>
				<?php endif; ?>
			</div>

			<form
				class="p10-search__grid<?php echo $show_direct ? ' p10-search__grid--with-direct' : ''; ?>"
				method="get"
				action="<?php echo esc_url( $directory ); ?>"
				data-p10-search-form
				data-url-template="<?php echo esc_attr( $template ); ?>"
				role="search">

				<?php if ( $show_direct ) : ?>
					<div class="p10-field p10-field--direct" data-p10-direct-wrap>
						<label class="p10-field__label" for="<?php echo esc_attr( $uid . 'q' ); ?>">
							<?php echo esc_html( $direct_lbl ); ?>
						</label>
						<div class="p10-direct-input-wrap">
							<span class="p10-direct-input__icon" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7.5"/><path d="M20 20l-3.8-3.8"/></svg>
							</span>
							<input
								type="search"
								class="p10-input p10-input--direct"
								id="<?php echo esc_attr( $uid . 'q' ); ?>"
								name="q"
								value="<?php echo esc_attr( $current_q ); ?>"
								placeholder="<?php echo esc_attr( $direct_ph ); ?>"
								autocomplete="off"
								data-p10-direct-input>
						</div>
						<div class="p10-suggest" data-p10-suggest hidden role="listbox" aria-label="<?php esc_attr_e( 'Instant search suggestions', 'perfect10' ); ?>"></div>
					</div>
				<?php endif; ?>

				<div class="p10-field">
					<label class="p10-field__label" for="<?php echo esc_attr( $uid . 'county' ); ?>"><?php esc_html_e( 'County', 'perfect10' ); ?></label>
					<select
						class="p10-select"
						id="<?php echo esc_attr( $uid . 'county' ); ?>"
						name="county"
						data-p10-county
						data-link-base="<?php echo esc_attr( $home . '__county__/' ); ?>">
						<option value=""><?php esc_html_e( 'All Counties', 'perfect10' ); ?></option>
						<?php foreach ( $counties as $county ) : ?>
							<option value="<?php echo esc_attr( $county->slug ); ?>"><?php echo esc_html( $county->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="p10-field">
					<label class="p10-field__label" for="<?php echo esc_attr( $uid . 'city' ); ?>"><?php esc_html_e( 'City', 'perfect10' ); ?></label>
					<select class="p10-select" id="<?php echo esc_attr( $uid . 'city' ); ?>" name="city" data-p10-city>
						<option value=""><?php esc_html_e( 'All Cities', 'perfect10' ); ?></option>
						<?php
						foreach ( $grouped as $county_slug => $group ) :
							if ( empty( $group['cities'] ) ) {
								continue;
							}
							?>
							<optgroup label="<?php echo esc_attr( $group['label'] ); ?>" data-county="<?php echo esc_attr( $county_slug ); ?>">
								<?php foreach ( $group['cities'] as $city ) : ?>
									<option value="<?php echo esc_attr( $city->slug ); ?>"><?php echo esc_html( $city->name ); ?></option>
								<?php endforeach; ?>
							</optgroup>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="p10-field p10-field--submit">
					<span class="p10-field__label p10-sr"><?php esc_html_e( 'Submit', 'perfect10' ); ?></span>
					<button class="p10-btn p10-btn--gold" type="submit">
						<?php echo esc_html( $button ); ?>
					</button>
				</div>
			</form>

			<?php if ( $show_quick ) : ?>
				<?php $quick_links = p10_get_categories(); ?>
				<?php if ( ! empty( $quick_links ) ) : ?>
					<div class="p10-search__hint">
						<span><?php esc_html_e( 'Popular:', 'perfect10' ); ?></span>
						<?php foreach ( array_slice( $quick_links, 0, 5 ) as $category ) : ?>
							<a href="<?php echo esc_url( get_term_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
						<?php endforeach; ?>
						<a class="p10-search__hint-premium" href="<?php echo esc_url( p10_directory_url( array( 'premium' => 1 ) ) ); ?>">
							<?php echo p10_premium_icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php esc_html_e( 'Premium Collection', 'perfect10' ); ?></span>
						</a>
					</div>
				<?php endif; ?>
			<?php endif; ?>

		</div>

	</div>
</section>
