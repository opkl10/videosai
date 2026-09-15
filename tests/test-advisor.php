<?php
/**
 * Promotion advisor tests.
 *
 * @package SocialHub
 */

use SocialHub\Advisor;
use SocialHub\Settings;

/**
 * Returns a single check by id.
 *
 * @param array[] $checks Checks.
 * @param string  $id     Check id.
 * @return array
 */
function sh_check( array $checks, $id ) {
	foreach ( $checks as $check ) {
		if ( $id === $check['id'] ) {
			return $check;
		}
	}

	return array(
		'id'     => $id,
		'status' => 'missing',
		'label'  => '',
		'hint'   => '',
	);
}

sh_test(
	'a bare post collects the advice it needs',
	static function () {
		$post   = sh_add_post(
			array(
				'ID'           => 50,
				'post_title'   => 'Short title',
				'post_content' => 'A body without any question in it.',
			)
		);
		$checks = Advisor::post_checks( $post );

		sh_assert_same( 'warn', sh_check( $checks, 'image' )['status'], 'a missing image is a warning' );
		sh_assert_same( 'pass', sh_check( $checks, 'title' )['status'], 'a short title passes' );
		sh_assert_same( 'info', sh_check( $checks, 'excerpt' )['status'], 'a missing excerpt is noted' );
		sh_assert_same( 'info', sh_check( $checks, 'hashtags' )['status'], 'missing tags are noted' );
		sh_assert_same( 'info', sh_check( $checks, 'engagement' )['status'], 'a message without a question is noted' );
		sh_assert_same( 6, count( $checks ), 'every check reports something' );
	}
);

sh_test(
	'a well prepared post passes everything',
	static function () {
		$post = sh_add_post(
			array(
				'ID'           => 51,
				'post_title'   => 'How to brew better coffee',
				'post_excerpt' => 'Three changes that fix most home espresso. Which one do you already do?',
				'thumbnail_id' => 7,
			)
		);

		$GLOBALS['sh_terms'][51] = array( 'post_tag' => array( 'Coffee', 'Espresso' ) );

		$checks = Advisor::post_checks( $post );

		sh_assert_same( 6, Advisor::passed( $checks ), 'all six checks pass' );
	}
);

sh_test(
	'a small featured image is flagged',
	static function () {
		$GLOBALS['sh_image_sizes'][8] = array( 600, 400 );

		$post   = sh_add_post(
			array(
				'ID'           => 52,
				'post_title'   => 'Post with a small image',
				'thumbnail_id' => 8,
			)
		);
		$checks = Advisor::post_checks( $post );

		sh_assert_same( 'info', sh_check( $checks, 'image' )['status'], 'an undersized image is noted' );
		sh_assert_contains( '600', sh_check( $checks, 'image' )['label'], 'the real size is shown' );
	}
);

sh_test(
	'a long headline is flagged',
	static function () {
		$post = sh_add_post(
			array(
				'ID'         => 53,
				'post_title' => str_repeat( 'a very long headline ', 6 ),
			)
		);

		sh_assert_same( 'info', sh_check( Advisor::post_checks( $post ), 'title' )['status'], 'the title is too long' );
	}
);

sh_test(
	'the fallback image softens the missing image warning',
	static function () {
		$settings                              = Settings::defaults();
		$settings['open_graph']['default_image'] = 4;
		Settings::update( $settings );

		$post = sh_add_post( array( 'ID' => 54 ) );

		sh_assert_same( 'info', sh_check( Advisor::post_checks( $post ), 'image' )['status'], 'the fallback downgrades the warning' );
	}
);

sh_test(
	'the site checklist reflects the configuration',
	static function () {
		$checks = Advisor::site_checks();

		sh_assert_same( 'warn', sh_check( $checks, 'networks' )['status'], 'no network is connected yet' );
		sh_assert_same( 'pass', sh_check( $checks, 'previews' )['status'], 'Open Graph is on by default' );
		sh_assert_same( 'info', sh_check( $checks, 'tracking' )['status'], 'tracking is off by default' );
		sh_assert_same( 'info', sh_check( $checks, 'timing' )['status'], 'the sharing window is off by default' );

		$settings                             = Settings::defaults();
		$settings['facebook']['enabled']      = true;
		$settings['facebook']['page_id']      = '555';
		$settings['facebook']['access_token'] = 'token';
		$settings['tracking']['enabled']      = true;
		$settings['timing']['enabled']        = true;
		Settings::update( $settings );
		SocialHub\Providers::reset();
		update_option( 'permalink_structure', '/%postname%/' );

		$checks = Advisor::site_checks();

		sh_assert_same( 'pass', sh_check( $checks, 'networks' )['status'], 'the connected network is counted' );
		sh_assert_same( 'pass', sh_check( $checks, 'tracking' )['status'], 'tracking is on' );
		sh_assert_same( 'pass', sh_check( $checks, 'timing' )['status'], 'the window is on' );
		sh_assert_same( 'pass', sh_check( $checks, 'permalinks' )['status'], 'pretty permalinks are detected' );
	}
);
