<?php
/**
 * Open Graph and Twitter card tags.
 *
 * @package SocialHub
 */

namespace SocialHub;

defined( 'ABSPATH' ) || exit;

/**
 * Prints the meta tags that control how shared links look.
 */
class Open_Graph {

	/**
	 * Registers the output hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'render' ), 5 );
	}

	/**
	 * Prints the tags.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! $this->should_render() ) {
			return;
		}

		$tags = $this->tags();

		if ( ! $tags ) {
			return;
		}

		echo "\n<!-- Social Hub -->\n";

		foreach ( $tags as $tag ) {
			$attribute = 0 === strpos( $tag['key'], 'twitter:' ) ? 'name' : 'property';

			printf(
				"<meta %s=\"%s\" content=\"%s\" />\n",
				esc_attr( $attribute ),
				esc_attr( $tag['key'] ),
				esc_attr( $tag['value'] )
			);
		}

		echo "<!-- /Social Hub -->\n\n";
	}

	/**
	 * Whether the tags should be printed for the current request.
	 *
	 * @return bool
	 */
	public function should_render() {
		if ( ! Settings::get( 'open_graph.enabled' ) || is_feed() || is_404() ) {
			return false;
		}

		if ( Settings::get( 'open_graph.respect_seo_plugins' ) && self::seo_plugin_active() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether an SEO plugin that already prints Open Graph tags is active.
	 *
	 * @return bool
	 */
	public static function seo_plugin_active() {
		$constants = array(
			'WPSEO_VERSION',
			'RANK_MATH_VERSION',
			'AIOSEO_VERSION',
			'SEOPRESS_VERSION',
			'THE_SEO_FRAMEWORK_VERSION',
			'SLIM_SEO_VER',
		);

		foreach ( $constants as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds the list of tags for the current request.
	 *
	 * @return array[] List of arrays with `key` and `value`.
	 */
	public function tags() {
		$tags = array(
			'og:locale'    => get_locale(),
			'og:site_name' => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'og:type'      => 'website',
			'og:title'     => $this->title(),
			'og:url'       => $this->url(),
		);

		$description = $this->description();

		if ( '' !== $description ) {
			$tags['og:description'] = $description;
		}

		if ( is_singular() && ! is_front_page() ) {
			$post = get_post();

			$tags['og:type'] = is_singular( 'post' ) ? 'article' : 'website';

			if ( $post && 'article' === $tags['og:type'] ) {
				$tags['article:published_time'] = (string) get_post_time( 'c', true, $post );
				$tags['article:modified_time']  = (string) get_post_modified_time( 'c', true, $post );
			}
		}

		$image = $this->image();

		if ( $image ) {
			$tags['og:image'] = $image['url'];

			if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
				$tags['og:image:width']  = (string) $image['width'];
				$tags['og:image:height'] = (string) $image['height'];
			}

			if ( ! empty( $image['alt'] ) ) {
				$tags['og:image:alt'] = $image['alt'];
			}
		}

		$app_id = (string) Settings::get( 'open_graph.fb_app_id' );

		if ( '' !== $app_id ) {
			$tags['fb:app_id'] = $app_id;
		}

		$tags['twitter:card'] = $image ? (string) Settings::get( 'open_graph.twitter_card' ) : 'summary';

		$twitter_site = (string) Settings::get( 'open_graph.twitter_site' );

		if ( '' !== $twitter_site ) {
			$tags['twitter:site'] = '@' . $twitter_site;
		}

		$tags['twitter:title'] = $tags['og:title'];

		if ( '' !== $description ) {
			$tags['twitter:description'] = $description;
		}

		if ( $image ) {
			$tags['twitter:image'] = $image['url'];
		}

		/**
		 * Filters the Open Graph tags before they are printed.
		 *
		 * @param array $tags Tag values keyed by property name.
		 */
		$tags = (array) apply_filters( 'social_hub_open_graph_tags', $tags );

		$output = array();

		foreach ( $tags as $key => $value ) {
			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$output[] = array(
				'key'   => (string) $key,
				'value' => $value,
			);
		}

		return $output;
	}

	/**
	 * Title for the current request.
	 *
	 * @return string
	 */
	private function title() {
		if ( is_front_page() ) {
			return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		}

		if ( is_singular() ) {
			return wp_strip_all_tags( (string) get_the_title() );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return wp_strip_all_tags( (string) single_term_title( '', false ) );
		}

		if ( is_post_type_archive() ) {
			return wp_strip_all_tags( (string) post_type_archive_title( '', false ) );
		}

		return wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	}

	/**
	 * Description for the current request.
	 *
	 * @return string
	 */
	private function description() {
		if ( is_singular() && ! is_front_page() ) {
			$post = get_post();

			if ( ! $post ) {
				return '';
			}

			$excerpt = trim( (string) $post->post_excerpt );

			if ( '' === $excerpt ) {
				$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 40, '…' );
			}

			return trim( wp_strip_all_tags( $excerpt ) );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			return trim( wp_strip_all_tags( (string) term_description() ) );
		}

		return wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES );
	}

	/**
	 * Canonical URL for the current request.
	 *
	 * @return string
	 */
	private function url() {
		if ( is_singular() ) {
			return (string) get_permalink();
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();

			if ( $term && ! empty( $term->term_id ) ) {
				$link = get_term_link( $term );

				if ( ! is_wp_error( $link ) ) {
					return (string) $link;
				}
			}
		}

		return (string) home_url( add_query_arg( array() ) );
	}

	/**
	 * Image used for the preview.
	 *
	 * @return array|null
	 */
	private function image() {
		$image_id = 0;

		if ( is_singular() && has_post_thumbnail() ) {
			$image_id = (int) get_post_thumbnail_id();
		}

		if ( ! $image_id ) {
			$image_id = (int) Settings::get( 'open_graph.default_image', 0 );
		}

		if ( ! $image_id ) {
			return null;
		}

		$source = wp_get_attachment_image_src( $image_id, 'full' );

		if ( ! $source ) {
			return null;
		}

		return array(
			'url'    => $source[0],
			'width'  => isset( $source[1] ) ? (int) $source[1] : 0,
			'height' => isset( $source[2] ) ? (int) $source[2] : 0,
			'alt'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
		);
	}
}
