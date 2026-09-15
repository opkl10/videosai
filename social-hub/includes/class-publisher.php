<?php
/**
 * Shares published posts to the configured networks.
 *
 * @package SocialHub
 */

namespace SocialHub;

use SocialHub\Providers\Provider;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Decides what gets shared, when, and with which text.
 */
class Publisher {

	const CRON_HOOK   = 'social_hub_share_post';
	const META_SHARES = '_social_hub_shares';
	const META_SKIP   = '_social_hub_skip';
	const META_MESSAGE = '_social_hub_message';

	/**
	 * Registers the hooks that trigger sharing.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'transition_post_status', array( $this, 'maybe_schedule' ), 10, 3 );
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled' ) );
	}

	/**
	 * Queues a share when a post becomes published for the first time.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post object.
	 * @return void
	 */
	public function maybe_schedule( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( ! Settings::get( 'auto_share' ) || ! $this->is_shareable_type( $post->post_type ) ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( ! Providers::active() ) {
			return;
		}

		/**
		 * Filters whether a post should be shared automatically.
		 *
		 * @param bool    $should_share Whether to share.
		 * @param WP_Post $post         Post being published.
		 */
		if ( ! apply_filters( 'social_hub_should_share', true, $post ) ) {
			return;
		}

		$args = array( $post->ID );

		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}

		$delay = (int) Settings::get( 'share_delay', 30 );

		wp_schedule_single_event( Timing::next_slot( time() + max( 0, $delay ) ), self::CRON_HOOK, $args );
	}

	/**
	 * Cron callback.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public function run_scheduled( $post_id ) {
		$this->share( (int) $post_id );
	}

	/**
	 * Shares a post to the requested networks.
	 *
	 * @param int      $post_id  Post id.
	 * @param string[] $networks Network ids; empty means every active network.
	 * @param bool     $force    Share again even if the post was already shared.
	 * @return array<string, array> Results keyed by network id.
	 */
	public function share( $post_id, array $networks = array(), $force = false ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return array();
		}

		if ( ! $force && get_post_meta( $post->ID, self::META_SKIP, true ) ) {
			return array();
		}

		$providers = $networks
			? array_intersect_key( Providers::all(), array_flip( $networks ) )
			: Providers::active();

		$shares  = $this->shares( $post->ID );
		$results = array();

		foreach ( $providers as $id => $provider ) {
			if ( ! $force && isset( $shares[ $id ]['status'] ) && 'success' === $shares[ $id ]['status'] ) {
				continue;
			}

			$results[ $id ] = $this->publish_to( $provider, $post );
			$shares[ $id ]  = $results[ $id ];
		}

		if ( $results ) {
			update_post_meta( $post->ID, self::META_SHARES, $shares );
		}

		return $results;
	}

	/**
	 * Sends one post to one provider and records the outcome.
	 *
	 * @param Provider $provider Target provider.
	 * @param WP_Post  $post     Post to share.
	 * @return array
	 */
	private function publish_to( Provider $provider, WP_Post $post ) {
		$payload  = $this->payload( $post, $provider );
		$response = $provider->publish( $payload );

		if ( is_wp_error( $response ) ) {
			$result = array(
				'status'  => 'error',
				'message' => $response->get_error_message(),
				'url'     => '',
				'id'      => '',
				'time'    => time(),
			);
		} else {
			$result = array(
				'status'  => 'success',
				'message' => sprintf(
					/* translators: %s: network name. */
					__( 'Shared to %s.', 'social-hub' ),
					$provider->label()
				),
				'url'     => isset( $response['url'] ) ? (string) $response['url'] : '',
				'id'      => isset( $response['id'] ) ? (string) $response['id'] : '',
				'time'    => time(),
			);
		}

		Log::add(
			array(
				'network' => $provider->id(),
				'post_id' => $post->ID,
				'status'  => $result['status'],
				'message' => $result['message'],
				'url'     => $result['url'],
			)
		);

		/**
		 * Fires after a share attempt, successful or not.
		 *
		 * @param array    $result   Result data.
		 * @param Provider $provider Provider used.
		 * @param WP_Post  $post     Shared post.
		 */
		do_action( 'social_hub_shared', $result, $provider, $post );

		return $result;
	}

	/**
	 * Builds the payload sent to a provider.
	 *
	 * @param WP_Post  $post     Post to share.
	 * @param Provider $provider Target provider.
	 * @return array
	 */
	public function payload( WP_Post $post, Provider $provider ) {
		$payload = array(
			'message'   => $this->message( $post, $provider->id() ),
			'url'       => $this->permalink( $post, $provider->id() ),
			'title'     => wp_strip_all_tags( get_the_title( $post ) ),
			'image_url' => $this->image_url( $post ),
		);

		/**
		 * Filters the payload sent to a network.
		 *
		 * @param array    $payload  Payload data.
		 * @param WP_Post  $post     Shared post.
		 * @param Provider $provider Target provider.
		 */
		return (array) apply_filters( 'social_hub_share_payload', $payload, $post, $provider );
	}

	/**
	 * Renders the share message for a post.
	 *
	 * @param WP_Post $post    Post to share.
	 * @param string  $network Network the message is meant for, used for link tracking.
	 * @return string
	 */
	public function message( WP_Post $post, $network = '' ) {
		$custom   = (string) get_post_meta( $post->ID, self::META_MESSAGE, true );
		$template = '' !== trim( $custom ) ? $custom : (string) Settings::get( 'message_template' );
		$message  = $this->render_template( $template, $post, $network );

		/**
		 * Filters the rendered share message.
		 *
		 * @param string  $message Rendered message.
		 * @param WP_Post $post    Shared post.
		 */
		return (string) apply_filters( 'social_hub_message', $message, $post );
	}

	/**
	 * Replaces the placeholders of a message template.
	 *
	 * @param string  $template Template text.
	 * @param WP_Post $post     Post providing the values.
	 * @param string  $network  Network the message is meant for, used for link tracking.
	 * @return string
	 */
	public function render_template( $template, WP_Post $post, $network = '' ) {
		$replacements = array(
			'{title}'      => wp_strip_all_tags( get_the_title( $post ) ),
			'{excerpt}'    => $this->excerpt( $post ),
			'{url}'        => $this->permalink( $post, $network ),
			'{site}'       => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'{author}'     => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'{tags}'       => $this->hashtags( $post, 'post_tag' ),
			'{categories}' => $this->hashtags( $post, 'category' ),
			'{date}'       => get_the_date( '', $post ),
		);

		$message = str_replace( array_keys( $replacements ), array_values( $replacements ), (string) $template );
		$message = preg_replace( "/\n{3,}/", "\n\n", $message );

		return trim( (string) $message );
	}

	/**
	 * Returns the permalink of a post, tagged for analytics when tracking is on.
	 *
	 * @param WP_Post $post    Post object.
	 * @param string  $network Network the link is meant for.
	 * @return string
	 */
	private function permalink( WP_Post $post, $network ) {
		$url = (string) get_permalink( $post );

		return '' !== (string) $network ? Tracking::for_share( $url, $network, $post ) : $url;
	}

	/**
	 * Returns a short plain text excerpt.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function excerpt( WP_Post $post ) {
		$excerpt = trim( (string) $post->post_excerpt );

		if ( '' === $excerpt ) {
			$excerpt = strip_shortcodes( (string) $post->post_content );
			$excerpt = wp_strip_all_tags( $excerpt );
			$excerpt = wp_trim_words( $excerpt, 40, '…' );
		}

		return trim( wp_strip_all_tags( $excerpt ) );
	}

	/**
	 * Builds hashtags from the terms of a taxonomy.
	 *
	 * @param WP_Post $post     Post object.
	 * @param string  $taxonomy Taxonomy name.
	 * @return string
	 */
	private function hashtags( WP_Post $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$tags = array();

		foreach ( $terms as $term ) {
			$name = preg_replace( '/[\s\-\.]+/u', '_', trim( $term->name ) );
			$name = preg_replace( '/[^\p{L}\p{N}_]/u', '', (string) $name );

			if ( '' !== $name ) {
				$tags[] = '#' . $name;
			}
		}

		return implode( ' ', array_slice( $tags, 0, 6 ) );
	}

	/**
	 * Returns the image used for the share, if any.
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function image_url( WP_Post $post ) {
		$image_id = (int) get_post_thumbnail_id( $post );

		if ( ! $image_id ) {
			$image_id = (int) Settings::get( 'open_graph.default_image', 0 );
		}

		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, 'full' );

		return $url ? $url : '';
	}

	/**
	 * Returns the recorded share results of a post.
	 *
	 * @param int $post_id Post id.
	 * @return array<string, array>
	 */
	public function shares( $post_id ) {
		$shares = get_post_meta( (int) $post_id, self::META_SHARES, true );

		return is_array( $shares ) ? $shares : array();
	}

	/**
	 * Whether a post type may be shared.
	 *
	 * @param string $post_type Post type name.
	 * @return bool
	 */
	public function is_shareable_type( $post_type ) {
		return in_array( $post_type, (array) Settings::get( 'post_types', array() ), true );
	}
}
