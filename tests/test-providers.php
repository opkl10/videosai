<?php
/**
 * Provider tests.
 *
 * @package SocialHub
 */

use SocialHub\Providers;
use SocialHub\Providers\Facebook;
use SocialHub\Providers\Telegram;
use SocialHub\Settings;

sh_test(
	'Facebook can post the featured image instead of a link',
	static function () {
		Settings::update(
			array_replace_recursive(
				Settings::defaults(),
				array(
					'facebook' => array(
						'enabled'      => true,
						'page_id'      => '555',
						'access_token' => 'page-token',
						'post_format'  => 'photo',
					),
				)
			)
		);

		sh_queue_http(
			array(
				'id'      => '900',
				'post_id' => '555_901',
			)
		);

		$result = ( new Facebook() )->publish(
			array(
				'message'   => 'Look at this',
				'url'       => 'https://example.com/post',
				'image_url' => 'https://example.com/image.jpg',
			)
		);

		$request = $GLOBALS['sh_http_log'][0];

		sh_assert_contains( '/v26.0/555/photos', $request['url'], 'the photos endpoint is used' );
		sh_assert_same( 'https://example.com/image.jpg', $request['args']['body']['url'], 'the image is sent' );
		sh_assert_contains( 'https://example.com/post', $request['args']['body']['caption'], 'the caption keeps the permalink' );
		sh_assert_same( 'https://www.facebook.com/555/posts/901', $result['url'], 'post_id wins over the photo id' );
	}
);

sh_test(
	'Facebook refuses to publish without credentials',
	static function () {
		$result = ( new Facebook() )->publish( array( 'message' => 'Hi' ) );

		sh_assert_true( is_wp_error( $result ), 'an error is returned' );
		sh_assert_same( 0, count( $GLOBALS['sh_http_log'] ), 'no request is attempted' );
	}
);

sh_test(
	'the Facebook connection test returns the Page name',
	static function () {
		Settings::update(
			array_replace_recursive(
				Settings::defaults(),
				array(
					'facebook' => array(
						'page_id'      => '555',
						'access_token' => 'page-token',
					),
				)
			)
		);

		sh_queue_http(
			array(
				'id'   => '555',
				'name' => 'My Page',
			)
		);

		sh_assert_same( 'My Page', ( new Facebook() )->test(), 'the Page name is returned' );
	}
);

sh_test(
	'Telegram sends the message and builds a t.me link',
	static function () {
		Settings::update(
			array_replace_recursive(
				Settings::defaults(),
				array(
					'telegram' => array(
						'enabled'   => true,
						'bot_token' => '123:abc',
						'chat_id'   => '@mychannel',
					),
				)
			)
		);

		sh_queue_http(
			array(
				'ok'     => true,
				'result' => array(
					'message_id' => 42,
					'chat'       => array( 'username' => 'mychannel' ),
				),
			)
		);

		$result  = ( new Telegram() )->publish(
			array(
				'message' => 'New article',
				'url'     => 'https://example.com/post',
			)
		);
		$request = $GLOBALS['sh_http_log'][0];

		sh_assert_contains( 'https://api.telegram.org/bot123:abc/sendMessage', $request['url'], 'the bot endpoint is used' );
		sh_assert_same( '@mychannel', $request['args']['body']['chat_id'], 'the chat id is sent' );
		sh_assert_contains( 'https://example.com/post', $request['args']['body']['text'], 'the permalink is appended to the text' );
		sh_assert_same( 'https://t.me/mychannel/42', $result['url'], 'the message link is built' );
	}
);

sh_test(
	'Telegram reports the API description on failure',
	static function () {
		Settings::update(
			array_replace_recursive(
				Settings::defaults(),
				array(
					'telegram' => array(
						'enabled'   => true,
						'bot_token' => '123:abc',
						'chat_id'   => '@mychannel',
					),
				)
			)
		);

		sh_queue_http(
			array(
				'ok'          => false,
				'description' => 'Bad Request: chat not found',
			),
			400
		);

		$result = ( new Telegram() )->publish( array( 'message' => 'Hi' ) );

		sh_assert_true( is_wp_error( $result ), 'an error is returned' );
		sh_assert_same( 'Bad Request: chat not found', $result->get_error_message(), 'the API description is surfaced' );
	}
);

sh_test(
	'long messages are shortened to the network limit',
	static function () {
		Settings::update(
			array_replace_recursive(
				Settings::defaults(),
				array(
					'telegram' => array(
						'enabled'   => true,
						'bot_token' => '123:abc',
						'chat_id'   => '@mychannel',
					),
				)
			)
		);

		sh_queue_http(
			array(
				'ok'     => true,
				'result' => array( 'message_id' => 1 ),
			)
		);

		( new Telegram() )->publish( array( 'message' => str_repeat( 'word ', 1200 ) ) );

		$text = $GLOBALS['sh_http_log'][0]['args']['body']['text'];

		sh_assert_true( mb_strlen( $text ) <= 4000, 'the text fits the Telegram limit' );
		sh_assert_contains( '…', $text, 'the cut is marked with an ellipsis' );
	}
);

sh_test(
	'the registry exposes both networks',
	static function () {
		sh_assert_same( array( 'facebook', 'telegram' ), array_keys( Providers::all() ), 'both providers are registered' );
		sh_assert_true( Providers::get( 'facebook' ) instanceof Facebook, 'providers can be fetched by id' );
		sh_assert_same( null, Providers::get( 'myspace' ), 'unknown ids return null' );
	}
);
