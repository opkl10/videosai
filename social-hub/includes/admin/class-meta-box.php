<?php
/**
 * Per-post sharing controls in the editor.
 *
 * @package SocialHub
 */

namespace SocialHub\Admin;

use SocialHub\Plugin;
use SocialHub\Providers;
use SocialHub\Publisher;
use SocialHub\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the share status of a post and lets an editor override it.
 */
class Meta_Box {

	const NONCE = 'social_hub_meta_box';

	/**
	 * Registers the meta box hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Adds the meta box to the shareable post types.
	 *
	 * @return void
	 */
	public function add() {
		$post_types = (array) Settings::get( 'post_types', array() );

		if ( ! $post_types ) {
			return;
		}

		add_meta_box(
			'social-hub',
			__( 'Social Hub', 'social-hub' ),
			array( $this, 'render' ),
			$post_types,
			'side',
			'default'
		);
	}

	/**
	 * Renders the meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );

		$skip    = (bool) get_post_meta( $post->ID, Publisher::META_SKIP, true );
		$message = (string) get_post_meta( $post->ID, Publisher::META_MESSAGE, true );
		$active  = Providers::active();
		?>
		<div class="social-hub-box">
			<?php if ( ! $active ) : ?>
				<p class="social-hub-box__empty">
					<?php esc_html_e( 'No network is connected yet.', 'social-hub' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::PAGE ) ); ?>"><?php esc_html_e( 'Connect one', 'social-hub' ); ?></a>
				</p>
			<?php else : ?>
				<p class="social-hub-box__networks">
					<?php
					printf(
						/* translators: %s: comma separated network names. */
						esc_html__( 'Will be shared to: %s', 'social-hub' ),
						esc_html(
							implode(
								', ',
								array_map(
									static function ( $provider ) {
										return $provider->label();
									},
									$active
								)
							)
						)
					);
					?>
				</p>
			<?php endif; ?>

			<?php
			$scheduled = wp_next_scheduled( Publisher::CRON_HOOK, array( $post->ID ) );

			if ( $scheduled ) :
				?>
				<p class="social-hub-box__scheduled">
					<?php
					printf(
						/* translators: %s: formatted date and time. */
						esc_html__( 'Queued for %s', 'social-hub' ),
						esc_html( wp_date( 'j M, H:i', (int) $scheduled ) )
					);
					?>
				</p>
			<?php endif; ?>

			<div data-social-hub-status><?php echo self::status_html( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped while built. ?></div>

			<p>
				<label>
					<input type="checkbox" name="social_hub_skip" value="1" <?php checked( $skip ); ?> />
					<?php esc_html_e( 'Do not share this post', 'social-hub' ); ?>
				</label>
			</p>

			<p>
				<label for="social-hub-custom-message"><?php esc_html_e( 'Custom message', 'social-hub' ); ?></label>
				<textarea
					id="social-hub-custom-message"
					name="social_hub_message"
					rows="4"
					class="widefat"
					placeholder="<?php echo esc_attr( (string) Settings::get( 'message_template' ) ); ?>"
				><?php echo esc_textarea( $message ); ?></textarea>
			</p>

			<?php if ( $active && 'publish' === $post->post_status ) : ?>
				<p>
					<button
						type="button"
						class="button"
						data-social-hub-share-now="<?php echo esc_attr( (string) $post->ID ); ?>"
					><?php esc_html_e( 'Share now', 'social-hub' ); ?></button>
					<span class="social-hub-box__result" data-social-hub-share-result></span>
				</p>
				<p class="description"><?php esc_html_e( 'Sharing now posts again even if this post was already shared.', 'social-hub' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Saves the per-post overrides.
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		if ( isset( $_POST['social_hub_skip'] ) ) {
			update_post_meta( $post_id, Publisher::META_SKIP, 1 );
		} else {
			delete_post_meta( $post_id, Publisher::META_SKIP );
		}

		$message = isset( $_POST['social_hub_message'] )
			? trim( wp_strip_all_tags( wp_unslash( $_POST['social_hub_message'] ) ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- tags stripped above.
			: '';

		if ( '' === $message ) {
			delete_post_meta( $post_id, Publisher::META_MESSAGE );
		} else {
			update_post_meta( $post_id, Publisher::META_MESSAGE, $message );
		}
	}

	/**
	 * Builds the share status list of a post.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	public static function status_html( $post_id ) {
		$shares = Plugin::instance()->publisher()->shares( $post_id );

		if ( ! $shares ) {
			return '';
		}

		$items = '';

		foreach ( $shares as $network => $share ) {
			$provider = Providers::get( $network );
			$label    = $provider ? $provider->label() : $network;
			$status   = isset( $share['status'] ) ? $share['status'] : 'error';
			$link     = ! empty( $share['url'] )
				? sprintf(
					' <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $share['url'] ),
					esc_html__( 'View', 'social-hub' )
				)
				: '';

			$items .= sprintf(
				'<li class="social-hub-box__status social-hub-box__status--%1$s"><strong>%2$s</strong> %3$s%4$s</li>',
				esc_attr( $status ),
				esc_html( $label ),
				esc_html(
					'success' === $status
						? sprintf(
							/* translators: %s: human readable time difference. */
							__( 'shared %s ago', 'social-hub' ),
							human_time_diff( isset( $share['time'] ) ? (int) $share['time'] : time() )
						)
						: (string) ( isset( $share['message'] ) ? $share['message'] : '' )
				),
				$link
			);
		}

		return '<ul class="social-hub-box__statuses">' . $items . '</ul>';
	}

	/**
	 * Builds a short summary of a manual share.
	 *
	 * @param array<string, array> $results Share results keyed by network.
	 * @return string
	 */
	public static function format_results( array $results ) {
		if ( ! $results ) {
			return __( 'Nothing was shared.', 'social-hub' );
		}

		$messages = array();

		foreach ( $results as $network => $result ) {
			$provider = Providers::get( $network );
			$label    = $provider ? $provider->label() : $network;

			$messages[] = 'success' === $result['status']
				? sprintf(
					/* translators: %s: network name. */
					__( '%s: done', 'social-hub' ),
					$label
				)
				: sprintf(
					/* translators: %1$s: network name, %2$s: error message. */
					__( '%1$s: %2$s', 'social-hub' ),
					$label,
					$result['message']
				);
		}

		return implode( ' · ', $messages );
	}
}
