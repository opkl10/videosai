<?php
/**
 * Rolling activity log.
 *
 * @package SocialHub
 */

namespace SocialHub;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the most recent share attempts so failures are visible in the admin.
 */
class Log {

	const OPTION = 'social_hub_log';
	const LIMIT  = 100;

	/**
	 * Adds an entry to the top of the log.
	 *
	 * @param array $entry {
	 *     Entry data.
	 *
	 *     @type string $network Network id.
	 *     @type int    $post_id Shared post id.
	 *     @type string $status  success|error|skipped.
	 *     @type string $message Human readable result.
	 *     @type string $url     Link to the created social post, when available.
	 * }
	 * @return void
	 */
	public static function add( array $entry ) {
		$entry = wp_parse_args(
			$entry,
			array(
				'time'    => time(),
				'network' => '',
				'post_id' => 0,
				'status'  => 'success',
				'message' => '',
				'url'     => '',
			)
		);

		$entries = self::all();
		array_unshift( $entries, $entry );

		update_option( self::OPTION, array_slice( $entries, 0, self::LIMIT ), false );
	}

	/**
	 * Returns every stored entry, newest first.
	 *
	 * @return array[]
	 */
	public static function all() {
		$entries = get_option( self::OPTION, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Empties the log.
	 *
	 * @return void
	 */
	public static function clear() {
		update_option( self::OPTION, array(), false );
	}
}
