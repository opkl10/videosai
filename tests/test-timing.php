<?php
/**
 * Sharing window tests.
 *
 * @package SocialHub
 */

use SocialHub\Settings;
use SocialHub\Timing;

/**
 * Stores a sharing window.
 *
 * @param array $timing Timing settings.
 * @return void
 */
function sh_set_window( array $timing ) {
	$settings           = Settings::defaults();
	$settings['timing'] = array_merge( $settings['timing'], $timing );
	Settings::update( $settings );
}

/**
 * Builds a timestamp from a local date and time in the site time zone.
 *
 * @param string $local Local date and time, e.g. "2026-09-15 03:00".
 * @return int
 */
function sh_local( $local ) {
	return ( new DateTimeImmutable( $local, new DateTimeZone( $GLOBALS['sh_timezone'] ) ) )->getTimestamp();
}

/**
 * Formats a timestamp back into local time.
 *
 * @param int $timestamp Timestamp.
 * @return string
 */
function sh_local_string( $timestamp ) {
	return ( new DateTimeImmutable( '@' . $timestamp ) )
		->setTimezone( new DateTimeZone( $GLOBALS['sh_timezone'] ) )
		->format( 'Y-m-d H:i' );
}

sh_test(
	'the window does nothing while it is switched off',
	static function () {
		$at = sh_local( '2026-09-15 03:00' );

		sh_assert_same( $at, Timing::next_slot( $at ), 'the timestamp is untouched' );
	}
);

sh_test(
	'a night time share waits for the morning',
	static function () {
		sh_set_window( array( 'enabled' => true ) );

		sh_assert_same(
			'2026-09-15 09:00',
			sh_local_string( Timing::next_slot( sh_local( '2026-09-15 03:00' ) ) ),
			'03:00 moves to the start of the window'
		);
	}
);

sh_test(
	'a share inside the window goes out as planned',
	static function () {
		sh_set_window( array( 'enabled' => true ) );
		$at = sh_local( '2026-09-15 14:30' );

		sh_assert_same( $at, Timing::next_slot( $at ), 'the timestamp is untouched' );
		sh_assert_true( Timing::in_window( $at ), 'the moment counts as inside the window' );
	}
);

sh_test(
	'a late evening share waits for the next morning',
	static function () {
		sh_set_window( array( 'enabled' => true ) );

		sh_assert_same(
			'2026-09-16 09:00',
			sh_local_string( Timing::next_slot( sh_local( '2026-09-15 23:40' ) ) ),
			'after the window it moves to the next day'
		);
	}
);

sh_test(
	'skipped days are jumped over',
	static function () {
		// 2026-09-18 is a Friday, 2026-09-19 a Saturday.
		sh_set_window(
			array(
				'enabled'   => true,
				'skip_days' => array( 5, 6 ),
			)
		);

		sh_assert_same(
			'2026-09-20 09:00',
			sh_local_string( Timing::next_slot( sh_local( '2026-09-18 10:00' ) ) ),
			'Friday and Saturday are skipped'
		);
	}
);

sh_test(
	'a custom window is respected',
	static function () {
		sh_set_window(
			array(
				'enabled' => true,
				'start'   => '19:00',
				'end'     => '22:00',
			)
		);

		sh_assert_same(
			'2026-09-15 19:00',
			sh_local_string( Timing::next_slot( sh_local( '2026-09-15 08:00' ) ) ),
			'the morning waits for the evening window'
		);
	}
);

sh_test(
	'an upside down window is rejected when it is saved',
	static function () {
		$saved = Settings::sanitize(
			array(
				'_section' => 'promote',
				'timing'   => array(
					'enabled' => '1',
					'start'   => '22:00',
					'end'     => '06:00',
				),
			)
		);

		sh_assert_same( '09:00', $saved['timing']['start'], 'the start falls back' );
		sh_assert_same( '21:00', $saved['timing']['end'], 'the end falls back' );
		sh_assert_same( 1, count( $GLOBALS['sh_settings_errors'] ), 'the admin is told why' );
	}
);

sh_test(
	'skipping every day disables the window instead of waiting forever',
	static function () {
		sh_set_window(
			array(
				'enabled'   => true,
				'skip_days' => array( 0, 1, 2, 3, 4, 5, 6 ),
			)
		);

		$at = sh_local( '2026-09-15 03:00' );

		sh_assert_same( $at, Timing::next_slot( $at ), 'the share is not postponed into eternity' );
	}
);

sh_test(
	'publishing at night queues the share for the morning',
	static function () {
		$settings                         = Settings::defaults();
		$settings['timing']['enabled']    = true;
		$settings['facebook']['enabled']  = true;
		$settings['facebook']['page_id']  = '555';
		$settings['facebook']['access_token'] = 'token';
		Settings::update( $settings );

		$post = sh_add_post( array( 'ID' => 40 ) );

		( new SocialHub\Publisher() )->maybe_schedule( 'publish', 'draft', $post );

		sh_assert_same( 1, count( $GLOBALS['sh_scheduled'] ), 'one event was scheduled' );
		sh_assert_true(
			SocialHub\Timing::in_window( $GLOBALS['sh_scheduled'][0]['timestamp'] ),
			'the event lands inside the window'
		);
		sh_assert_same( array( 40 ), $GLOBALS['sh_scheduled'][0]['args'], 'the post id is passed to the event' );
	}
);
