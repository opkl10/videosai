<?php
/**
 * REST endpoint used by the editor to turn pasted CSV into chart data.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Parses CSV server side, where quoting, delimiters and the Excel BOM are handled once.
 */
class Rest {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'statista-charts/v1';

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the parse-csv route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/parse-csv',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'parse_csv' ),
				'permission_callback' => array( __CLASS__, 'can_parse' ),
				'args'                => array(
					'csv' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && strlen( $value ) <= 200000;
						},
						'sanitize_callback' => static function ( $value ) {
							return wp_check_invalid_utf8( (string) $value, true );
						},
					),
				),
			)
		);
	}

	/**
	 * Only logged in editors may use the endpoint, and their REST nonce must be valid.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return true|\WP_Error True when allowed.
	 */
	public static function can_parse( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error(
				'statista_charts_bad_nonce',
				__( 'Your session has expired. Reload the editor and try again.', 'statista-charts' ),
				array( 'status' => 403 )
			);
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error(
				'statista_charts_forbidden',
				__( 'You are not allowed to import chart data.', 'statista-charts' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Parses the posted CSV.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response Labels and series.
	 */
	public static function parse_csv( $request ) {
		$parsed = Config::parse_csv( (string) $request->get_param( 'csv' ) );

		return rest_ensure_response( $parsed );
	}
}
