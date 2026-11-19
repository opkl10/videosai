<?php
/**
 * The Statista Chart widget for Elementor.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts\Widgets;

use StatistaCharts\Block;
use StatistaCharts\Config;
use StatistaCharts\Elementor_Support;
use StatistaCharts\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Draws a chart, or a single statistic, from data typed one line per item.
 */
class Chart_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget slug.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'statista_chart';
	}

	/**
	 * Widget title shown in the panel.
	 *
	 * @return string Title.
	 */
	public function get_title() {
		return __( 'Statista Chart', 'statista-charts' );
	}

	/**
	 * Panel icon.
	 *
	 * @return string Icon class.
	 */
	public function get_icon() {
		return 'eicon-chart-bar';
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
		return array( 'chart', 'graph', 'statistics', 'data', 'csv' );
	}

	/**
	 * Styles this widget needs, borrowed from the block.
	 *
	 * @return string[] Handles.
	 */
	public function get_style_depends() {
		return array_filter( array( Elementor_Support::style_handle( Block::NAME ) ) );
	}

	/**
	 * Scripts this widget needs, borrowed from the block.
	 *
	 * @return string[] Handles.
	 */
	public function get_script_depends() {
		return array_filter( array( Elementor_Support::script_handle( Block::NAME ) ) );
	}

	/**
	 * Builds the panel controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_data',
			array(
				'label' => __( 'Data', 'statista-charts' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => __( 'Chart type', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'bar',
				'options' => array(
					'bar'      => __( 'Bars', 'statista-charts' ),
					'line'     => __( 'Lines', 'statista-charts' ),
					'area'     => __( 'Area', 'statista-charts' ),
					'pie'      => __( 'Pie', 'statista-charts' ),
					'doughnut' => __( 'Doughnut', 'statista-charts' ),
					'stat'     => __( 'Single number', 'statista-charts' ),
				),
			)
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Title', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'default'     => '',
			)
		);

		$this->add_control(
			'data',
			array(
				'label'       => __( 'Your data — one line per item', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 8,
				'default'     => "January: 120\nFebruary: 138\nMarch: 151\nApril: 166",
				'placeholder' => "January: 120\nFebruary: 138",
				'description' => __( 'Write a name and a number on each line, for example "January: 120". A pasted CSV table works too.', 'statista-charts' ),
				'condition'   => array( 'type!' => 'stat' ),
			)
		);

		$this->add_control(
			'statValue',
			array(
				'label'     => __( 'The number', 'statista-charts' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'default'   => '27.4%',
				'condition' => array( 'type' => 'stat' ),
			)
		);

		$this->add_control(
			'statLabel',
			array(
				'label'       => __( 'What it measures', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'condition'   => array( 'type' => 'stat' ),
			)
		);

		$this->add_control(
			'statDelta',
			array(
				'label'     => __( 'Change', 'statista-charts' ),
				'type'      => \Elementor\Controls_Manager::TEXT,
				'condition' => array( 'type' => 'stat' ),
			)
		);

		$this->add_control(
			'statTrend',
			array(
				'label'     => __( 'Direction', 'statista-charts' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'up',
				'options'   => array(
					'up'   => __( 'Up', 'statista-charts' ),
					'down' => __( 'Down', 'statista-charts' ),
					'flat' => __( 'Unchanged', 'statista-charts' ),
				),
				'condition' => array( 'type' => 'stat' ),
			)
		);

		$this->add_control(
			'statCaption',
			array(
				'label'       => __( 'Note under the number', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
				'condition'   => array( 'type' => 'stat' ),
			)
		);

		$this->add_control(
			'source',
			array(
				'label'       => __( 'Data source', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_display',
			array(
				'label' => __( 'Display options', 'statista-charts' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$switches = array(
			'showLegend' => array( __( 'Show the legend', 'statista-charts' ), 'yes' ),
			'showValues' => array( __( 'Show value labels', 'statista-charts' ), '' ),
			'showGrid'   => array( __( 'Show grid lines', 'statista-charts' ), 'yes' ),
			'showTable'  => array( __( 'Show the data table', 'statista-charts' ), '' ),
			'stacked'    => array( __( 'Stack the series', 'statista-charts' ), '' ),
			'smooth'     => array( __( 'Smooth the lines', 'statista-charts' ), 'yes' ),
		);

		foreach ( $switches as $key => $switch ) {
			$this->add_control(
				$key,
				array(
					'label'     => $switch[0],
					'type'      => \Elementor\Controls_Manager::SWITCHER,
					'default'   => $switch[1],
					'condition' => array( 'type!' => 'stat' ),
				)
			);
		}

		$this->add_control(
			'height',
			array(
				'label'      => __( 'Height (pixels)', 'statista-charts' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min'  => 160,
						'max'  => 800,
						'step' => 10,
					),
				),
				'default'    => array(
					'unit' => 'px',
					'size' => 360,
				),
				'condition'  => array( 'type!' => 'stat' ),
			)
		);

		$this->add_control(
			'decimals',
			array(
				'label'   => __( 'Decimal places', 'statista-charts' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 0,
				'max'     => 4,
				'default' => 0,
			)
		);

		$this->add_control(
			'prefix',
			array(
				'label'       => __( 'Prefix', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '₪',
			)
		);

		$this->add_control(
			'suffix',
			array(
				'label'       => __( 'Suffix', 'statista-charts' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => '%',
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

		$raw = array(
			'type'        => isset( $settings['type'] ) ? $settings['type'] : 'bar',
			'title'       => isset( $settings['title'] ) ? $settings['title'] : '',
			'showTitle'   => true,
			'source'      => isset( $settings['source'] ) ? $settings['source'] : '',
			'showSource'  => true,
			'showChart'   => true,
			'height'      => isset( $settings['height']['size'] ) ? $settings['height']['size'] : 360,
			'decimals'    => isset( $settings['decimals'] ) ? $settings['decimals'] : 0,
			'prefix'      => isset( $settings['prefix'] ) ? $settings['prefix'] : '',
			'suffix'      => isset( $settings['suffix'] ) ? $settings['suffix'] : '',
			'palette'     => isset( $settings['palette'] ) ? $settings['palette'] : 'statista',
			'theme'       => isset( $settings['theme'] ) ? $settings['theme'] : 'light',
			'direction'   => isset( $settings['direction'] ) ? $settings['direction'] : 'auto',
			'statValue'   => isset( $settings['statValue'] ) ? $settings['statValue'] : '',
			'statLabel'   => isset( $settings['statLabel'] ) ? $settings['statLabel'] : '',
			'statDelta'   => isset( $settings['statDelta'] ) ? $settings['statDelta'] : '',
			'statTrend'   => isset( $settings['statTrend'] ) ? $settings['statTrend'] : 'up',
			'statCaption' => isset( $settings['statCaption'] ) ? $settings['statCaption'] : '',
		);

		foreach ( array( 'showLegend', 'showValues', 'showGrid', 'showTable', 'stacked', 'smooth' ) as $key ) {
			$raw[ $key ] = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		}

		$parsed        = Config::parse_data( isset( $settings['data'] ) ? $settings['data'] : '' );
		$raw['labels'] = $parsed['labels'];
		$raw['series'] = $parsed['series'];

		echo Renderer::render( Config::sanitize( $raw ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the renderer.
	}
}
