<?php
/**
 * Plugin Name:       Statista Charts
 * Plugin URI:        https://example.com/statista-charts
 * Description:       Beautiful, responsive statistics and charts inside posts — Gutenberg blocks, Elementor widgets and a matching [statista_chart] shortcode.
 * Version:           1.4.13
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Statista Charts
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       statista-charts
 * Domain Path:       /languages
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

define( 'STATISTA_CHARTS_VERSION', '1.4.13' );
define( 'STATISTA_CHARTS_FILE', __FILE__ );
define( 'STATISTA_CHARTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'STATISTA_CHARTS_URL', plugin_dir_url( __FILE__ ) );

require_once STATISTA_CHARTS_PATH . 'includes/class-config.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-renderer.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-block.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-stats.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-dashboard.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-shortcode.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-rest.php';
require_once STATISTA_CHARTS_PATH . 'includes/class-elementor.php';

/**
 * Boot the plugin once WordPress is ready.
 *
 * @return void
 */
function bootstrap() {
	Block::init();
	Stats::init();
	Dashboard::init();
	Shortcode::init();
	Rest::init();
	Elementor_Support::init();
}
add_action( 'init', __NAMESPACE__ . '\\bootstrap' );

/**
 * Load the plugin translations.
 *
 * @return void
 */
function load_textdomain() {
	load_plugin_textdomain( 'statista-charts', false, dirname( plugin_basename( STATISTA_CHARTS_FILE ) ) . '/languages' );
}
add_action( 'init', __NAMESPACE__ . '\\load_textdomain', 1 );
