<?php
/**
 * Works out when a share should actually go out.
 *
 * @package SocialHub
 */

namespace SocialHub;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Holds shares back until the hours when the audience is around.
 */
class Timing {

	/**
	 * Moves a timestamp into the configured sharing window.
	 *
	 * A post published at 03:00 is worth more at 09:00, when people are awake
	 * and the first hour of engagement decides how far the post travels.
	 *
	 * @param int $timestamp Desired share time, in UTC seconds.
	 * @return int Timestamp inside the window, in UTC seconds.
	 */
	public static function next_slot( $timestamp ) {
		$timestamp = (int) $timestamp;

		if ( ! Settings::get( 'timing.enabled' ) ) {
			return $timestamp;
		}

		$start = self::parts( (string) Settings::get( 'timing.start', '09:00' ), array( 9, 0 ) );
		$end   = self::parts( (string) Settings::get( 'timing.end', '21:00' ), array( 21, 0 ) );
		$skip  = array_map( 'intval', (array) Settings::get( 'timing.skip_days', array() ) );

		if ( count( $skip ) >= 7 ) {
			return $timestamp;
		}

		$moment = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( wp_timezone() );

		for ( $day = 0; $day < 8; $day++ ) {
			$opens  = $moment->setTime( $start[0], $start[1] );
			$closes = $moment->setTime( $end[0], $end[1] );

			if ( ! in_array( (int) $moment->format( 'w' ), $skip, true ) ) {
				if ( $moment < $opens ) {
					return $opens->getTimestamp();
				}

				if ( $moment <= $closes ) {
					return $moment->getTimestamp();
				}
			}

			$moment = $moment->modify( '+1 day' )->setTime( $start[0], $start[1] );
		}

		return $timestamp;
	}

	/**
	 * Whether a timestamp already falls inside the window.
	 *
	 * @param int $timestamp Timestamp in UTC seconds.
	 * @return bool
	 */
	public static function in_window( $timestamp ) {
		return ! Settings::get( 'timing.enabled' ) || self::next_slot( $timestamp ) === (int) $timestamp;
	}

	/**
	 * Weekday names indexed the way PHP's "w" format counts them.
	 *
	 * @return array<int, string>
	 */
	public static function weekdays() {
		global $wp_locale;

		$days = array();

		for ( $day = 0; $day < 7; $day++ ) {
			$days[ $day ] = isset( $wp_locale ) ? $wp_locale->get_weekday( $day ) : (string) $day;
		}

		return $days;
	}

	/**
	 * Splits an HH:MM string into hour and minute.
	 *
	 * @param string $time     Time string.
	 * @param int[]  $fallback Hour and minute used when the string is unusable.
	 * @return int[]
	 */
	private static function parts( $time, array $fallback ) {
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches ) ) {
			return $fallback;
		}

		return array( (int) $matches[1], (int) $matches[2] );
	}
}
