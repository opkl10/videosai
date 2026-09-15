<?php
/**
 * PSR-4 style autoloader mapping the SocialHub namespace to includes/.
 *
 * SocialHub\Providers\Facebook  =>  includes/providers/class-facebook.php
 * SocialHub\Admin\Meta_Box      =>  includes/admin/class-meta-box.php
 *
 * @package SocialHub
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'SocialHub\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$leaf  = array_pop( $parts );
		$path  = SOCIAL_HUB_DIR . 'includes/';

		foreach ( $parts as $part ) {
			$path .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}

		$path .= 'class-' . strtolower( str_replace( '_', '-', $leaf ) ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);
