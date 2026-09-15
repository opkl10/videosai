<?php
/**
 * Plugin bootstrap.
 *
 * @package SocialHub
 */

namespace SocialHub;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin components together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Publisher instance.
	 *
	 * @var Publisher|null
	 */
	private $publisher = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers every hook the plugin needs.
	 *
	 * @return void
	 */
	public function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'site_transient_update_plugins', array( $this, 'block_foreign_updates' ) );

		$this->publisher = new Publisher();
		$this->publisher->register();

		( new Open_Graph() )->register();
		( new Share_Buttons() )->register();

		if ( is_admin() ) {
			( new Admin\Admin() )->register();
		}
	}

	/**
	 * Returns the publisher used for sharing posts.
	 *
	 * @return Publisher
	 */
	public function publisher() {
		if ( null === $this->publisher ) {
			$this->publisher = new Publisher();
		}

		return $this->publisher;
	}

	/**
	 * Loads bundled translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'social-hub', false, dirname( plugin_basename( SOCIAL_HUB_FILE ) ) . '/languages' );
	}

	/**
	 * Drops update offers aimed at this plugin.
	 *
	 * The social-hub slug belongs to an unrelated plugin on WordPress.org, and
	 * WordPress matches updates by folder name, so an auto-update would replace
	 * this plugin with that one.
	 *
	 * @param mixed $updates Update transient value.
	 * @return mixed
	 */
	public function block_foreign_updates( $updates ) {
		if ( ! is_object( $updates ) ) {
			return $updates;
		}

		$basename = plugin_basename( SOCIAL_HUB_FILE );

		unset( $updates->response[ $basename ], $updates->no_update[ $basename ] );

		return $updates;
	}

	/**
	 * Capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return (string) apply_filters( 'social_hub_capability', 'manage_options' );
	}

	/**
	 * Stores default settings on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}

	/**
	 * Clears pending share events on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Publisher::CRON_HOOK );
	}
}
