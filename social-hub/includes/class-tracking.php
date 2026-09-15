<?php
/**
 * UTM parameters on shared links.
 *
 * @package SocialHub
 */

namespace SocialHub;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Tags outgoing links so analytics can tell the networks apart.
 */
class Tracking {

	/**
	 * Adds UTM parameters to a link.
	 *
	 * @param string       $url     Link to tag.
	 * @param string       $network Network id, used as utm_source.
	 * @param WP_Post|null $post    Post being shared, used by campaign placeholders.
	 * @param string       $medium  Overrides the configured utm_medium.
	 * @return string
	 */
	public static function decorate( $url, $network, $post = null, $medium = '' ) {
		$url = (string) $url;

		if ( '' === $url || '' === (string) $network ) {
			return $url;
		}

		$campaign = self::campaign( $post, $network );
		$medium   = '' !== $medium ? $medium : (string) Settings::get( 'tracking.medium', 'social' );

		$parameters = array(
			'utm_source'   => sanitize_key( $network ),
			'utm_medium'   => $medium,
			'utm_campaign' => $campaign,
		);

		/**
		 * Filters the UTM parameters added to a shared link.
		 *
		 * @param array        $parameters Query parameters.
		 * @param string       $network    Network id.
		 * @param WP_Post|null $post       Post being shared.
		 */
		$parameters = (array) apply_filters( 'social_hub_utm_parameters', $parameters, $network, $post );

		return add_query_arg( array_filter( $parameters ), $url );
	}

	/**
	 * Adds UTM parameters to an automatic share, when tracking is on.
	 *
	 * @param string       $url     Link to tag.
	 * @param string       $network Network id.
	 * @param WP_Post|null $post    Post being shared.
	 * @return string
	 */
	public static function for_share( $url, $network, $post = null ) {
		if ( ! Settings::get( 'tracking.enabled' ) ) {
			return $url;
		}

		return self::decorate( $url, $network, $post );
	}

	/**
	 * Adds UTM parameters to a reader share button, when tracking is on.
	 *
	 * @param string       $url     Link to tag.
	 * @param string       $network Network id.
	 * @param WP_Post|null $post    Post being shared.
	 * @return string
	 */
	public static function for_button( $url, $network, $post = null ) {
		if ( ! Settings::get( 'tracking.buttons' ) ) {
			return $url;
		}

		return self::decorate( $url, $network, $post, 'share_button' );
	}

	/**
	 * Renders the campaign name.
	 *
	 * @param WP_Post|null $post    Post being shared.
	 * @param string       $network Network id.
	 * @return string
	 */
	private static function campaign( $post, $network ) {
		$template = (string) Settings::get( 'tracking.campaign', 'social_hub' );

		$replacements = array(
			'{network}' => sanitize_key( $network ),
			'{slug}'    => $post instanceof WP_Post ? $post->post_name : '',
			'{year}'    => $post instanceof WP_Post ? get_post_time( 'Y', false, $post ) : gmdate( 'Y' ),
			'{month}'   => $post instanceof WP_Post ? get_post_time( 'm', false, $post ) : gmdate( 'm' ),
		);

		$campaign = str_replace( array_keys( $replacements ), array_values( $replacements ), $template );

		return trim( (string) preg_replace( '/[^A-Za-z0-9_\-]+/', '_', $campaign ), '_' );
	}
}
