<?php
/**
 * Base class for social network providers.
 *
 * @package SocialHub
 */

namespace SocialHub\Providers;

use SocialHub\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A provider knows how to publish a post payload to one network.
 */
abstract class Provider {

	/**
	 * Unique provider id, also used as the settings group key.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human readable name.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Whether the credentials needed to publish are present.
	 *
	 * @return bool
	 */
	abstract public function is_configured();

	/**
	 * Publishes a payload.
	 *
	 * @param array $payload {
	 *     Post payload.
	 *
	 *     @type string $message   Rendered message.
	 *     @type string $url       Permalink.
	 *     @type string $title     Post title.
	 *     @type string $image_url Featured image URL, may be empty.
	 * }
	 * @return array|WP_Error Array with `id` and `url` keys, or an error.
	 */
	abstract public function publish( array $payload );

	/**
	 * Checks the stored credentials against the network.
	 *
	 * @return string|WP_Error Connected account description, or an error.
	 */
	abstract public function test();

	/**
	 * Whether the network is switched on in the settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) $this->setting( 'enabled', false );
	}

	/**
	 * Whether the network should receive automatic shares.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_enabled() && $this->is_configured();
	}

	/**
	 * Maximum message length, or 0 when the network has no meaningful limit.
	 *
	 * @return int
	 */
	public function max_message_length() {
		return 0;
	}

	/**
	 * Reads a setting from this provider's group.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback value.
	 * @return mixed
	 */
	protected function setting( $key, $fallback = '' ) {
		return Settings::get( $this->id() . '.' . $key, $fallback );
	}

	/**
	 * Performs a JSON API request.
	 *
	 * @param string $url  Endpoint URL.
	 * @param array  $args Arguments for wp_remote_request().
	 * @return array|WP_Error Decoded response body, or an error.
	 */
	protected function request( $url, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'method'     => 'GET',
				'timeout'    => 20,
				'user-agent' => 'SocialHub/' . SOCIAL_HUB_VERSION . '; ' . home_url( '/' ),
			)
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'social_hub_invalid_response',
				sprintf(
					/* translators: %1$s: network name, %2$d: HTTP status code. */
					__( '%1$s returned an unreadable response (HTTP %2$d).', 'social-hub' ),
					$this->label(),
					$code
				)
			);
		}

		if ( $code < 200 || $code > 299 ) {
			return new WP_Error(
				'social_hub_api_error',
				$this->error_message( $body, $code ),
				array(
					'status' => $code,
					'body'   => $body,
				)
			);
		}

		return $body;
	}

	/**
	 * Extracts a readable error message from an API response.
	 *
	 * @param array $body Decoded response body.
	 * @param int   $code HTTP status code.
	 * @return string
	 */
	protected function error_message( array $body, $code ) {
		return sprintf(
			/* translators: %1$s: network name, %2$d: HTTP status code. */
			__( '%1$s rejected the request (HTTP %2$d).', 'social-hub' ),
			$this->label(),
			$code
		);
	}

	/**
	 * Shortens a message to the network limit without cutting mid-word.
	 *
	 * @param string $message Message to shorten.
	 * @return string
	 */
	protected function trim_message( $message ) {
		$limit = $this->max_message_length();

		if ( $limit <= 0 || mb_strlen( $message ) <= $limit ) {
			return $message;
		}

		$shortened = mb_substr( $message, 0, $limit - 1 );
		$on_word   = preg_replace( '/\s+\S*$/u', '', $shortened );

		if ( is_string( $on_word ) && mb_strlen( $on_word ) > $limit / 2 ) {
			$shortened = $on_word;
		}

		return rtrim( $shortened ) . '…';
	}
}
