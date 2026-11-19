<?php
/**
 * Elementor support: the same charts and stat cards as widgets.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Elementor category and widgets when Elementor is active.
 */
class Elementor_Support {

	/**
	 * Elementor category slug.
	 */
	const CATEGORY = 'statista-charts';

	/**
	 * Hooks into Elementor, and does nothing at all without it.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( __CLASS__, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_widgets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_preview_script' ) );
	}

	/**
	 * Adds a panel category of our own.
	 *
	 * @param \Elementor\Elements_Manager $elements Elementor elements manager.
	 * @return void
	 */
	public static function register_category( $elements ) {
		$elements->add_category(
			self::CATEGORY,
			array(
				'title' => __( 'Statista Charts', 'statista-charts' ),
				'icon'  => 'eicon-bar-chart',
			)
		);
	}

	/**
	 * Registers the widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets Elementor widgets manager.
	 * @return void
	 */
	public static function register_widgets( $widgets ) {
		require_once STATISTA_CHARTS_PATH . 'includes/elementor/class-chart-widget.php';
		require_once STATISTA_CHARTS_PATH . 'includes/elementor/class-stats-widget.php';

		$widgets->register( new Widgets\Chart_Widget() );
		$widgets->register( new Widgets\Stats_Widget() );
	}

	/**
	 * Redraws charts that Elementor re-renders while editing.
	 *
	 * The front-end runtime listens for this event and picks up any figure it
	 * has not drawn yet.
	 *
	 * @return void
	 */
	public static function enqueue_preview_script() {
		$handle = wp_script_is( 'elementor-frontend', 'registered' ) ? 'elementor-frontend' : '';

		if ( '' === $handle ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			'window.addEventListener( "elementor/frontend/init", function () {
				elementorFrontend.hooks.addAction( "frontend/element_ready/statista_chart.default", function () {
					document.dispatchEvent( new CustomEvent( "statista-charts:refresh" ) );
				} );
			} );'
		);
	}

	/**
	 * The style handle registered by the chart block, reused by the widgets.
	 *
	 * @param string $block Block name.
	 * @return string Handle, or an empty string when the block is missing.
	 */
	public static function style_handle( $block ) {
		return function_exists( 'generate_block_asset_handle' ) ? generate_block_asset_handle( $block, 'style' ) : '';
	}

	/**
	 * The view script handle registered by the chart block.
	 *
	 * @param string $block Block name.
	 * @return string Handle, or an empty string when the block is missing.
	 */
	public static function script_handle( $block ) {
		return function_exists( 'generate_block_asset_handle' ) ? generate_block_asset_handle( $block, 'viewScript' ) : '';
	}

	/**
	 * The palette choices, shaped for an Elementor select control.
	 *
	 * @return array<string, string> Slug => label.
	 */
	public static function palette_options() {
		$options = array();

		foreach ( Config::palettes() as $slug => $palette ) {
			$options[ $slug ] = $palette['label'];
		}

		return $options;
	}

	/**
	 * The theme choices, shaped for an Elementor select control.
	 *
	 * @return array<string, string> Value => label.
	 */
	public static function theme_options() {
		return array(
			'light' => __( 'Light', 'statista-charts' ),
			'dark'  => __( 'Dark', 'statista-charts' ),
			'auto'  => __( 'Follow the reader’s device', 'statista-charts' ),
		);
	}

	/**
	 * The direction choices, shaped for an Elementor select control.
	 *
	 * @return array<string, string> Value => label.
	 */
	public static function direction_options() {
		return array(
			'auto' => __( 'Follow the site', 'statista-charts' ),
			'rtl'  => __( 'Right to left', 'statista-charts' ),
			'ltr'  => __( 'Left to right', 'statista-charts' ),
		);
	}
}
