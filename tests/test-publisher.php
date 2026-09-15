<?php
/**
 * Publisher tests.
 *
 * @package SocialHub
 */

use SocialHub\Providers;
use SocialHub\Publisher;
use SocialHub\Settings;

/**
 * Stores a working Facebook configuration.
 *
 * @param array $overrides Extra settings.
 * @return void
 */
function sh_configure_facebook( array $overrides = array() ) {
	Settings::update(
		array_replace_recursive(
			Settings::defaults(),
			array(
				'facebook' => array(
					'enabled'      => true,
					'page_id'      => '555',
					'access_token' => 'page-token',
				),
			),
			$overrides
		)
	);
}

sh_test(
	'the message template replaces every placeholder',
	static function () {
		$post = sh_add_post(
			array(
				'ID'           => 10,
				'post_title'   => 'Hello world',
				'post_content' => 'Some [shortcode] body text that is long enough.',
			)
		);

		$GLOBALS['sh_terms'][10] = array( 'post_tag' => array( 'Web Design', 'קידום אתרים' ) );

		$publisher = new Publisher();
		$message   = $publisher->render_template( "{title}\n{excerpt}\n{tags}\n{site}\n{author}\n{url}", $post );

		sh_assert_contains( 'Hello world', $message, 'the title is inserted' );
		sh_assert_contains( 'Some body text', $message, 'shortcodes are stripped from the excerpt' );
		sh_assert_contains( '#Web_Design', $message, 'spaces in tags become underscores' );
		sh_assert_contains( '#קידום_אתרים', $message, 'non-latin tags are kept' );
		sh_assert_contains( 'Test Site', $message, 'the site name is inserted' );
		sh_assert_contains( 'https://example.com/?p=10', $message, 'the permalink is inserted' );
	}
);

sh_test(
	'a per-post message overrides the template',
	static function () {
		$post = sh_add_post(
			array(
				'ID'         => 11,
				'post_title' => 'Ignored title',
			)
		);

		update_post_meta( 11, Publisher::META_MESSAGE, 'Custom text about {title}' );

		sh_assert_same(
			'Custom text about Ignored title',
			( new Publisher() )->message( $post ),
			'the custom message is rendered with placeholders'
		);
	}
);

sh_test(
	'publishing sends the post to Facebook and records the result',
	static function () {
		sh_configure_facebook();
		sh_add_post(
			array(
				'ID'         => 12,
				'post_title' => 'A new post',
			)
		);
		sh_queue_http( array( 'id' => '555_777' ) );

		$results = ( new Publisher() )->share( 12 );

		sh_assert_same( 'success', $results['facebook']['status'], 'the share succeeds' );
		sh_assert_same( 'https://www.facebook.com/555/posts/777', $results['facebook']['url'], 'the post URL is built from the Graph ID' );

		$request = $GLOBALS['sh_http_log'][0];

		sh_assert_contains( 'https://graph.facebook.com/v26.0/555/feed', $request['url'], 'the feed endpoint is versioned' );
		sh_assert_same( 'https://example.com/?p=12', $request['args']['body']['link'], 'the permalink is sent as a link attachment' );
		sh_assert_not_contains( 'https://example.com/?p=12', $request['args']['body']['message'], 'the URL is not repeated in the message' );

		$shares = ( new Publisher() )->shares( 12 );

		sh_assert_same( 'success', $shares['facebook']['status'], 'the result is stored on the post' );
	}
);

sh_test(
	'a post is not shared twice unless it is forced',
	static function () {
		sh_configure_facebook();
		sh_add_post( array( 'ID' => 13 ) );
		sh_queue_http( array( 'id' => '555_1' ) );

		$publisher = new Publisher();
		$publisher->share( 13 );

		sh_assert_same( array(), $publisher->share( 13 ), 'the second automatic share is skipped' );

		sh_queue_http( array( 'id' => '555_2' ) );
		$forced = $publisher->share( 13, array( 'facebook' ), true );

		sh_assert_same( 'success', $forced['facebook']['status'], 'forcing shares again' );
		sh_assert_same( 2, count( $GLOBALS['sh_http_log'] ), 'exactly two requests were made' );
	}
);

sh_test(
	'posts marked as skipped are left alone',
	static function () {
		sh_configure_facebook();
		sh_add_post( array( 'ID' => 14 ) );
		update_post_meta( 14, Publisher::META_SKIP, 1 );

		sh_assert_same( array(), ( new Publisher() )->share( 14 ), 'nothing is shared' );
		sh_assert_same( 0, count( $GLOBALS['sh_http_log'] ), 'no request is made' );
	}
);

sh_test(
	'drafts are never shared',
	static function () {
		sh_configure_facebook();
		sh_add_post(
			array(
				'ID'          => 15,
				'post_status' => 'draft',
			)
		);

		sh_assert_same( array(), ( new Publisher() )->share( 15 ), 'a draft is ignored' );
	}
);

sh_test(
	'an API failure is reported and can be retried',
	static function () {
		sh_configure_facebook();
		sh_add_post( array( 'ID' => 16 ) );
		sh_queue_http(
			array(
				'error' => array(
					'message' => 'Invalid OAuth access token.',
					'type'    => 'OAuthException',
				),
			),
			401
		);

		$publisher = new Publisher();
		$results   = $publisher->share( 16 );

		sh_assert_same( 'error', $results['facebook']['status'], 'the failure is reported' );
		sh_assert_same( 'Invalid OAuth access token.', $results['facebook']['message'], 'the Graph error message is kept' );

		$log = SocialHub\Log::all();

		sh_assert_same( 'error', $log[0]['status'], 'the failure is logged' );

		sh_queue_http( array( 'id' => '555_9' ) );
		$retry = $publisher->share( 16 );

		sh_assert_same( 'success', $retry['facebook']['status'], 'a failed share is retried automatically' );
	}
);

sh_test(
	'inactive networks receive nothing',
	static function () {
		sh_configure_facebook( array( 'facebook' => array( 'enabled' => false ) ) );
		sh_add_post( array( 'ID' => 17 ) );

		sh_assert_same( array(), Providers::active(), 'a disabled network is not active' );
		sh_assert_same( array(), ( new Publisher() )->share( 17 ), 'nothing is shared' );
	}
);
