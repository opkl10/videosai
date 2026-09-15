<?php
/**
 * Front-end share buttons.
 *
 * @package SocialHub
 */

namespace SocialHub;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the share buttons and keeps their assets registered.
 */
class Share_Buttons {

	const HANDLE = 'social-hub-share';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'the_content', array( $this, 'append_to_content' ), 20 );
		add_shortcode( 'social_hub_share', array( $this, 'shortcode' ) );
	}

	/**
	 * The networks that can be shown, keyed by id.
	 *
	 * @return array<string, array>
	 */
	public static function networks() {
		return array(
			'facebook' => array(
				'label'    => __( 'Facebook', 'social-hub' ),
				'color'    => '#1877f2',
				'template' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
				'icon'     => '<path d="M13.6 21.9v-8.2h2.8l.4-3.2h-3.2V8.4c0-.9.3-1.6 1.6-1.6h1.7V4c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.4H7.4v3.2h2.8v8.2h3.4z"/>',
			),
			'x'        => array(
				'label'    => __( 'X', 'social-hub' ),
				'color'    => '#0f1419',
				'template' => 'https://twitter.com/intent/tweet?url={url}&text={title}',
				'icon'     => '<path d="M17.5 3h3.1l-6.8 7.8L21.8 21h-6.3l-4.9-6.4L4.9 21H1.8l7.3-8.3L1.5 3h6.4l4.4 5.8L17.5 3zm-1.1 16.1h1.7L7.2 4.8H5.4l11 14.3z"/>',
			),
			'whatsapp' => array(
				'label'    => __( 'WhatsApp', 'social-hub' ),
				'color'    => '#25d366',
				'template' => 'https://api.whatsapp.com/send?text={title}%20{url}',
				'icon'     => '<path d="M12 2.2a9.8 9.8 0 0 0-8.4 14.8L2.2 21.8l4.9-1.3A9.8 9.8 0 1 0 12 2.2zm0 1.8a8 8 0 1 1-4.1 14.9l-.3-.2-2.6.7.7-2.5-.2-.3A8 8 0 0 1 12 4z"/><path d="M9.3 7.4c-.2-.5-.4-.5-.6-.5h-.5c-.2 0-.6.1-.9.4-.3.3-1.1 1.1-1.1 2.6s1.1 3 1.3 3.2c.2.2 2.2 3.4 5.4 4.6 2.6 1 3.2.8 3.8.8.6-.1 1.8-.8 2.1-1.5.3-.7.3-1.4.2-1.5-.1-.1-.3-.2-.6-.4l-2-1c-.3-.1-.5-.1-.7.1l-.8 1c-.1.2-.3.2-.6.1-.3-.2-1.3-.5-2.5-1.6-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.5-.6c.1-.2.2-.3.2-.5.1-.2 0-.4 0-.5l-.6-1.9z"/>',
			),
			'telegram' => array(
				'label'    => __( 'Telegram', 'social-hub' ),
				'color'    => '#229ed9',
				'template' => 'https://t.me/share/url?url={url}&text={title}',
				'icon'     => '<path d="M21.6 4.2 2.9 11.4c-.9.3-.9 1.6.1 1.9l4.6 1.4 1.8 5.6c.2.7 1.1.9 1.6.3l2.5-2.7 4.6 3.4c.6.4 1.4.1 1.6-.6l3-14.9c.2-.9-.7-1.7-1.5-1.4zM8.9 14.1l9-5.7-7.5 6.6-.4 3.4-1.1-4.3z"/>',
			),
			'linkedin' => array(
				'label'    => __( 'LinkedIn', 'social-hub' ),
				'color'    => '#0a66c2',
				'template' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}',
				'icon'     => '<path d="M6.9 8.7H3.6V21h3.3V8.7zM5.2 3.2a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8zM21 14.3c0-3.4-1.8-5-4.2-5-1.9 0-2.8 1.1-3.3 1.8V8.7h-3.3c0 .9 0 12.3 0 12.3h3.3v-6.9c0-.4 0-.7.1-1 .3-.7.9-1.5 2-1.5 1.4 0 2 1.1 2 2.7V21H21v-6.7z"/>',
			),
			'email'    => array(
				'label'    => __( 'Email', 'social-hub' ),
				'color'    => '#5b6470',
				'template' => 'mailto:?subject={title}&body={url}',
				'icon'     => '<path d="M3 5.5h18c.8 0 1.5.7 1.5 1.5v10c0 .8-.7 1.5-1.5 1.5H3c-.8 0-1.5-.7-1.5-1.5V7c0-.8.7-1.5 1.5-1.5zm.5 2.2v.4l8.5 5 8.5-5v-.4h-17zm17 2.5-8 4.7c-.3.2-.7.2-1 0l-8-4.7v6.1h17v-6.1z"/>',
			),
			'copy'     => array(
				'label'    => __( 'Copy link', 'social-hub' ),
				'color'    => '#40464f',
				'template' => '',
				'icon'     => '<path d="M10.2 13.9a1 1 0 0 1 0-1.4l2.3-2.3a1 1 0 0 1 1.4 1.4l-2.3 2.3a1 1 0 0 1-1.4 0z"/><path d="M13.1 6.3a3.6 3.6 0 0 1 5.1 5.1l-2.6 2.6a3.6 3.6 0 0 1-5.1 0l1.4-1.4a1.6 1.6 0 0 0 2.3 0l2.6-2.6a1.6 1.6 0 0 0-2.3-2.3l-1.1 1.1-1.4-1.4 1.1-1.1zM10.9 17.7a3.6 3.6 0 0 1-5.1-5.1l2.6-2.6a3.6 3.6 0 0 1 5.1 0l-1.4 1.4a1.6 1.6 0 0 0-2.3 0l-2.6 2.6a1.6 1.6 0 0 0 2.3 2.3l1.1-1.1 1.4 1.4-1.1 1.1z"/>',
			),
		);
	}

	/**
	 * Registers the front-end assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			self::HANDLE,
			SOCIAL_HUB_URL . 'assets/css/share.css',
			array(),
			SOCIAL_HUB_VERSION
		);

		wp_register_script(
			self::HANDLE,
			SOCIAL_HUB_URL . 'assets/js/share.js',
			array(),
			SOCIAL_HUB_VERSION,
			true
		);

		wp_localize_script(
			self::HANDLE,
			'socialHubShare',
			array(
				'copied'      => __( 'Link copied', 'social-hub' ),
				'copyFailed'  => __( 'Copy failed', 'social-hub' ),
				'popupWidth'  => 620,
				'popupHeight' => 640,
			)
		);

		if ( $this->auto_append_position() && is_singular( (array) Settings::get( 'buttons.post_types', array() ) ) ) {
			$this->enqueue();
		}
	}

	/**
	 * Enqueues the assets on demand.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Appends the buttons to the post content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function append_to_content( $content ) {
		$position = $this->auto_append_position();

		if ( ! $position || ! is_singular( (array) Settings::get( 'buttons.post_types', array() ) ) ) {
			return $content;
		}

		if ( ! in_the_loop() || ! is_main_query() || post_password_required() ) {
			return $content;
		}

		$buttons = $this->render();

		if ( '' === $buttons ) {
			return $content;
		}

		if ( 'before' === $position ) {
			return $buttons . $content;
		}

		if ( 'both' === $position ) {
			return $buttons . $content . $buttons;
		}

		return $content . $buttons;
	}

	/**
	 * Shortcode handler.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'networks' => '',
				'heading'  => null,
				'style'    => '',
				'labels'   => '',
				'post_id'  => 0,
			),
			is_array( $atts ) ? $atts : array(),
			'social_hub_share'
		);

		$args = array();

		if ( '' !== $atts['networks'] ) {
			$args['networks'] = array_filter( array_map( 'sanitize_key', explode( ',', $atts['networks'] ) ) );
		}

		if ( null !== $atts['heading'] ) {
			$args['heading'] = sanitize_text_field( $atts['heading'] );
		}

		if ( '' !== $atts['style'] ) {
			$args['style'] = sanitize_key( $atts['style'] );
		}

		if ( '' !== $atts['labels'] ) {
			$args['show_labels'] = in_array( strtolower( $atts['labels'] ), array( '1', 'true', 'yes' ), true );
		}

		if ( $atts['post_id'] ) {
			$args['post_id'] = (int) $atts['post_id'];
		}

		return $this->render( $args );
	}

	/**
	 * Builds the buttons markup.
	 *
	 * @param array $args Overrides for the stored settings.
	 * @return string
	 */
	public function render( array $args = array() ) {
		$defaults = array(
			'post_id'     => get_the_ID(),
			'networks'    => (array) Settings::get( 'buttons.networks', array() ),
			'heading'     => (string) Settings::get( 'buttons.heading', '' ),
			'style'       => (string) Settings::get( 'buttons.style', 'filled' ),
			'show_labels' => (bool) Settings::get( 'buttons.show_labels', true ),
		);

		$args      = wp_parse_args( $args, $defaults );
		$available = self::networks();
		$networks  = array_values( array_intersect( array_map( 'sanitize_key', (array) $args['networks'] ), array_keys( $available ) ) );

		if ( ! Settings::get( 'buttons.enabled' ) || ! $networks ) {
			return '';
		}

		$post = get_post( $args['post_id'] );

		if ( ! $post ) {
			return '';
		}

		$url     = (string) get_permalink( $post );
		$title   = wp_strip_all_tags( (string) get_the_title( $post ) );
		$heading = '' !== trim( (string) $args['heading'] ) ? $args['heading'] : __( 'Share this post', 'social-hub' );
		$style   = 'outline' === $args['style'] ? 'outline' : 'filled';

		$this->enqueue();

		$items = '';

		foreach ( $networks as $id ) {
			$items .= $this->render_item( $id, $available[ $id ], $url, $title, (bool) $args['show_labels'] );
		}

		$markup = sprintf(
			'<div class="social-hub-share social-hub-share--%1$s%2$s">%3$s<ul class="social-hub-share__list">%4$s</ul></div>',
			esc_attr( $style ),
			$args['show_labels'] ? '' : ' social-hub-share--icons-only',
			'' !== $heading ? '<p class="social-hub-share__heading">' . esc_html( $heading ) . '</p>' : '',
			$items
		);

		/**
		 * Filters the rendered share buttons markup.
		 *
		 * @param string $markup Buttons markup.
		 * @param array  $args   Render arguments.
		 */
		return (string) apply_filters( 'social_hub_share_buttons_html', $markup, $args );
	}

	/**
	 * Renders a single button.
	 *
	 * @param string $id          Network id.
	 * @param array  $network     Network definition.
	 * @param string $url         Shared URL.
	 * @param string $title       Shared title.
	 * @param bool   $show_labels Whether to print the text label.
	 * @return string
	 */
	private function render_item( $id, array $network, $url, $title, $show_labels ) {
		$icon  = sprintf(
			'<svg class="social-hub-share__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="currentColor">%s</svg>',
			$network['icon']
		);
		$label = $show_labels
			? '<span class="social-hub-share__label">' . esc_html( $network['label'] ) . '</span>'
			: '';
		$style = sprintf( 'style="--social-hub-color:%s"', esc_attr( $network['color'] ) );
		$class = sprintf( 'social-hub-share__button social-hub-share__button--%s', esc_attr( $id ) );

		if ( 'copy' === $id ) {
			return sprintf(
				'<li class="social-hub-share__item"><button type="button" class="%1$s" %2$s data-social-hub-copy="%3$s" aria-label="%4$s">%5$s%6$s</button></li>',
				esc_attr( $class ),
				$style,
				esc_url( $url ),
				esc_attr( $network['label'] ),
				$icon,
				$label
			);
		}

		$href = str_replace(
			array( '{url}', '{title}' ),
			array( rawurlencode( $url ), rawurlencode( $title ) ),
			$network['template']
		);

		$popup = 0 === strpos( $network['template'], 'mailto:' ) ? '' : ' data-social-hub-popup="1"';
		$aria  = sprintf(
			/* translators: %s: network name. */
			__( 'Share on %s', 'social-hub' ),
			$network['label']
		);

		return sprintf(
			'<li class="social-hub-share__item"><a class="%1$s" %2$s href="%3$s" target="_blank" rel="noopener nofollow"%4$s aria-label="%5$s">%6$s%7$s</a></li>',
			esc_attr( $class ),
			$style,
			esc_url( $href, array( 'http', 'https', 'mailto' ) ),
			$popup,
			esc_attr( $aria ),
			$icon,
			$label
		);
	}

	/**
	 * Returns the automatic position, or an empty string when buttons are manual or disabled.
	 *
	 * @return string
	 */
	private function auto_append_position() {
		if ( ! Settings::get( 'buttons.enabled' ) ) {
			return '';
		}

		$position = (string) Settings::get( 'buttons.position', 'after' );

		return 'manual' === $position ? '' : $position;
	}
}
