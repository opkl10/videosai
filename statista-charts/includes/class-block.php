<?php
/**
 * Gutenberg block registration.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Statista Chart" block and renders it on the front end.
 */
class Block {

	/**
	 * Block name.
	 */
	const NAME = 'statista/chart';

	/**
	 * Hooks the block registration.
	 *
	 * @return void
	 */
	public static function init() {
		$metadata = STATISTA_CHARTS_PATH . 'build/block.json';

		if ( ! file_exists( $metadata ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'build_missing_notice' ) );
			return;
		}

		register_block_type(
			$metadata,
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);

		$editor_handle = generate_block_asset_handle( self::NAME, 'editorScript' );

		wp_set_script_translations( $editor_handle, 'statista-charts', STATISTA_CHARTS_PATH . 'languages' );

		wp_add_inline_script(
			$editor_handle,
			'window.statistaCharts = window.statistaCharts || {}; window.statistaCharts.palettes = ' . wp_json_encode( Config::palettes() ) . ';',
			'before'
		);
	}

	/**
	 * Renders the block on the front end.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Block HTML.
	 */
	public static function render( $attributes ) {
		$config = Config::sanitize( $attributes );

		return Renderer::render( $config, get_block_wrapper_attributes() );
	}

	/**
	 * Warns an administrator when the plugin assets have not been built.
	 *
	 * @return void
	 */
	public static function build_missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Statista Charts: the build directory is missing. Run "npm install && npm run build" inside the plugin folder.', 'statista-charts' )
		);
	}
}
