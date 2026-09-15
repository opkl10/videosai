<?php
/**
 * Pre-share checks that turn promotion advice into something actionable.
 *
 * @package SocialHub
 */

namespace SocialHub;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Looks at a post, or at the whole site, and says what would make a share work harder.
 */
class Advisor {

	const PASS = 'pass';
	const WARN = 'warn';
	const INFO = 'info';

	/**
	 * Width a preview image needs so no network crops or rejects it.
	 */
	const IMAGE_WIDTH = 1200;

	/**
	 * Height that matches the 1.91:1 ratio the networks use.
	 */
	const IMAGE_HEIGHT = 630;

	/**
	 * Characters a headline can use before mobile cuts it off.
	 */
	const TITLE_LIMIT = 70;

	/**
	 * Characters a post shows before the "see more" fold.
	 */
	const MESSAGE_LIMIT = 250;

	/**
	 * Checks for a single post.
	 *
	 * @param WP_Post $post Post about to be shared.
	 * @return array[] Each item has id, status, label and hint.
	 */
	public static function post_checks( WP_Post $post ) {
		$checks = array(
			self::check_image( $post ),
			self::check_title( $post ),
			self::check_excerpt( $post ),
			self::check_message( $post ),
			self::check_hashtags( $post ),
			self::check_engagement( $post ),
		);

		/**
		 * Filters the per-post promotion checks.
		 *
		 * @param array[] $checks Checks.
		 * @param WP_Post $post   Post being checked.
		 */
		return (array) apply_filters( 'social_hub_post_checks', $checks, $post );
	}

	/**
	 * Checks that apply to the whole site.
	 *
	 * @return array[] Each item has id, status, label and hint.
	 */
	public static function site_checks() {
		$checks = array();

		$active   = Providers::active();
		$checks[] = self::result(
			'networks',
			$active ? self::PASS : self::WARN,
			$active
				? sprintf(
					/* translators: %d: number of connected networks. */
					__( 'Connected networks: %d', 'social-hub' ),
					count( $active )
				)
				: __( 'No network is connected', 'social-hub' ),
			__( 'Nothing is published automatically until at least one network is connected and switched on.', 'social-hub' )
		);

		$previews = Settings::get( 'open_graph.enabled' ) || Open_Graph::seo_plugin_active();
		$checks[] = self::result(
			'previews',
			$previews ? self::PASS : self::WARN,
			$previews ? __( 'Link previews are handled', 'social-hub' ) : __( 'Nothing controls your link previews', 'social-hub' ),
			__( 'Without Open Graph tags the networks guess the title and image, and usually guess badly.', 'social-hub' )
		);

		$fallback = (int) Settings::get( 'open_graph.default_image' );
		$checks[] = self::result(
			'fallback-image',
			$fallback ? self::PASS : self::INFO,
			$fallback ? __( 'A fallback preview image is set', 'social-hub' ) : __( 'No fallback preview image', 'social-hub' ),
			__( 'Posts without a featured image fall back to this one instead of sharing a bare link.', 'social-hub' )
		);

		$buttons  = (bool) Settings::get( 'buttons.enabled' );
		$checks[] = self::result(
			'buttons',
			$buttons ? self::PASS : self::INFO,
			$buttons ? __( 'Readers can share your posts', 'social-hub' ) : __( 'Share buttons are switched off', 'social-hub' ),
			__( 'A share from a reader reaches an audience your own page never touches.', 'social-hub' )
		);

		$tracking = (bool) Settings::get( 'tracking.enabled' );
		$checks[] = self::result(
			'tracking',
			$tracking ? self::PASS : self::INFO,
			$tracking ? __( 'Shared links are tagged for analytics', 'social-hub' ) : __( 'Shared links are not tagged', 'social-hub' ),
			__( 'UTM tags are the only way to see which network actually sends you readers, rather than guessing from likes.', 'social-hub' )
		);

		$pretty   = '' !== (string) get_option( 'permalink_structure' );
		$checks[] = self::result(
			'permalinks',
			$pretty ? self::PASS : self::INFO,
			$pretty ? __( 'Readable permalinks', 'social-hub' ) : __( 'Permalinks look like ?p=123', 'social-hub' ),
			__( 'A readable URL gets clicked more often, and survives being pasted into a chat.', 'social-hub' )
		);

		$window   = (bool) Settings::get( 'timing.enabled' );
		$checks[] = self::result(
			'timing',
			$window ? self::PASS : self::INFO,
			$window ? __( 'Shares wait for your best hours', 'social-hub' ) : __( 'Shares go out the moment you publish', 'social-hub' ),
			__( 'A post published at 03:00 burns its first hour of reach while everyone sleeps.', 'social-hub' )
		);

		/**
		 * Filters the site wide promotion checks.
		 *
		 * @param array[] $checks Checks.
		 */
		return (array) apply_filters( 'social_hub_site_checks', $checks );
	}

	/**
	 * Counts how many checks passed.
	 *
	 * @param array[] $checks Checks.
	 * @return int Passed checks.
	 */
	public static function passed( array $checks ) {
		return count(
			array_filter(
				$checks,
				static function ( $check ) {
					return self::PASS === $check['status'];
				}
			)
		);
	}

	/**
	 * Featured image check.
	 *
	 * @param WP_Post $post Post being checked.
	 * @return array
	 */
	private static function check_image( WP_Post $post ) {
		$image_id = (int) get_post_thumbnail_id( $post );

		if ( ! $image_id ) {
			$fallback = (int) Settings::get( 'open_graph.default_image' );

			return self::result(
				'image',
				$fallback ? self::INFO : self::WARN,
				$fallback ? __( 'Using the fallback image', 'social-hub' ) : __( 'No featured image', 'social-hub' ),
				sprintf(
					/* translators: %1$d: image width, %2$d: image height. */
					__( 'A post with a %1$d×%2$d image takes several times more space in the feed than a bare link.', 'social-hub' ),
					self::IMAGE_WIDTH,
					self::IMAGE_HEIGHT
				)
			);
		}

		$source = wp_get_attachment_image_src( $image_id, 'full' );
		$width  = $source && isset( $source[1] ) ? (int) $source[1] : 0;
		$height = $source && isset( $source[2] ) ? (int) $source[2] : 0;

		if ( $width < self::IMAGE_WIDTH || $height < self::IMAGE_HEIGHT ) {
			return self::result(
				'image',
				self::INFO,
				sprintf(
					/* translators: %1$d: image width, %2$d: image height. */
					__( 'Featured image is %1$d×%2$d', 'social-hub' ),
					$width,
					$height
				),
				sprintf(
					/* translators: %1$d: recommended width, %2$d: recommended height. */
					__( 'Below %1$d×%2$d the networks upscale the image and it looks soft.', 'social-hub' ),
					self::IMAGE_WIDTH,
					self::IMAGE_HEIGHT
				)
			);
		}

		return self::result(
			'image',
			self::PASS,
			__( 'Featured image is large enough', 'social-hub' ),
			''
		);
	}

	/**
	 * Headline length check.
	 *
	 * @param WP_Post $post Post being checked.
	 * @return array
	 */
	private static function check_title( WP_Post $post ) {
		$length = mb_strlen( wp_strip_all_tags( get_the_title( $post ) ) );

		if ( $length > self::TITLE_LIMIT ) {
			return self::result(
				'title',
				self::INFO,
				sprintf(
					/* translators: %d: number of characters. */
					__( 'Title is %d characters', 'social-hub' ),
					$length
				),
				sprintf(
					/* translators: %d: recommended number of characters. */
					__( 'Preview cards cut the title around %d characters on a phone.', 'social-hub' ),
					self::TITLE_LIMIT
				)
			);
		}

		return self::result( 'title', self::PASS, __( 'Title fits a preview card', 'social-hub' ), '' );
	}

	/**
	 * Manual excerpt check.
	 *
	 * @param WP_Post $post Post being checked.
	 * @return array
	 */
	private static function check_excerpt( WP_Post $post ) {
		if ( '' === trim( (string) $post->post_excerpt ) ) {
			return self::result(
				'excerpt',
				self::INFO,
				__( 'No manual excerpt', 'social-hub' ),
				__( 'The excerpt is the description under the link. Writing it yourself beats the first sentence of the post.', 'social-hub' )
			);
		}

		return self::result( 'excerpt', self::PASS, __( 'Excerpt written by hand', 'social-hub' ), '' );
	}

	/**
	 * Share message length check.
	 *
	 * @param WP_Post $post Post being checked.
	 * @return array
	 */
	private static function check_message( WP_Post $post ) {
		$length = mb_strlen( ( new Publisher() )->message( $post ) );

		if ( $length > self::MESSAGE_LIMIT ) {
			return self::result(
				'message',
				self::INFO,
				sprintf(
					/* translators: %d: number of characters. */
					__( 'Share text is %d characters', 'social-hub' ),
					$length
				),
				sprintf(
					/* translators: %d: number of characters. */
					__( 'Feeds fold anything past roughly %d characters behind a "see more" link.', 'social-hub' ),
					self::MESSAGE_LIMIT
				)
			);
		}

		return self::result( 'message', self::PASS, __( 'Share text stays above the fold', 'social-hub' ), '' );
	}

	/**
	 * Tag check, since tags become hashtags.
	 *
	 * @param WP_Post $post Post being checked.
	 * @return array
	 */
	private static function check_hashtags( WP_Post $post ) {
		$terms = get_the_terms( $post, 'post_tag' );
		$count = is_array( $terms ) ? count( $terms ) : 0;

		if ( 0 === $count ) {
			return self::result(
				'hashtags',
				self::INFO,
				__( 'No tags', 'social-hub' ),
				__( 'Two or three tags become hashtags in the share text, and give people a reason to find the post later.', 'social-hub' )
			);
		}

		if ( $count > 6 ) {
			return self::result(
				'hashtags',
				self::INFO,
				sprintf(
					/* translators: %d: number of tags. */
					__( '%d tags', 'social-hub' ),
					$count
				),
				__( 'Only the first six become hashtags. A wall of them reads as spam anyway.', 'social-hub' )
			);
		}

		return self::result( 'hashtags', self::PASS, __( 'Tags will become hashtags', 'social-hub' ), '' );
	}

	/**
	 * Call to action check.
	 *
	 * @param WP_Post $post Post being checked.
	 * @return array
	 */
	private static function check_engagement( WP_Post $post ) {
		// Links are dropped first, because a query string is not a question.
		$message = preg_replace( '~https?://\S+~', '', ( new Publisher() )->message( $post ) );

		if ( false === strpos( (string) $message, '?' ) ) {
			return self::result(
				'engagement',
				self::INFO,
				__( 'The share text asks nothing', 'social-hub' ),
				__( 'A question at the end earns comments, and comments are what push a post to people who do not follow you.', 'social-hub' )
			);
		}

		return self::result( 'engagement', self::PASS, __( 'The share text invites a reply', 'social-hub' ), '' );
	}

	/**
	 * Builds a check result.
	 *
	 * @param string $id     Check id.
	 * @param string $status pass, warn or info.
	 * @param string $label  Short result.
	 * @param string $hint   Why it matters.
	 * @return array
	 */
	private static function result( $id, $status, $label, $hint ) {
		return array(
			'id'     => $id,
			'status' => $status,
			'label'  => $label,
			'hint'   => $hint,
		);
	}
}
