<?php
/**
 * County / city search panel.
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
 * Search widget.
 */
class P10_Elementor_Search extends P10_Elementor_Widget {

	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'p10-search';
	}

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Perfect 10 · Search Panel', 'perfect10' );
	}

	/**
	 * Icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-search';
	}

	/**
	 * Controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'search_content',
			array(
				'label' => __( 'Search Panel', 'perfect10' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'content_source',
			array(
				'label'   => __( 'Content source', 'perfect10' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'theme',
				'options' => array(
					'theme'  => __( 'Perfect 10 Customizer', 'perfect10' ),
					'custom' => __( 'Override for this page', 'perfect10' ),
				),
			)
		);
		$this->add_control(
			'kicker',
			array(
				'label'     => __( 'Kicker', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array( 'content_source' => 'custom' ),
			)
		);
		$this->add_control(
			'title',
			array(
				'label'     => __( 'Heading', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array( 'content_source' => 'custom' ),
			)
		);
		$this->add_control(
			'accent',
			array(
				'label'     => __( 'Italic accent', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array( 'content_source' => 'custom' ),
			)
		);
		$this->add_control(
			'label',
			array(
				'label'       => __( 'Field prompt', 'perfect10' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'Where are you looking?', 'perfect10' ),
				'condition'   => array( 'content_source' => 'custom' ),
			)
		);
		$this->add_control(
			'region',
			array(
				'label'     => __( 'Region caption', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array( 'content_source' => 'custom' ),
			)
		);
		$this->add_control(
			'button',
			array(
				'label'     => __( 'Button label', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => __( 'Search', 'perfect10' ),
				'condition' => array( 'content_source' => 'custom' ),
			)
		);
		$this->add_control(
			'show_direct',
			array(
				'label'        => __( 'Show direct keyword search input', 'perfect10' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'show_quick_links',
			array(
				'label'        => __( 'Show popular type links', 'perfect10' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);
		$this->add_control(
			'anchor',
			array(
				'label'       => __( 'Anchor ID', 'perfect10' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => 'p10-next',
				'description' => __( 'The hero scroll cue links to #p10-next by default.', 'perfect10' ),
			)
		);
		$this->end_controls_section();

		$this->start_controls_section(
			'search_style',
			array(
				'label' => __( 'Panel Style', 'perfect10' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);
		$this->add_control(
			'panel_bg',
			array(
				'label'     => __( 'Panel background', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .p10-search__panel' => 'background-color:{{VALUE}};',
				),
			)
		);
		$this->add_control(
			'panel_border',
			array(
				'label'     => __( 'Panel border', 'perfect10' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .p10-search__panel' => 'border-color:{{VALUE}};',
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
			'force_show'       => true,
			'anchor'           => isset( $s['anchor'] ) ? sanitize_title( $s['anchor'] ) : 'p10-next',
			'show_direct'      => ! isset( $s['show_direct'] ) || 'yes' === $s['show_direct'],
			'show_quick_links' => ! isset( $s['show_quick_links'] ) || 'yes' === $s['show_quick_links'],
		);

		if ( isset( $s['content_source'] ) && 'custom' === $s['content_source'] ) {
			$args['kicker'] = isset( $s['kicker'] ) ? (string) $s['kicker'] : '';
			$args['title']  = isset( $s['title'] ) ? (string) $s['title'] : '';
			$args['accent'] = isset( $s['accent'] ) ? (string) $s['accent'] : '';
			$args['label']  = isset( $s['label'] ) ? (string) $s['label'] : '';
			$args['region'] = isset( $s['region'] ) ? (string) $s['region'] : '';
			$args['button'] = isset( $s['button'] ) ? (string) $s['button'] : __( 'Search', 'perfect10' );
		}

		get_template_part( 'template-parts/search-panel', null, $args );
	}
}
