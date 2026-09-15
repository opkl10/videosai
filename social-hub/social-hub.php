<?php
/**
 * Plugin Name:       Social Hub
 * Description:       Auto-publish new posts to a Facebook Page and Telegram, add social share buttons, and output Open Graph tags so shared links look right.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            videosai
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       social-hub
 * Domain Path:       /languages
 *
 * @package SocialHub
 */

defined( 'ABSPATH' ) || exit;

define( 'SOCIAL_HUB_VERSION', '1.0.0' );
define( 'SOCIAL_HUB_FILE', __FILE__ );
define( 'SOCIAL_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SOCIAL_HUB_URL', plugin_dir_url( __FILE__ ) );

require_once SOCIAL_HUB_DIR . 'includes/autoload.php';
require_once SOCIAL_HUB_DIR . 'includes/template-tags.php';

register_activation_hook( __FILE__, array( 'SocialHub\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SocialHub\\Plugin', 'deactivate' ) );

SocialHub\Plugin::instance()->boot();
