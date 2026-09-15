<?php
/**
 * Share button rendering tests.
 *
 * @package SocialHub
 */

use SocialHub\Settings;
use SocialHub\Share_Buttons;

sh_test(
	'the buttons link to the right share endpoints',
	static function () {
		sh_add_post(
			array(
				'ID'         => 20,
				'post_title' => 'Tips & tricks',
			)
		);

		$html = ( new Share_Buttons() )->render( array( 'post_id' => 20 ) );

		sh_assert_contains( 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fexample.com%2F%3Fp%3D20', $html, 'the Facebook sharer gets the encoded permalink' );
		sh_assert_contains( 'https://twitter.com/intent/tweet?url=', $html, 'X is linked' );
		sh_assert_contains( 'Tips%20%26%20tricks', $html, 'the title is URL encoded' );
		sh_assert_contains( 'data-social-hub-copy=', $html, 'the copy button carries the URL' );
		sh_assert_contains( 'rel="noopener nofollow"', $html, 'outgoing links are marked' );
		sh_assert_contains( 'social-hub-share', implode( ',', $GLOBALS['sh_enqueued'] ), 'the assets are enqueued' );
	}
);

sh_test(
	'only the selected networks are rendered',
	static function () {
		sh_add_post( array( 'ID' => 21 ) );

		$settings                        = Settings::defaults();
		$settings['buttons']['networks'] = array( 'whatsapp' );
		Settings::update( $settings );

		$html = ( new Share_Buttons() )->render( array( 'post_id' => 21 ) );

		sh_assert_contains( 'api.whatsapp.com', $html, 'WhatsApp is rendered' );
		sh_assert_not_contains( 'linkedin.com', $html, 'LinkedIn is not rendered' );
	}
);

sh_test(
	'nothing is rendered when the buttons are switched off',
	static function () {
		sh_add_post( array( 'ID' => 22 ) );

		Settings::update(
			array_replace_recursive(
				Settings::defaults(),
				array( 'buttons' => array( 'enabled' => false ) )
			)
		);

		sh_assert_same( '', ( new Share_Buttons() )->render( array( 'post_id' => 22 ) ), 'the markup is empty' );
	}
);

sh_test(
	'the heading and icon-only mode can be overridden per call',
	static function () {
		sh_add_post( array( 'ID' => 23 ) );

		$html = ( new Share_Buttons() )->render(
			array(
				'post_id'     => 23,
				'heading'     => 'שתפו',
				'show_labels' => false,
				'style'       => 'outline',
			)
		);

		sh_assert_contains( 'שתפו', $html, 'the custom heading is used' );
		sh_assert_contains( 'social-hub-share--icons-only', $html, 'labels can be hidden' );
		sh_assert_contains( 'social-hub-share--outline', $html, 'the outline style is applied' );
		sh_assert_not_contains( 'social-hub-share__label', $html, 'no label markup is printed' );
	}
);

sh_test(
	'every network definition is complete',
	static function () {
		foreach ( Share_Buttons::networks() as $id => $network ) {
			sh_assert_true( ! empty( $network['label'] ), $id . ' has a label' );
			sh_assert_true( ! empty( $network['icon'] ), $id . ' has an icon' );
			sh_assert_true( 1 === preg_match( '/^#[0-9a-f]{6}$/', $network['color'] ), $id . ' has a hex colour' );

			if ( 'copy' !== $id ) {
				sh_assert_contains( '{url}', $network['template'], $id . ' shares the URL' );
			}
		}
	}
);
