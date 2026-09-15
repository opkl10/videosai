<?php
/**
 * Removes the plugin data when the user asks for it.
 *
 * @package SocialHub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$social_hub_settings = get_option( 'social_hub_settings', array() );

if ( empty( $social_hub_settings['delete_data_on_uninstall'] ) ) {
	return;
}

delete_option( 'social_hub_settings' );
delete_option( 'social_hub_log' );

delete_post_meta_by_key( '_social_hub_shares' );
delete_post_meta_by_key( '_social_hub_skip' );
delete_post_meta_by_key( '_social_hub_message' );

wp_clear_scheduled_hook( 'social_hub_share_post' );
