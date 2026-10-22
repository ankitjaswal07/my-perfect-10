<?php
/** Dynamic Elementor component. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

class P10_Elementor_Gallery extends P10_Elementor_Widget {
	public function get_name() {
		return 'p10-gallery'; }
	public function get_title() {
		return __( 'P10 Current Profile Gallery', 'perfect10' ); }
	protected function register_controls() {
		$this->start_controls_section( 'gallery', array( 'label' => __( 'Linked Media Library images', 'perfect10' ) ) );
		$this->columns();
		$this->add_control(
			'size',
			array(
				'label'   => __( 'Image size', 'perfect10' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'options' => array(
					'p10-card'    => 'Profile card',
					'p10-gallery' => 'Gallery',
					'full'        => 'Full',
				),
				'default' => 'p10-gallery',
			)
		);
		$this->add_control(
			'link',
			array(
				'label' => __( 'Link to full image', 'perfect10' ),
				'type'  => \Elementor\Controls_Manager::SWITCHER,
			)
		);
		$this->end_controls_section();
	}
	protected function render() {
		$id = $this->current_profile();
		if ( ! $id ) {
			echo '<p>' . esc_html__( 'Select a published profile preview to display its gallery.', 'perfect10' ) . '</p>';
			return; }
		$s        = $this->get_settings_for_display();
		$img_size = ! empty( $s['size'] ) ? $s['size'] : 'p10-gallery';
		$post_obj = get_post( $id );
		$items    = array();
		foreach ( (array) p10_profile_gallery( $post_obj ) as $image ) {
			if ( p10_attachment_has_file( $image ) ) {
				$url   = wp_get_attachment_image_url( $image, $img_size );
				$thumb = wp_get_attachment_image_url( $image, 'p10-thumb' );
				$zoom  = wp_get_attachment_image_url( $image, 'full' );
				if ( $url ) {
					$items[] = array(
						'full'  => $url,
						'thumb' => $thumb ? $thumb : $url,
						'zoom'  => $zoom ? $zoom : $url,
						'alt'   => get_the_title( $post_obj ),
					);
				}
			}
		}
		if ( count( $items ) < 2 && function_exists( 'p10_bundled_profile_gallery_urls' ) ) {
			foreach ( p10_bundled_profile_gallery_urls( $post_obj, 4 ) as $b_url ) {
				$items[] = array(
					'full'  => $b_url,
					'thumb' => $b_url,
					'zoom'  => $b_url,
					'alt'   => get_the_title( $post_obj ),
				);
			}
		}
		if ( empty( $items ) ) {
			return;
		}
		?>
		<div class="p10-gallery" data-p10-gallery tabindex="0" role="group" aria-label="<?php esc_attr_e( 'Profile gallery', 'perfect10' ); ?>">
			<div class="p10-gallery__main" data-p10-gallery-main>
				<img class="p10-gallery__stage-img" data-p10-gallery-stage src="<?php echo esc_url( $items[0]['full'] ); ?>" data-zoom="<?php echo esc_url( $items[0]['zoom'] ); ?>" alt="<?php echo esc_attr( $items[0]['alt'] ); ?>">
				<?php if ( count( $items ) > 1 ) : ?>
					<button type="button" class="p10-gallery__nav p10-gallery__nav--prev" data-p10-gallery-prev aria-label="<?php esc_attr_e( 'Previous image', 'perfect10' ); ?>">←</button>
					<button type="button" class="p10-gallery__nav p10-gallery__nav--next" data-p10-gallery-next aria-label="<?php esc_attr_e( 'Next image', 'perfect10' ); ?>">→</button>
				<?php endif; ?>
				<button type="button" class="p10-gallery__zoom" data-p10-gallery-zoom aria-label="<?php esc_attr_e( 'View fullscreen photo', 'perfect10' ); ?>">
					<span aria-hidden="true">⤢</span>
					<span><?php esc_html_e( 'Enlarge', 'perfect10' ); ?></span>
				</button>
			</div>
			<?php if ( count( $items ) > 1 ) : ?>
				<div class="p10-gallery__thumbs">
					<?php foreach ( $items as $idx => $item ) : ?>
						<button
							type="button"
							class="p10-gallery__thumb<?php echo 0 === $idx ? ' is-active' : ''; ?>"
							data-p10-gallery-thumb
							data-index="<?php echo esc_attr( $idx ); ?>"
							data-full="<?php echo esc_url( $item['full'] ); ?>"
							data-zoom="<?php echo esc_url( $item['zoom'] ); ?>"
							data-alt="<?php echo esc_attr( $item['alt'] ); ?>"
							aria-current="<?php echo 0 === $idx ? 'true' : 'false'; ?>">
							<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="" loading="lazy">
						</button>
					<?php endforeach; ?>
				</div>
				<p class="p10-small" data-p10-gallery-count><?php echo esc_html( '1 / ' . count( $items ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
