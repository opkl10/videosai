<?php
/**
 * Template tags for theme authors.
 *
 * @package SocialHub
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'social_hub_share_buttons' ) ) {
	/**
	 * Prints the share buttons.
	 *
	 * @param array $args Overrides for the stored settings: post_id, networks, heading, style, show_labels.
	 * @return void
	 */
	function social_hub_share_buttons( array $args = array() ) {
		echo social_hub_get_share_buttons( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped while it is built.
	}
}

if ( ! function_exists( 'social_hub_get_share_buttons' ) ) {
	/**
	 * Returns the share buttons markup.
	 *
	 * @param array $args Overrides for the stored settings.
	 * @return string
	 */
	function social_hub_get_share_buttons( array $args = array() ) {
		return ( new SocialHub\Share_Buttons() )->render( $args );
	}
}
