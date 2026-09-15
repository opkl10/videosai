<?php
/**
 * Minimal WordPress stubs so the plugin logic can be exercised without a WordPress install.
 *
 * Only the functions the plugin actually calls are defined, with just enough
 * behaviour to make the assertions in tests/ meaningful.
 *
 * @package SocialHub
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'SOCIAL_HUB_VERSION', '1.0.0' );
define( 'SOCIAL_HUB_FILE', dirname( __DIR__ ) . '/social-hub/social-hub.php' );
define( 'SOCIAL_HUB_DIR', dirname( __DIR__ ) . '/social-hub/' );
define( 'SOCIAL_HUB_URL', 'https://example.com/wp-content/plugins/social-hub/' );

$GLOBALS['sh_options']    = array();
$GLOBALS['sh_post_meta']  = array();
$GLOBALS['sh_posts']      = array();
$GLOBALS['sh_terms']      = array();
$GLOBALS['sh_http_queue'] = array();
$GLOBALS['sh_http_log']   = array();
$GLOBALS['sh_enqueued']        = array();
$GLOBALS['sh_singular']        = true;
$GLOBALS['sh_timezone']        = 'Asia/Jerusalem';
$GLOBALS['sh_image_sizes']     = array();
$GLOBALS['sh_settings_errors'] = array();

/**
 * Resets every piece of stub state between tests.
 *
 * @return void
 */
function sh_reset_state() {
	$GLOBALS['sh_options']    = array();
	$GLOBALS['sh_post_meta']  = array();
	$GLOBALS['sh_posts']      = array();
	$GLOBALS['sh_terms']      = array();
	$GLOBALS['sh_http_queue'] = array();
	$GLOBALS['sh_http_log']        = array();
	$GLOBALS['sh_enqueued']        = array();
	$GLOBALS['sh_scheduled']       = array();
	$GLOBALS['sh_timezone']        = 'Asia/Jerusalem';
	$GLOBALS['sh_image_sizes']     = array();
	$GLOBALS['sh_settings_errors'] = array();

	SocialHub\Settings::flush();
	SocialHub\Providers::reset();
}

/**
 * Queues the next HTTP response returned by wp_remote_request().
 *
 * @param mixed $body Response body, encoded as JSON when it is an array.
 * @param int   $code HTTP status code.
 * @return void
 */
function sh_queue_http( $body, $code = 200 ) {
	$GLOBALS['sh_http_queue'][] = array(
		'response' => array( 'code' => $code ),
		'body'     => is_string( $body ) ? $body : wp_json_encode( $body ),
	);
}

/**
 * Registers a fake post.
 *
 * @param array $data Post fields.
 * @return WP_Post
 */
function sh_add_post( array $data ) {
	$post                            = new WP_Post( $data );
	$GLOBALS['sh_posts'][ $post->ID ] = $post;

	return $post;
}

// phpcs:disable Squiz.Commenting, Generic.Commenting -- compact stubs.

class WP_Post {
	public $ID = 1;
	public $post_title = '';
	public $post_content = '';
	public $post_excerpt = '';
	public $post_status = 'publish';
	public $post_type = 'post';
	public $post_author = 1;
	public $post_name = '';

	public function __construct( array $data = array() ) {
		foreach ( $data as $key => $value ) {
			$this->$key = $value;
		}
	}
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code = '', $message = '', $data = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
	return $text;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function esc_attr__( $text, $domain = null ) {
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_textarea( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url, $protocols = null ) {
	return str_replace( array( '"', "'", '<', '>' ), '', (string) $url );
}

function esc_url_raw( $url ) {
	return esc_url( $url );
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	return true;
}

function do_action( $hook, ...$args ) {
	return null;
}

function apply_filters( $hook, $value, ...$args ) {
	return $value;
}

function add_shortcode( $tag, $callback ) {
	return true;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['sh_options'] ) ? $GLOBALS['sh_options'][ $name ] : $default;
}

function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['sh_options'][ $name ] = $value;

	return true;
}

function add_option( $name, $value ) {
	return update_option( $name, $value );
}

function delete_option( $name ) {
	unset( $GLOBALS['sh_options'][ $name ] );

	return true;
}

function get_post_meta( $post_id, $key, $single = false ) {
	$value = isset( $GLOBALS['sh_post_meta'][ $post_id ][ $key ] ) ? $GLOBALS['sh_post_meta'][ $post_id ][ $key ] : '';

	return $single ? $value : array( $value );
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['sh_post_meta'][ $post_id ][ $key ] = $value;

	return true;
}

function delete_post_meta( $post_id, $key ) {
	unset( $GLOBALS['sh_post_meta'][ $post_id ][ $key ] );

	return true;
}

function get_post( $post = null ) {
	if ( $post instanceof WP_Post ) {
		return $post;
	}

	$id = (int) $post;

	return isset( $GLOBALS['sh_posts'][ $id ] ) ? $GLOBALS['sh_posts'][ $id ] : null;
}

function get_the_ID() {
	$first = reset( $GLOBALS['sh_posts'] );

	return $first ? $first->ID : 0;
}

function get_the_title( $post = null ) {
	$post = get_post( $post );

	return $post ? $post->post_title : '';
}

function get_permalink( $post = null ) {
	$post = get_post( $post );

	return $post ? 'https://example.com/?p=' . $post->ID : '';
}

function get_edit_post_link( $post_id ) {
	return 'https://example.com/wp-admin/post.php?post=' . (int) $post_id . '&action=edit';
}

function get_post_status( $post ) {
	$post = get_post( $post );

	return $post ? $post->post_status : false;
}

function get_the_terms( $post, $taxonomy ) {
	$post = get_post( $post );

	if ( ! $post || empty( $GLOBALS['sh_terms'][ $post->ID ][ $taxonomy ] ) ) {
		return false;
	}

	return array_map(
		static function ( $name ) {
			return (object) array( 'name' => $name );
		},
		$GLOBALS['sh_terms'][ $post->ID ][ $taxonomy ]
	);
}

function get_the_author_meta( $field, $user_id = 0 ) {
	return 'Test Author';
}

function get_the_date( $format = '', $post = null ) {
	return '2026-09-15';
}

function get_bloginfo( $show = '' ) {
	return 'description' === $show ? 'A test site' : 'Test Site';
}

function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}

function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
}

function get_post_thumbnail_id( $post = null ) {
	$post = get_post( $post );

	return $post && isset( $post->thumbnail_id ) ? (int) $post->thumbnail_id : 0;
}

function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
	return $attachment_id ? 'https://example.com/image-' . (int) $attachment_id . '.jpg' : false;
}

function wp_specialchars_decode( $text, $quote_style = ENT_QUOTES ) {
	return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wp_strip_all_tags( $text, $remove_breaks = false ) {
	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
	$text = strip_tags( (string) $text );

	return $remove_breaks ? trim( preg_replace( '/[\r\n\t ]+/', ' ', $text ) ) : trim( $text );
}

function strip_shortcodes( $content ) {
	return preg_replace( '/\[[^\]]*\]/', '', (string) $content );
}

function wp_trim_words( $text, $num_words = 55, $more = '…' ) {
	$words = preg_split( '/\s+/u', trim( (string) $text ), -1, PREG_SPLIT_NO_EMPTY );

	if ( count( $words ) <= $num_words ) {
		return implode( ' ', $words );
	}

	return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}

function wp_parse_args( $args, $defaults = array() ) {
	if ( is_object( $args ) ) {
		$args = get_object_vars( $args );
	}

	return array_merge( $defaults, (array) $args );
}

function sanitize_key( $key ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key ) );
}

function sanitize_text_field( $text ) {
	return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( (string) $text ) ) );
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_post_types( $args = array(), $output = 'names' ) {
	$types = array(
		'post' => (object) array(
			'name'   => 'post',
			'labels' => (object) array( 'name' => 'Posts' ),
		),
		'page' => (object) array(
			'name'   => 'page',
			'labels' => (object) array( 'name' => 'Pages' ),
		),
	);

	return 'objects' === $output ? $types : array_combine( array_keys( $types ), array_keys( $types ) );
}

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();

	foreach ( $pairs as $name => $default ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default;
	}

	return $out;
}

function wp_register_style( ...$args ) {
	return true;
}

function wp_register_script( ...$args ) {
	return true;
}

function wp_localize_script( ...$args ) {
	return true;
}

function wp_enqueue_style( $handle ) {
	$GLOBALS['sh_enqueued'][] = $handle;
}

function wp_enqueue_script( $handle ) {
	$GLOBALS['sh_enqueued'][] = $handle;
}

function is_singular( $types = '' ) {
	return (bool) $GLOBALS['sh_singular'];
}

function in_the_loop() {
	return true;
}

function is_main_query() {
	return true;
}

function post_password_required( $post = null ) {
	return false;
}

function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
}

function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$url  = $value;
		$args = $key;
	} else {
		$args = array( $key => $value );
	}

	$separator = false === strpos( (string) $url, '?' ) ? '?' : '&';

	return $url . $separator . http_build_query( $args );
}

function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['sh_http_log'][] = array(
		'url'  => $url,
		'args' => $args,
	);

	if ( ! $GLOBALS['sh_http_queue'] ) {
		return new WP_Error( 'http_request_failed', 'No response queued for ' . $url );
	}

	return array_shift( $GLOBALS['sh_http_queue'] );
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function human_time_diff( $from, $to = 0 ) {
	return '1 minute';
}

function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}

function wp_timezone() {
	return new DateTimeZone( $GLOBALS['sh_timezone'] );
}

function wp_timezone_string() {
	return $GLOBALS['sh_timezone'];
}

function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, (int) $timestamp );
}

function get_post_time( $format = 'U', $gmt = false, $post = null ) {
	return gmdate( $format, 1789000000 );
}

function get_post_modified_time( $format = 'U', $gmt = false, $post = null ) {
	return gmdate( $format, 1789000000 );
}

function wp_get_attachment_image_src( $attachment_id, $size = 'thumbnail' ) {
	if ( ! $attachment_id ) {
		return false;
	}

	$size = isset( $GLOBALS['sh_image_sizes'][ $attachment_id ] )
		? $GLOBALS['sh_image_sizes'][ $attachment_id ]
		: array( 1200, 630 );

	return array( 'https://example.com/image-' . (int) $attachment_id . '.jpg', $size[0], $size[1] );
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['sh_settings_errors'][] = compact( 'setting', 'code', 'message', 'type' );
}

function wp_is_post_revision( $post ) {
	return false;
}

function wp_is_post_autosave( $post ) {
	return false;
}

function wp_next_scheduled( $hook, $args = array() ) {
	return false;
}

function wp_schedule_single_event( $timestamp, $hook, $args = array() ) {
	$GLOBALS['sh_scheduled'][] = compact( 'timestamp', 'hook', 'args' );

	return true;
}

function wp_clear_scheduled_hook( $hook, $args = array() ) {
	return true;
}

function current_user_can( $capability, ...$args ) {
	return true;
}

function checked( $checked, $current = true, $echo = true ) {
	$result = (string) $checked === (string) $current ? ' checked="checked"' : '';

	if ( $echo ) {
		echo $result;
	}

	return $result;
}

function selected( $selected, $current = true, $echo = true ) {
	return checked( $selected, $current, $echo );
}

// phpcs:enable

require_once SOCIAL_HUB_DIR . 'includes/autoload.php';
require_once SOCIAL_HUB_DIR . 'includes/template-tags.php';
