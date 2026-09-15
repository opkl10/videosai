<?php
/**
 * Site readiness checklist tests.
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
	'a fresh install is told what is missing',
	static function () {
		$checks = Advisor::site_checks();

		sh_assert_same( 'warn', sh_check( $checks, 'networks' )['status'], 'no network is connected yet' );
		sh_assert_same( 'pass', sh_check( $checks, 'previews' )['status'], 'Open Graph is on by default' );
		sh_assert_same( 'info', sh_check( $checks, 'fallback-image' )['status'], 'no fallback image yet' );
		sh_assert_same( 'info', sh_check( $checks, 'tracking' )['status'], 'tracking is off by default' );
		sh_assert_same( 'info', sh_check( $checks, 'timing' )['status'], 'the sharing window is off by default' );
		sh_assert_same( 7, count( $checks ), 'every check reports something' );
	}
);

sh_test(
	'a configured install passes its checks',
	static function () {
		$settings                                = Settings::defaults();
		$settings['facebook']['enabled']         = true;
		$settings['facebook']['page_id']         = '555';
		$settings['facebook']['access_token']    = 'token';
		$settings['open_graph']['default_image'] = 4;
		$settings['tracking']['enabled']         = true;
		$settings['timing']['enabled']           = true;
		Settings::update( $settings );
		SocialHub\Providers::reset();
		update_option( 'permalink_structure', '/%postname%/' );

		$checks = Advisor::site_checks();

		sh_assert_same( 'pass', sh_check( $checks, 'networks' )['status'], 'the connected network is counted' );
		sh_assert_contains( '1', sh_check( $checks, 'networks' )['label'], 'the count is shown' );
		sh_assert_same( 'pass', sh_check( $checks, 'fallback-image' )['status'], 'the fallback image is found' );
		sh_assert_same( 'pass', sh_check( $checks, 'tracking' )['status'], 'tracking is on' );
		sh_assert_same( 'pass', sh_check( $checks, 'timing' )['status'], 'the window is on' );
		sh_assert_same( 'pass', sh_check( $checks, 'permalinks' )['status'], 'pretty permalinks are detected' );
		sh_assert_same( 7, Advisor::passed( $checks ), 'nothing is left to fix' );
	}
);
