<?php
/**
 * Renders the share buttons into a standalone HTML file for a quick visual check.
 *
 * Usage: php tests/preview.php > /tmp/social-hub-preview.html
 *
 * @package SocialHub
 */

require_once __DIR__ . '/bootstrap.php';

sh_add_post(
	array(
		'ID'         => 1,
		'post_title' => 'Ten things I learned about coffee',
	)
);

$buttons = new SocialHub\Share_Buttons();

$variations = array(
	'Filled, with labels'    => array( 'style' => 'filled' ),
	'Outline, with labels'   => array( 'style' => 'outline' ),
	'Filled, icons only'     => array(
		'style'       => 'filled',
		'show_labels' => false,
	),
	'Hebrew heading, RTL'    => array(
		'heading' => 'שתפו את הפוסט',
		'dir'     => 'rtl',
	),
);

echo "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">\n";
echo '<link rel="stylesheet" href="' . dirname( __DIR__ ) . "/social-hub/assets/css/share.css\">\n";
echo "<style>body{font-family:system-ui,sans-serif;margin:32px;max-width:760px}h2{font-size:15px;color:#555;margin-top:32px;border-top:1px solid #eee;padding-top:16px}</style>\n";
echo "</head><body>\n<h1>Social Hub share buttons</h1>\n";

foreach ( $variations as $title => $args ) {
	$dir = isset( $args['dir'] ) ? $args['dir'] : 'ltr';
	unset( $args['dir'] );

	$args['post_id'] = 1;

	echo '<h2>' . esc_html( $title ) . "</h2>\n";
	echo '<div dir="' . esc_attr( $dir ) . '">' . $buttons->render( $args ) . "</div>\n";
}

echo "</body></html>\n";
