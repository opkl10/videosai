<?php
/**
 * Telegram channel provider.
 *
 * @package SocialHub
 */

namespace SocialHub\Providers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes to a Telegram channel or group through a bot.
 */
class Telegram extends Provider {

	const API_HOST = 'https://api.telegram.org';

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function id() {
		return 'telegram';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function label() {
		return __( 'Telegram', 'social-hub' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== (string) $this->setting( 'bot_token' ) && '' !== (string) $this->setting( 'chat_id' );
	}

	/**
	 * Telegram rejects messages longer than 4096 characters.
	 *
	 * @return int
	 */
	public function max_message_length() {
		return 4000;
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
				__( 'Add a Telegram bot token and chat ID before sharing.', 'social-hub' )
			);
		}

		$url  = isset( $payload['url'] ) ? (string) $payload['url'] : '';
		$text = $this->trim_message( (string) $payload['message'] );

		if ( '' !== $url && false === strpos( $text, $url ) ) {
			$text = trim( $text . "\n\n" . $url );
		}

		$response = $this->request(
			$this->endpoint( 'sendMessage' ),
			array(
				'method' => 'POST',
				'body'   => array(
					'chat_id'                  => (string) $this->setting( 'chat_id' ),
					'text'                     => $text,
					'disable_web_page_preview' => $this->setting( 'disable_preview' ) ? 'true' : 'false',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['ok'] ) || empty( $response['result']['message_id'] ) ) {
			return new WP_Error(
				'social_hub_missing_id',
				__( 'Telegram accepted the request but did not return a message ID.', 'social-hub' )
			);
		}

		$message_id = (string) $response['result']['message_id'];
		$username   = isset( $response['result']['chat']['username'] ) ? (string) $response['result']['chat']['username'] : '';

		return array(
			'id'  => $message_id,
			'url' => '' !== $username ? 'https://t.me/' . rawurlencode( $username ) . '/' . rawurlencode( $message_id ) : '',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string|WP_Error
	 */
	public function test() {
		if ( '' === (string) $this->setting( 'bot_token' ) ) {
			return new WP_Error( 'social_hub_not_configured', __( 'Add a Telegram bot token first.', 'social-hub' ) );
		}

		$bot = $this->request( $this->endpoint( 'getMe' ) );

		if ( is_wp_error( $bot ) ) {
			return $bot;
		}

		$bot_name = isset( $bot['result']['username'] ) ? '@' . $bot['result']['username'] : __( 'unknown bot', 'social-hub' );
		$chat_id  = (string) $this->setting( 'chat_id' );

		if ( '' === $chat_id ) {
			return $bot_name;
		}

		$chat = $this->request(
			add_query_arg( 'chat_id', rawurlencode( $chat_id ), $this->endpoint( 'getChat' ) )
		);

		if ( is_wp_error( $chat ) ) {
			return $chat;
		}

		$chat_name = '';

		if ( ! empty( $chat['result']['title'] ) ) {
			$chat_name = (string) $chat['result']['title'];
		} elseif ( ! empty( $chat['result']['username'] ) ) {
			$chat_name = '@' . $chat['result']['username'];
		}

		if ( '' === $chat_name ) {
			return $bot_name;
		}

		return sprintf(
			/* translators: %1$s: bot username, %2$s: chat title. */
			__( '%1$s posting to %2$s', 'social-hub' ),
			$bot_name,
			$chat_name
		);
	}

	/**
	 * Builds a Bot API endpoint URL.
	 *
	 * @param string $method Bot API method name.
	 * @return string
	 */
	public function endpoint( $method ) {
		return self::API_HOST . '/bot' . (string) $this->setting( 'bot_token' ) . '/' . $method;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array $body Decoded response body.
	 * @param int   $code HTTP status code.
	 * @return string
	 */
	protected function error_message( array $body, $code ) {
		if ( ! empty( $body['description'] ) ) {
			return (string) $body['description'];
		}

		return parent::error_message( $body, $code );
	}
}
