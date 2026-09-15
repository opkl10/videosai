<?php
/**
 * Registry of social network providers.
 *
 * @package SocialHub
 */

namespace SocialHub;

use SocialHub\Providers\Facebook;
use SocialHub\Providers\Provider;
use SocialHub\Providers\Telegram;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and caches the provider instances.
 */
class Providers {

	/**
	 * Cached providers, keyed by id.
	 *
	 * @var Provider[]|null
	 */
	private static $providers = null;

	/**
	 * Returns every registered provider.
	 *
	 * @return Provider[]
	 */
	public static function all() {
		if ( null === self::$providers ) {
			$providers = array();

			foreach ( array( new Facebook(), new Telegram() ) as $provider ) {
				$providers[ $provider->id() ] = $provider;
			}

			/**
			 * Filters the available providers.
			 *
			 * @param Provider[] $providers Providers keyed by id.
			 */
			$providers = apply_filters( 'social_hub_providers', $providers );

			self::$providers = array_filter(
				$providers,
				static function ( $provider ) {
					return $provider instanceof Provider;
				}
			);
		}

		return self::$providers;
	}

	/**
	 * Returns a single provider.
	 *
	 * @param string $id Provider id.
	 * @return Provider|null
	 */
	public static function get( $id ) {
		$providers = self::all();

		return isset( $providers[ $id ] ) ? $providers[ $id ] : null;
	}

	/**
	 * Returns the providers that are both enabled and fully configured.
	 *
	 * @return Provider[]
	 */
	public static function active() {
		return array_filter(
			self::all(),
			static function ( Provider $provider ) {
				return $provider->is_active();
			}
		);
	}

	/**
	 * Drops the cached instances.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$providers = null;
	}
}
