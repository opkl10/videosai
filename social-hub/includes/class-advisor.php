<?php
/**
 * Checks that say whether the site is set up to get anything out of sharing.
 *
 * @package SocialHub
 */

namespace SocialHub;

defined( 'ABSPATH' ) || exit;

/**
 * Looks at the configuration and says what is missing before posts start going out.
 */
class Advisor {

	const PASS = 'pass';
	const WARN = 'warn';
	const INFO = 'info';

	/**
	 * Checks that apply to the whole site.
	 *
	 * @return array[] Each item has id, status, label and hint.
	 */
	public static function site_checks() {
		$checks = array();

		$active   = Providers::active();
		$checks[] = self::result(
			'networks',
			$active ? self::PASS : self::WARN,
			$active
				? sprintf(
					/* translators: %d: number of connected networks. */
					__( 'Connected networks: %d', 'social-hub' ),
					count( $active )
				)
				: __( 'No network is connected', 'social-hub' ),
			__( 'Nothing is published automatically until at least one network is connected and switched on.', 'social-hub' )
		);

		$previews = Settings::get( 'open_graph.enabled' ) || Open_Graph::seo_plugin_active();
		$checks[] = self::result(
			'previews',
			$previews ? self::PASS : self::WARN,
			$previews ? __( 'Link previews are handled', 'social-hub' ) : __( 'Nothing controls your link previews', 'social-hub' ),
			__( 'Without Open Graph tags the networks guess the title and image, and usually guess badly.', 'social-hub' )
		);

		$fallback = (int) Settings::get( 'open_graph.default_image' );
		$checks[] = self::result(
			'fallback-image',
			$fallback ? self::PASS : self::INFO,
			$fallback ? __( 'A fallback preview image is set', 'social-hub' ) : __( 'No fallback preview image', 'social-hub' ),
			__( 'Posts without a featured image fall back to this one instead of sharing a bare link.', 'social-hub' )
		);

		$buttons  = (bool) Settings::get( 'buttons.enabled' );
		$checks[] = self::result(
			'buttons',
			$buttons ? self::PASS : self::INFO,
			$buttons ? __( 'Readers can share your posts', 'social-hub' ) : __( 'Share buttons are switched off', 'social-hub' ),
			__( 'A share from a reader reaches an audience your own page never touches.', 'social-hub' )
		);

		$tracking = (bool) Settings::get( 'tracking.enabled' );
		$checks[] = self::result(
			'tracking',
			$tracking ? self::PASS : self::INFO,
			$tracking ? __( 'Shared links are tagged for analytics', 'social-hub' ) : __( 'Shared links are not tagged', 'social-hub' ),
			__( 'UTM tags are the only way to see which network actually sends you readers, rather than guessing from likes.', 'social-hub' )
		);

		$pretty   = '' !== (string) get_option( 'permalink_structure' );
		$checks[] = self::result(
			'permalinks',
			$pretty ? self::PASS : self::INFO,
			$pretty ? __( 'Readable permalinks', 'social-hub' ) : __( 'Permalinks look like ?p=123', 'social-hub' ),
			__( 'A readable URL gets clicked more often, and survives being pasted into a chat.', 'social-hub' )
		);

		$window   = (bool) Settings::get( 'timing.enabled' );
		$checks[] = self::result(
			'timing',
			$window ? self::PASS : self::INFO,
			$window ? __( 'Shares wait for your best hours', 'social-hub' ) : __( 'Shares go out the moment you publish', 'social-hub' ),
			__( 'A post published at 03:00 burns its first hour of reach while everyone sleeps.', 'social-hub' )
		);

		/**
		 * Filters the site wide promotion checks.
		 *
		 * @param array[] $checks Checks.
		 */
		return (array) apply_filters( 'social_hub_site_checks', $checks );
	}

	/**
	 * Counts how many checks passed.
	 *
	 * @param array[] $checks Checks.
	 * @return int Passed checks.
	 */
	public static function passed( array $checks ) {
		return count(
			array_filter(
				$checks,
				static function ( $check ) {
					return self::PASS === $check['status'];
				}
			)
		);
	}

	/**
	 * Builds a check result.
	 *
	 * @param string $id     Check id.
	 * @param string $status pass, warn or info.
	 * @param string $label  Short result.
	 * @param string $hint   Why it matters.
	 * @return array
	 */
	private static function result( $id, $status, $label, $hint ) {
		return array(
			'id'     => $id,
			'status' => $status,
			'label'  => $label,
			'hint'   => $hint,
		);
	}
}
