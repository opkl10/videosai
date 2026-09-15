<?php
/**
 * Settings sanitization tests.
 *
 * @package SocialHub
 */

use SocialHub\Settings;

sh_test(
	'defaults are returned when nothing is stored',
	static function () {
		sh_assert_same( true, Settings::get( 'auto_share' ), 'auto sharing defaults to on' );
		sh_assert_same( array( 'post' ), Settings::get( 'post_types' ), 'only posts are shared by default' );
		sh_assert_same( 'v26.0', Settings::get( 'facebook.api_version' ), 'the current Graph API version is the default' );
		sh_assert_same( null, Settings::get( 'facebook.nope' ), 'unknown paths return the fallback' );
	}
);

sh_test(
	'saving one tab leaves the other tabs alone',
	static function () {
		Settings::update(
			array_merge(
				Settings::defaults(),
				array(
					'facebook' => array(
						'enabled'      => true,
						'page_id'      => '123',
						'access_token' => 'secret-token',
						'api_version'  => 'v26.0',
						'post_format'  => 'link',
					),
				)
			)
		);

		$saved = Settings::sanitize(
			array(
				'_section'   => 'general',
				'auto_share' => '1',
				'post_types' => array( 'post', 'page' ),
			)
		);

		sh_assert_same( 'secret-token', $saved['facebook']['access_token'], 'the Facebook token survives a general save' );
		sh_assert_same( array( 'post', 'page' ), $saved['post_types'], 'post types are stored' );
	}
);

sh_test(
	'unchecked checkboxes are stored as false',
	static function () {
		$saved = Settings::sanitize( array( '_section' => 'general' ) );

		sh_assert_same( false, $saved['auto_share'], 'a missing checkbox turns the setting off' );
	}
);

sh_test(
	'unknown post types are dropped',
	static function () {
		$saved = Settings::sanitize(
			array(
				'_section'   => 'general',
				'post_types' => array( 'post', 'made_up_type' ),
			)
		);

		sh_assert_same( array( 'post' ), $saved['post_types'], 'only registered public post types are kept' );
	}
);

sh_test(
	'a blank secret keeps the stored one and the clear flag removes it',
	static function () {
		Settings::update(
			array_merge(
				Settings::defaults(),
				array(
					'telegram' => array(
						'enabled'         => true,
						'bot_token'       => 'stored-token',
						'chat_id'         => '@channel',
						'disable_preview' => false,
					),
				)
			)
		);

		$kept = Settings::sanitize(
			array(
				'_section' => 'telegram',
				'telegram' => array(
					'enabled'   => '1',
					'bot_token' => '',
					'chat_id'   => '@channel',
				),
			)
		);

		sh_assert_same( 'stored-token', $kept['telegram']['bot_token'], 'an empty field keeps the saved token' );

		Settings::update( $kept );

		$cleared = Settings::sanitize(
			array(
				'_section' => 'telegram',
				'telegram' => array(
					'enabled'         => '1',
					'bot_token'       => '',
					'clear_bot_token' => '1',
					'chat_id'         => '@channel',
				),
			)
		);

		sh_assert_same( '', $cleared['telegram']['bot_token'], 'the clear checkbox wipes the token' );
	}
);

sh_test(
	'an invalid Graph API version falls back to the default',
	static function () {
		$saved = Settings::sanitize(
			array(
				'_section' => 'facebook',
				'facebook' => array(
					'page_id'     => 'my-page',
					'api_version' => 'latest; drop table',
				),
			)
		);

		sh_assert_same( 'v26.0', $saved['facebook']['api_version'], 'only vNN.N is accepted' );
		sh_assert_same( 'my-page', $saved['facebook']['page_id'], 'page ids keep their safe characters' );
	}
);

sh_test(
	'button settings only accept known networks and positions',
	static function () {
		$saved = Settings::sanitize(
			array(
				'_section' => 'buttons',
				'buttons'  => array(
					'enabled'  => '1',
					'networks' => array( 'facebook', 'myspace', 'copy' ),
					'position' => 'sideways',
				),
			)
		);

		sh_assert_same( array( 'facebook', 'copy' ), $saved['buttons']['networks'], 'unknown networks are dropped' );
		sh_assert_same( 'after', $saved['buttons']['position'], 'an unknown position falls back to "after"' );
	}
);

sh_test(
	'the X handle is stored without the leading at sign',
	static function () {
		$saved = Settings::sanitize(
			array(
				'_section'   => 'preview',
				'open_graph' => array(
					'enabled'      => '1',
					'twitter_site' => '@my site!',
					'fb_app_id'    => 'abc123456',
				),
			)
		);

		sh_assert_same( 'mysite', $saved['open_graph']['twitter_site'], 'the handle is normalised' );
		sh_assert_same( '123456', $saved['open_graph']['fb_app_id'], 'the app id keeps digits only' );
	}
);
