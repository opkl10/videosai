<?php
/**
 * UTM tracking tests.
 *
 * @package SocialHub
 */

use SocialHub\Publisher;
use SocialHub\Settings;
use SocialHub\Share_Buttons;
use SocialHub\Tracking;

/**
 * Stores tracking settings.
 *
 * @param array $tracking Tracking settings.
 * @return void
 */
function sh_set_tracking( array $tracking ) {
	$settings             = Settings::defaults();
	$settings['tracking'] = array_merge( $settings['tracking'], $tracking );
	Settings::update( $settings );
}

sh_test(
	'links are left alone while tracking is off',
	static function () {
		$url = 'https://example.com/post';

		sh_assert_same( $url, Tracking::for_share( $url, 'facebook' ), 'automatic shares are untagged' );
		sh_assert_same( $url, Tracking::for_button( $url, 'facebook' ), 'reader shares are untagged' );
	}
);

sh_test(
	'an automatic share carries the network as the source',
	static function () {
		sh_set_tracking( array( 'enabled' => true ) );

		$tagged = Tracking::for_share( 'https://example.com/post', 'telegram' );

		sh_assert_contains( 'utm_source=telegram', $tagged, 'the network is the source' );
		sh_assert_contains( 'utm_medium=social', $tagged, 'the medium is set' );
		sh_assert_contains( 'utm_campaign=social_hub', $tagged, 'the campaign is set' );
	}
);

sh_test(
	'the campaign name understands placeholders',
	static function () {
		sh_set_tracking(
			array(
				'enabled'  => true,
				'campaign' => '{network}-{slug}',
			)
		);

		$post = sh_add_post(
			array(
				'ID'        => 30,
				'post_name' => 'coffee-guide',
			)
		);

		sh_assert_contains(
			'utm_campaign=facebook-coffee-guide',
			Tracking::for_share( 'https://example.com/post', 'facebook', $post ),
			'the placeholders are replaced'
		);
	}
);

sh_test(
	'a Hebrew slug falls back to something a report can show',
	static function () {
		sh_set_tracking(
			array(
				'enabled'  => true,
				'campaign' => '{network}-{slug}',
			)
		);

		$post = sh_add_post(
			array(
				'ID'        => 33,
				// WordPress stores non-latin slugs percent-encoded.
				'post_name' => '%d7%a7%d7%a4%d7%94',
			)
		);

		sh_assert_contains(
			'utm_campaign=facebook-post-33',
			Tracking::for_share( 'https://example.com/post', 'facebook', $post ),
			'the post id is used instead of percent escapes'
		);
	}
);

sh_test(
	'reader shares are tagged separately from automatic ones',
	static function () {
		sh_set_tracking(
			array(
				'enabled' => true,
				'buttons' => true,
			)
		);

		sh_assert_contains(
			'utm_medium=share_button',
			Tracking::for_button( 'https://example.com/post', 'whatsapp' ),
			'reader shares use their own medium'
		);
	}
);

sh_test(
	'the published message and the link attachment carry the same tagged URL',
	static function () {
		$settings                             = Settings::defaults();
		$settings['tracking']['enabled']      = true;
		$settings['facebook']['enabled']      = true;
		$settings['facebook']['page_id']      = '555';
		$settings['facebook']['access_token'] = 'token';
		$settings['facebook']['post_format']  = 'photo';
		Settings::update( $settings );

		$post = sh_add_post(
			array(
				'ID'           => 31,
				'post_title'   => 'Coffee',
				'thumbnail_id' => 9,
			)
		);
		sh_queue_http( array( 'post_id' => '555_1' ) );

		( new Publisher() )->share( 31 );

		$caption = $GLOBALS['sh_http_log'][0]['args']['body']['caption'];

		sh_assert_contains( 'utm_source=facebook', $caption, 'the link inside the message is tagged' );
		sh_assert_same( 1, substr_count( $caption, 'utm_source' ), 'the URL is not duplicated' );
		sh_assert_same( 'https://example.com/image-9.jpg', $GLOBALS['sh_http_log'][0]['args']['body']['url'], 'the featured image is sent' );
	}
);

sh_test(
	'share buttons tag their links but leave the copied URL clean',
	static function () {
		sh_set_tracking(
			array(
				'enabled' => true,
				'buttons' => true,
			)
		);
		sh_add_post( array( 'ID' => 32 ) );

		$html = ( new Share_Buttons() )->render( array( 'post_id' => 32 ) );

		sh_assert_contains( 'utm_source%3Dfacebook', $html, 'the Facebook link is tagged' );
		sh_assert_contains( 'data-social-hub-copy="https://example.com/?p=32"', $html, 'the copied link stays clean' );
	}
);
