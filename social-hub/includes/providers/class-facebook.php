<?php
/**
 * Facebook Page provider.
 *
 * @package SocialHub
 */

namespace SocialHub\Providers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes to a Facebook Page through the Graph API.
 */
class Facebook extends Provider {

	const DEFAULT_API_VERSION = 'v26.0';
	const API_HOST            = 'https://graph.facebook.com';

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function id() {
		return 'facebook';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Facebook Page', 'social-hub' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== (string) $this->setting( 'page_id' ) && '' !== (string) $this->setting( 'access_token' );
	}

	/**
	 * Facebook allows very long posts; this keeps them sane.
	 *
	 * @return int
	 */
	public function max_message_length() {
		return 5000;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $payload Post payload.
	 * @return array|WP_Error
	 */
	public function publish( array $payload ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'social_hub_not_configured',
				__( 'Add a Facebook Page ID and access token before sharing.', 'social-hub' )
			);
		}

		$page_id   = (string) $this->setting( 'page_id' );
		$message   = $this->trim_message( (string) $payload['message'] );
		$url       = isset( $payload['url'] ) ? (string) $payload['url'] : '';
		$image_url = isset( $payload['image_url'] ) ? (string) $payload['image_url'] : '';
		$as_photo  = 'photo' === $this->setting( 'post_format' ) && '' !== $image_url;

		if ( $as_photo ) {
			$endpoint = $this->endpoint( $page_id . '/photos' );
			$body     = array(
				'url'     => $image_url,
				'caption' => $this->with_url( $message, $url ),
			);
		} else {
			$endpoint = $this->endpoint( $page_id . '/feed' );
			$body     = array( 'message' => $this->without_url( $message, $url ) );

			if ( '' !== $url ) {
				$body['link'] = $url;
			}
		}

		$body['access_token'] = (string) $this->setting( 'access_token' );

		$response = $this->request(
			$endpoint,
			array(
				'method' => 'POST',
				'body'   => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$post_id = '';

		if ( ! empty( $response['post_id'] ) ) {
			$post_id = (string) $response['post_id'];
		} elseif ( ! empty( $response['id'] ) ) {
			$post_id = (string) $response['id'];
		}

		if ( '' === $post_id ) {
			return new WP_Error(
				'social_hub_missing_id',
				__( 'Facebook accepted the request but did not return a post ID.', 'social-hub' )
			);
		}

		return array(
			'id'  => $post_id,
			'url' => $this->permalink( $post_id ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string|WP_Error
	 */
	public function test() {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'social_hub_not_configured',
				__( 'Add a Facebook Page ID and access token first.', 'social-hub' )
			);
		}

		$response = $this->request(
			add_query_arg(
				array(
					'fields'       => 'id,name',
					'access_token' => rawurlencode( (string) $this->setting( 'access_token' ) ),
				),
				$this->endpoint( (string) $this->setting( 'page_id' ) )
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['name'] ) ) {
			return new WP_Error(
				'social_hub_unexpected_page',
				__( 'The token works but no Page name was returned. Make sure you used a Page access token.', 'social-hub' )
			);
		}

		return (string) $response['name'];
	}

	/**
	 * Builds a Graph API endpoint URL.
	 *
	 * @param string $path Path after the API version.
	 * @return string
	 */
	public function endpoint( $path ) {
		$version = (string) $this->setting( 'api_version', self::DEFAULT_API_VERSION );

		if ( ! preg_match( '/^v\d+\.\d+$/', $version ) ) {
			$version = self::DEFAULT_API_VERSION;
		}

		return self::API_HOST . '/' . $version . '/' . ltrim( $path, '/' );
	}

	/**
	 * Builds the public URL of a created post.
	 *
	 * @param string $post_id Graph post id, usually "pageid_postid".
	 * @return string
	 */
	private function permalink( $post_id ) {
		if ( false !== strpos( $post_id, '_' ) ) {
			list( $page, $story ) = explode( '_', $post_id, 2 );

			return 'https://www.facebook.com/' . rawurlencode( $page ) . '/posts/' . rawurlencode( $story );
		}

		return 'https://www.facebook.com/' . rawurlencode( $post_id );
	}

	/**
	 * Drops the permalink from the message, because it is sent as a link attachment.
	 *
	 * @param string $message Message text.
	 * @param string $url     Permalink.
	 * @return string
	 */
	private function without_url( $message, $url ) {
		if ( '' === $url || false === strpos( $message, $url ) ) {
			return $message;
		}

		return trim( str_replace( $url, '', $message ) );
	}

	/**
	 * Appends the permalink to the message when it is not already there.
	 *
	 * @param string $message Message text.
	 * @param string $url     Permalink.
	 * @return string
	 */
	private function with_url( $message, $url ) {
		if ( '' === $url || false !== strpos( $message, $url ) ) {
			return $message;
		}

		return trim( $message . "\n\n" . $url );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $body Decoded response body.
	 * @param int   $code HTTP status code.
	 * @return string
	 */
	protected function error_message( array $body, $code ) {
		if ( ! empty( $body['error']['error_user_msg'] ) ) {
			return (string) $body['error']['error_user_msg'];
		}

		if ( ! empty( $body['error']['message'] ) ) {
			return (string) $body['error']['message'];
		}

		return parent::error_message( $body, $code );
	}
}
