<?php
/**
 * Settings storage, access and sanitization.
 *
 * @package SocialHub
 */

namespace SocialHub;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the single plugin option.
 */
class Settings {

	const OPTION = 'social_hub_settings';
	const GROUP  = 'social_hub_settings_group';

	/**
	 * Runtime cache of the merged settings.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'auto_share'               => true,
			'share_delay'              => 30,
			'post_types'               => array( 'post' ),
			'message_template'         => "{title}\n\n{excerpt}\n\n{url}",
			'delete_data_on_uninstall' => false,
			'facebook'                 => array(
				'enabled'      => false,
				'page_id'      => '',
				'access_token' => '',
				'api_version'  => Providers\Facebook::DEFAULT_API_VERSION,
				'post_format'  => 'link',
			),
			'telegram'                 => array(
				'enabled'         => false,
				'bot_token'       => '',
				'chat_id'         => '',
				'disable_preview' => false,
			),
			'buttons'                  => array(
				'enabled'     => true,
				'networks'    => array( 'facebook', 'x', 'whatsapp', 'telegram', 'linkedin', 'email', 'copy' ),
				'position'    => 'after',
				'post_types'  => array( 'post' ),
				'heading'     => '',
				'style'       => 'filled',
				'show_labels' => true,
			),
			'open_graph'               => array(
				'enabled'             => true,
				'respect_seo_plugins' => true,
				'default_image'       => 0,
				'fb_app_id'           => '',
				'twitter_site'        => '',
				'twitter_card'        => 'summary_large_image',
			),
		);
	}

	/**
	 * Settings keys grouped by admin tab, used to save one tab without wiping the others.
	 *
	 * @return array<string, string[]>
	 */
	public static function sections() {
		return array(
			'general'  => array( 'auto_share', 'share_delay', 'post_types', 'message_template', 'delete_data_on_uninstall' ),
			'facebook' => array( 'facebook' ),
			'telegram' => array( 'telegram' ),
			'buttons'  => array( 'buttons' ),
			'preview'  => array( 'open_graph' ),
		);
	}

	/**
	 * All settings, merged over the defaults.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = self::merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}

		return self::$cache;
	}

	/**
	 * Returns a single setting, with support for "group.key" paths.
	 *
	 * @param string $key      Setting path, e.g. "facebook.page_id".
	 * @param mixed  $fallback Value returned when the path does not exist.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$value = self::all();

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $fallback;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Persists a full settings array.
	 *
	 * @param array $settings Settings to store.
	 * @return void
	 */
	public static function update( array $settings ) {
		update_option( self::OPTION, $settings );
		self::flush();
	}

	/**
	 * Clears the runtime cache.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Recursively merges stored values over defaults.
	 *
	 * @param array $defaults Default values.
	 * @param array $stored   Stored values.
	 * @return array
	 */
	private static function merge( array $defaults, array $stored ) {
		foreach ( $stored as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_array( $value ) && ! self::is_list( $defaults[ $key ] ) ) {
				$defaults[ $key ] = self::merge( $defaults[ $key ], $value );
				continue;
			}

			$defaults[ $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Whether an array is a plain list (sequential integer keys).
	 *
	 * @param array $value Array to check.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/**
	 * Sanitizes a submitted settings form.
	 *
	 * Only the keys belonging to the submitted tab are touched so the other
	 * tabs keep their stored values.
	 *
	 * @param mixed $input Raw form input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$settings = self::all();

		if ( ! is_array( $input ) ) {
			return $settings;
		}

		$sections = self::sections();
		$section  = isset( $input['_section'] ) ? sanitize_key( $input['_section'] ) : '';
		$keys     = isset( $sections[ $section ] ) ? $sections[ $section ] : array_keys( self::defaults() );

		foreach ( $keys as $key ) {
			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;

			switch ( $key ) {
				case 'auto_share':
				case 'delete_data_on_uninstall':
					$settings[ $key ] = ! empty( $value );
					break;

				case 'share_delay':
					$settings[ $key ] = min( 3600, max( 0, (int) $value ) );
					break;

				case 'post_types':
					$settings[ $key ] = self::sanitize_post_types( $value );
					break;

				case 'message_template':
					$settings[ $key ] = self::sanitize_template( $value );
					break;

				case 'facebook':
					$settings['facebook'] = self::sanitize_facebook( (array) $value, $settings['facebook'] );
					break;

				case 'telegram':
					$settings['telegram'] = self::sanitize_telegram( (array) $value, $settings['telegram'] );
					break;

				case 'buttons':
					$settings['buttons'] = self::sanitize_buttons( (array) $value );
					break;

				case 'open_graph':
					$settings['open_graph'] = self::sanitize_open_graph( (array) $value );
					break;
			}
		}

		self::$cache = null;

		return $settings;
	}

	/**
	 * Keeps only registered public post types.
	 *
	 * @param mixed $value Submitted post types.
	 * @return string[]
	 */
	private static function sanitize_post_types( $value ) {
		$allowed = array_keys( get_post_types( array( 'public' => true ), 'names' ) );
		$value   = array_map( 'sanitize_key', (array) $value );

		return array_values( array_intersect( $value, $allowed ) );
	}

	/**
	 * Normalises a message template.
	 *
	 * @param mixed $value Submitted template.
	 * @return string
	 */
	private static function sanitize_template( $value ) {
		$value = is_string( $value ) ? $value : '';
		$value = str_replace( "\r\n", "\n", $value );

		return trim( wp_strip_all_tags( $value ) );
	}

	/**
	 * Sanitizes the Facebook group, keeping the stored token when the field is left blank.
	 *
	 * @param array $value   Submitted values.
	 * @param array $current Stored values.
	 * @return array
	 */
	private static function sanitize_facebook( array $value, array $current ) {
		$api_version = isset( $value['api_version'] ) ? trim( (string) $value['api_version'] ) : '';

		return array(
			'enabled'      => ! empty( $value['enabled'] ),
			'page_id'      => isset( $value['page_id'] ) ? preg_replace( '/[^0-9A-Za-z._-]/', '', (string) $value['page_id'] ) : '',
			'access_token' => self::sanitize_secret( $value, 'access_token', isset( $current['access_token'] ) ? $current['access_token'] : '' ),
			'api_version'  => preg_match( '/^v\d+\.\d+$/', $api_version ) ? $api_version : Providers\Facebook::DEFAULT_API_VERSION,
			'post_format'  => isset( $value['post_format'] ) && 'photo' === $value['post_format'] ? 'photo' : 'link',
		);
	}

	/**
	 * Sanitizes the Telegram group, keeping the stored token when the field is left blank.
	 *
	 * @param array $value   Submitted values.
	 * @param array $current Stored values.
	 * @return array
	 */
	private static function sanitize_telegram( array $value, array $current ) {
		return array(
			'enabled'         => ! empty( $value['enabled'] ),
			'bot_token'       => self::sanitize_secret( $value, 'bot_token', isset( $current['bot_token'] ) ? $current['bot_token'] : '' ),
			'chat_id'         => isset( $value['chat_id'] ) ? preg_replace( '/[^0-9A-Za-z@_-]/', '', (string) $value['chat_id'] ) : '',
			'disable_preview' => ! empty( $value['disable_preview'] ),
		);
	}

	/**
	 * Sanitizes the share buttons group.
	 *
	 * @param array $value Submitted values.
	 * @return array
	 */
	private static function sanitize_buttons( array $value ) {
		$positions = array( 'after', 'before', 'both', 'manual' );
		$styles    = array( 'filled', 'outline' );
		$networks  = array_map( 'sanitize_key', isset( $value['networks'] ) ? (array) $value['networks'] : array() );
		$position  = isset( $value['position'] ) ? sanitize_key( $value['position'] ) : 'after';
		$style     = isset( $value['style'] ) ? sanitize_key( $value['style'] ) : 'filled';

		return array(
			'enabled'     => ! empty( $value['enabled'] ),
			'networks'    => array_values( array_intersect( $networks, array_keys( Share_Buttons::networks() ) ) ),
			'position'    => in_array( $position, $positions, true ) ? $position : 'after',
			'post_types'  => self::sanitize_post_types( isset( $value['post_types'] ) ? $value['post_types'] : array() ),
			'heading'     => isset( $value['heading'] ) ? sanitize_text_field( (string) $value['heading'] ) : '',
			'style'       => in_array( $style, $styles, true ) ? $style : 'filled',
			'show_labels' => ! empty( $value['show_labels'] ),
		);
	}

	/**
	 * Sanitizes the link preview group.
	 *
	 * @param array $value Submitted values.
	 * @return array
	 */
	private static function sanitize_open_graph( array $value ) {
		$cards = array( 'summary_large_image', 'summary' );
		$card  = isset( $value['twitter_card'] ) ? sanitize_key( $value['twitter_card'] ) : 'summary_large_image';
		$site  = isset( $value['twitter_site'] ) ? ltrim( sanitize_text_field( (string) $value['twitter_site'] ), '@' ) : '';

		return array(
			'enabled'             => ! empty( $value['enabled'] ),
			'respect_seo_plugins' => ! empty( $value['respect_seo_plugins'] ),
			'default_image'       => isset( $value['default_image'] ) ? absint( $value['default_image'] ) : 0,
			'fb_app_id'           => isset( $value['fb_app_id'] ) ? preg_replace( '/\D/', '', (string) $value['fb_app_id'] ) : '',
			'twitter_site'        => preg_replace( '/[^0-9A-Za-z_]/', '', $site ),
			'twitter_card'        => in_array( $card, $cards, true ) ? $card : 'summary_large_image',
		);
	}

	/**
	 * Returns the submitted secret, or the stored one when the field was left blank.
	 *
	 * @param array  $value   Submitted group values.
	 * @param string $key     Secret key.
	 * @param string $current Stored secret.
	 * @return string
	 */
	private static function sanitize_secret( array $value, $key, $current ) {
		if ( ! empty( $value[ 'clear_' . $key ] ) ) {
			return '';
		}

		$submitted = isset( $value[ $key ] ) ? trim( (string) $value[ $key ] ) : '';

		if ( '' === $submitted ) {
			return (string) $current;
		}

		return preg_replace( '/[^\x21-\x7E]/', '', $submitted );
	}
}
