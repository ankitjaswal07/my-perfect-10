<?php
/**
 * Elementor widget: Homepage Blogs / Editorial Journal grid.
 *
 * @package Perfect10
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Blogs widget.
 */
class P10_Elementor_Blogs extends P10_Elementor_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'p10-blogs';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Perfect 10 · Blogs / Journal', 'perfect10' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-post-list';
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'blogs_content',
			array(
				'label' => __( 'Blogs / Journal', 'perfect10' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'content_source',
			array(
				'label'   => __( 'Heading source', 'perfect10' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'theme',
				'options' => array(
					'theme'  => __( 'Perfect 10 Customizer', 'perfect10' ),
					'custom' => __( 'Override for this page', 'perfect10' ),
				),
			)
		);

		$this->add_control(
			'number',
			array(
				'label'     => __( 'Section number', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => '06',
				'condition' => array( 'content_source' => 'custom' ),
			)
		);

		$this->add_control(
			'kicker',
			array(
				'label'     => __( 'Kicker', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Editorial Journal', 'perfect10' ),
				'condition' => array( 'content_source' => 'custom' ),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'     => __( 'Heading', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Latest from the', 'perfect10' ),
				'condition' => array( 'content_source' => 'custom' ),
			)
		);

		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Italic accent', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Journal', 'perfect10' ),
				'condition' => array( 'content_source' => 'custom' ),
			)
		);

		$this->add_control(
			'action',
			array(
				'label'     => __( 'Action link label', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'View all articles', 'perfect10' ),
				'condition' => array( 'content_source' => 'custom' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Articles to display', 'perfect10' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'default' => 3,
				'min'     => 1,
				'max'     => 9,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Columns', 'perfect10' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '3',
				'options' => array(
					'2' => '2',
					'3' => '3',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render.
	 */
	protected function render() {
		$s    = $this->get_settings_for_display();
		$args = array(
			'force_show' => true,
			'count'      => ! empty( $s['limit'] ) ? (int) $s['limit'] : (int) p10_option( 'blogs_count', 3 ),
			'columns'    => ! empty( $s['columns'] ) ? (int) $s['columns'] : 3,
		);

		if ( isset( $s['content_source'] ) && 'custom' === $s['content_source'] ) {
			$args['number'] = isset( $s['number'] ) ? (string) $s['number'] : '06';
			$args['kicker'] = isset( $s['kicker'] ) ? (string) $s['kicker'] : '';
			$args['title']  = isset( $s['title'] ) ? (string) $s['title'] : '';
			$args['accent'] = isset( $s['accent'] ) ? (string) $s['accent'] : '';
			$args['action'] = isset( $s['action'] ) ? (string) $s['action'] : '';
		}

		get_template_part( 'template-parts/blogs', null, $args );
	}
}
