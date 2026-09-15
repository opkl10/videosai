<?php
/**
 * Admin bootstrap: menu, assets and AJAX endpoints.
 *
 * @package SocialHub
 */

namespace SocialHub\Admin;

use SocialHub\Log;
use SocialHub\Plugin;
use SocialHub\Providers;
use SocialHub\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers everything the plugin shows inside wp-admin.
 */
class Admin {

	const PAGE  = 'social-hub';
	const NONCE = 'social_hub_admin';

	/**
	 * Settings page renderer.
	 *
	 * @var Settings_Page
	 */
	private $settings_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_page = new Settings_Page();
	}

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_social_hub_clear_log', array( $this, 'clear_log' ) );
		add_action( 'wp_ajax_social_hub_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_social_hub_share_now', array( $this, 'ajax_share_now' ) );

		add_filter(
			'plugin_action_links_' . plugin_basename( SOCIAL_HUB_FILE ),
			array( $this, 'action_links' )
		);

		( new Meta_Box() )->register();
	}

	/**
	 * Adds the top level menu entry.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Social Hub', 'social-hub' ),
			__( 'Social Hub', 'social-hub' ),
			Plugin::capability(),
			self::PAGE,
			array( $this->settings_page, 'render' ),
			'dashicons-share',
			81
		);
	}

	/**
	 * Registers the plugin option with the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			Settings::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Loads the admin assets where they are used.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		$is_settings_page = ( 'toplevel_page_' . self::PAGE ) === $hook_suffix;
		$is_editor        = in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_settings_page && ! $is_editor ) {
			return;
		}

		if ( $is_settings_page ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'social-hub-admin',
			SOCIAL_HUB_URL . 'assets/css/admin.css',
			array(),
			SOCIAL_HUB_VERSION
		);

		wp_enqueue_script(
			'social-hub-admin',
			SOCIAL_HUB_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			SOCIAL_HUB_VERSION,
			true
		);

		wp_localize_script(
			'social-hub-admin',
			'socialHubAdmin',
			array(
				'nonce'        => wp_create_nonce( self::NONCE ),
				'testing'      => __( 'Checking…', 'social-hub' ),
				'sharing'      => __( 'Sharing…', 'social-hub' ),
				'genericError' => __( 'The request failed. Please try again.', 'social-hub' ),
				'mediaTitle'   => __( 'Choose a fallback preview image', 'social-hub' ),
				'mediaButton'  => __( 'Use this image', 'social-hub' ),
			)
		);
	}

	/**
	 * Adds a settings shortcut on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
			esc_html__( 'Settings', 'social-hub' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Empties the activity log.
	 *
	 * @return void
	 */
	public function clear_log() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'social-hub' ) );
		}

		check_admin_referer( 'social_hub_clear_log' );
		Log::clear();

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&tab=log&cleared=1' ) );
		exit;
	}

	/**
	 * Verifies a network connection with the stored credentials.
	 *
	 * @return void
	 */
	public function ajax_test_connection() {
		$this->verify_ajax_request( Plugin::capability() );

		$network  = isset( $_POST['network'] ) ? sanitize_key( wp_unslash( $_POST['network'] ) ) : '';
		$provider = Providers::get( $network );

		if ( ! $provider ) {
			wp_send_json_error( array( 'message' => __( 'Unknown network.', 'social-hub' ) ) );
		}

		$result = $provider->test();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %s: connected account name. */
					__( 'Connected: %s', 'social-hub' ),
					$result
				),
			)
		);
	}

	/**
	 * Shares a post on demand from the editor.
	 *
	 * @return void
	 */
	public function ajax_share_now() {
		$this->verify_ajax_request();

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to share this post.', 'social-hub' ) ) );
		}

		if ( 'publish' !== get_post_status( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Publish the post before sharing it.', 'social-hub' ) ) );
		}

		if ( ! Providers::active() ) {
			wp_send_json_error( array( 'message' => __( 'No network is connected yet.', 'social-hub' ) ) );
		}

		$results = Plugin::instance()->publisher()->share( $post_id, array_keys( Providers::active() ), true );

		wp_send_json_success(
			array(
				'message' => Meta_Box::format_results( $results ),
				'html'    => Meta_Box::status_html( $post_id ),
			)
		);
	}

	/**
	 * Shared nonce and capability check for the AJAX endpoints.
	 *
	 * @param string $capability Capability to require, defaults to editing posts.
	 * @return void
	 */
	private function verify_ajax_request( $capability = 'edit_posts' ) {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'social-hub' ) ), 403 );
		}

		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'social-hub' ) ), 403 );
		}
	}
}
