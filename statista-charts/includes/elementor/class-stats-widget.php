<?php
/**
 * The Statista Stat Cards widget for Elementor.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts\Widgets;

use StatistaCharts\Elementor_Support;
use StatistaCharts\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * A row of cards for percentages and single figures.
 */
class Stats_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'statista_stats';
	}

	/**
	 * Widget title shown in the panel.
	 *
	 * @return string Title.
	 */
	public function get_title() {
		return __( 'Statista Stat Cards', 'statista-charts' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string Icon class.
	 */
	public function get_icon() {
		return 'eicon-counter';
	}

	/**
	 * Panel category.
	 *
	 * @return string[] Categories.
	 */
	public function get_categories() {
		return array( Elementor_Support::CATEGORY );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[] Keywords.
	 */
	public function get_keywords() {
		return array( 'statistics', 'kpi', 'percent', 'number', 'card' );
	}

	/**
	 * Styles this widget needs, borrowed from the block.
	 *
	 * @return string[] Handles.
	 */
	public function get_style_depends() {
		return array_filter( array( Elementor_Support::style_handle( Stats::NAME ) ) );
	}

	/**
	 * Builds the panel controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_cards',
			array(
				'label' => __( 'Cards', 'statista-charts' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'icon',
			array(
				'label'       => __( 'Icon', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '📊',
				'description' => __( 'Any emoji, or leave it empty.', 'statista-charts' ),
			)
		);

		$repeater->add_control(
			'label',
			array(
				'label'       => __( 'What it measures', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'default'     => __( 'Conversion rate', 'statista-charts' ),
			)
		);

		$repeater->add_control(
			'value',
			array(
				'label'       => __( 'Figure', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '27.4%',
				'description' => __( 'A percentage, a sum or any short number. A percentage also draws a bar.', 'statista-charts' ),
			)
		);

		$repeater->add_control(
			'delta',
			array(
				'label'       => __( 'Change', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '+3.2%',
			)
		);

		$repeater->add_control(
			'trend',
			array(
				'label'   => __( 'Direction', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'none',
				'options' => array(
					'none' => __( 'No arrow', 'statista-charts' ),
					'up'   => __( 'Up', 'statista-charts' ),
					'down' => __( 'Down', 'statista-charts' ),
				),
			)
		);

		$repeater->add_control(
			'note',
			array(
				'label'       => __( 'Note', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->add_control(
			'cards',
			array(
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'title_field' => '{{{ label }}} — {{{ value }}}',
				'default'     => array(
					array(
						'icon'  => '📈',
						'label' => __( 'Conversion rate', 'statista-charts' ),
						'value' => '27.4%',
						'delta' => '+3.2%',
						'trend' => 'up',
						'note'  => '',
					),
					array(
						'icon'  => '🛒',
						'label' => __( 'Cart abandonment', 'statista-charts' ),
						'value' => '68%',
						'delta' => '-1.5%',
						'trend' => 'down',
						'note'  => '',
					),
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Style', 'statista-charts' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'   => __( 'Cards per row', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '0',
				'options' => array(
					'0' => __( 'Fit automatically', 'statista-charts' ),
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
			)
		);

		$this->add_control(
			'showBar',
			array(
				'label'   => __( 'Show a bar for percentages', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'palette',
			array(
				'label'   => __( 'Color palette', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'statista',
				'options' => Elementor_Support::palette_options(),
			)
		);

		$this->add_control(
			'theme',
			array(
				'label'   => __( 'Theme', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'light',
				'options' => Elementor_Support::theme_options(),
			)
		);

		$this->add_control(
			'direction',
			array(
				'label'   => __( 'Text direction', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'auto',
				'options' => Elementor_Support::direction_options(),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Renders the widget on the page and in the editor preview.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$data = Stats::sanitize(
			array(
				'cards'     => isset( $settings['cards'] ) && is_array( $settings['cards'] ) ? $settings['cards'] : array(),
				'columns'   => isset( $settings['columns'] ) ? $settings['columns'] : 0,
				'showBar'   => isset( $settings['showBar'] ) ? $settings['showBar'] : '',
				'palette'   => isset( $settings['palette'] ) ? $settings['palette'] : 'statista',
				'theme'     => isset( $settings['theme'] ) ? $settings['theme'] : 'light',
				'direction' => isset( $settings['direction'] ) ? $settings['direction'] : 'auto',
			)
		);

		if ( empty( $data['cards'] ) ) {
			return;
		}

		echo Stats::markup( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the markup builder.
	}
}
