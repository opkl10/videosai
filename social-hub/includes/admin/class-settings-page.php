<?php
/**
 * Renders the Social Hub settings screen.
 *
 * @package SocialHub
 */

namespace SocialHub\Admin;

use SocialHub\Log;
use SocialHub\Open_Graph;
use SocialHub\Plugin;
use SocialHub\Providers;
use SocialHub\Settings;
use SocialHub\Share_Buttons;

defined( 'ABSPATH' ) || exit;

/**
 * Tabbed settings UI.
 */
class Settings_Page {

	/**
	 * Renders the whole screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'social-hub' ) );
		}

		$tabs = $this->tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'general';
		?>
		<div class="wrap social-hub-wrap">
			<h1><?php esc_html_e( 'Social Hub', 'social-hub' ); ?></h1>
			<p class="social-hub-intro">
				<?php esc_html_e( 'Publish new posts to your social pages automatically, add share buttons for your readers, and make sure shared links show the right title and image.', 'social-hub' ); ?>
			</p>

			<?php $this->render_status(); ?>

			<nav class="nav-tab-wrapper social-hub-tabs">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . Admin::PAGE . '&tab=' . $slug ) ); ?>"
					><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			if ( 'log' === $tab ) {
				$this->render_log();
			} else {
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php
					settings_fields( Settings::GROUP );
					$this->hidden( '_section', $tab );

					switch ( $tab ) {
						case 'facebook':
							$this->render_facebook();
							break;
						case 'telegram':
							$this->render_telegram();
							break;
						case 'buttons':
							$this->render_buttons();
							break;
						case 'preview':
							$this->render_preview();
							break;
						default:
							$this->render_general();
					}

					submit_button();
					?>
				</form>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Tab labels keyed by slug.
	 *
	 * @return array<string, string>
	 */
	private function tabs() {
		return array(
			'general'  => __( 'General', 'social-hub' ),
			'facebook' => __( 'Facebook', 'social-hub' ),
			'telegram' => __( 'Telegram', 'social-hub' ),
			'buttons'  => __( 'Share buttons', 'social-hub' ),
			'preview'  => __( 'Link previews', 'social-hub' ),
			'log'      => __( 'Activity log', 'social-hub' ),
		);
	}

	/**
	 * Shows a one line connection summary for every network.
	 *
	 * @return void
	 */
	private function render_status() {
		echo '<ul class="social-hub-status">';

		foreach ( Providers::all() as $provider ) {
			if ( $provider->is_active() ) {
				$state = 'ok';
				$text  = __( 'Connected and sharing', 'social-hub' );
			} elseif ( $provider->is_configured() ) {
				$state = 'warn';
				$text  = __( 'Configured but switched off', 'social-hub' );
			} else {
				$state = 'off';
				$text  = __( 'Not connected', 'social-hub' );
			}

			printf(
				'<li class="social-hub-status__item social-hub-status__item--%1$s"><strong>%2$s</strong><span>%3$s</span></li>',
				esc_attr( $state ),
				esc_html( $provider->label() ),
				esc_html( $text )
			);
		}

		echo '</ul>';
	}

	/**
	 * General tab.
	 *
	 * @return void
	 */
	private function render_general() {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic sharing', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox( 'auto_share', Settings::get( 'auto_share' ), __( 'Share a post to the connected networks when it is published', 'social-hub' ) );
					?>
					<p class="description"><?php esc_html_e( 'Only the first transition to "published" triggers a share, so editing an old post will not repost it.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Post types', 'social-hub' ); ?></th>
				<td><?php $this->post_types( 'post_types', (array) Settings::get( 'post_types', array() ) ); ?></td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-share-delay"><?php esc_html_e( 'Delay before sharing', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="number"
						min="0"
						max="3600"
						step="5"
						id="social-hub-share-delay"
						name="<?php echo esc_attr( $this->name( 'share_delay' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'share_delay' ) ); ?>"
						class="small-text"
					/>
					<?php esc_html_e( 'seconds', 'social-hub' ); ?>
					<p class="description"><?php esc_html_e( 'A short delay gives you time to fix a typo, and lets caches warm up before the link preview is fetched.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-template"><?php esc_html_e( 'Message template', 'social-hub' ); ?></label>
				</th>
				<td>
					<textarea
						id="social-hub-template"
						name="<?php echo esc_attr( $this->name( 'message_template' ) ); ?>"
						rows="5"
						class="large-text code"
					><?php echo esc_textarea( (string) Settings::get( 'message_template' ) ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available placeholders:', 'social-hub' ); ?>
						<code>{title}</code> <code>{excerpt}</code> <code>{url}</code> <code>{site}</code>
						<code>{author}</code> <code>{tags}</code> <code>{categories}</code> <code>{date}</code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox(
						'delete_data_on_uninstall',
						Settings::get( 'delete_data_on_uninstall' ),
						__( 'Delete all Social Hub settings and logs when the plugin is deleted', 'social-hub' )
					);
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Facebook tab.
	 *
	 * @return void
	 */
	private function render_facebook() {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox( 'facebook[enabled]', Settings::get( 'facebook.enabled' ), __( 'Share new posts to this Facebook Page', 'social-hub' ) );
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-fb-page"><?php esc_html_e( 'Page ID', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-fb-page"
						class="regular-text"
						name="<?php echo esc_attr( $this->name( 'facebook[page_id]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'facebook.page_id' ) ); ?>"
						autocomplete="off"
					/>
					<p class="description"><?php esc_html_e( 'Facebook Page settings → About → Page ID. A numeric ID works best.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-fb-token"><?php esc_html_e( 'Page access token', 'social-hub' ); ?></label>
				</th>
				<td>
					<?php $this->secret( 'facebook', 'access_token', 'social-hub-fb-token' ); ?>
					<p class="description"><?php esc_html_e( 'Use a long-lived Page access token with the pages_manage_posts permission. It is stored in your database, so treat it like a password.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-fb-format"><?php esc_html_e( 'Post format', 'social-hub' ); ?></label>
				</th>
				<td>
					<select id="social-hub-fb-format" name="<?php echo esc_attr( $this->name( 'facebook[post_format]' ) ); ?>">
						<option value="link" <?php selected( 'link', Settings::get( 'facebook.post_format' ) ); ?>>
							<?php esc_html_e( 'Link post (Facebook builds the preview)', 'social-hub' ); ?>
						</option>
						<option value="photo" <?php selected( 'photo', Settings::get( 'facebook.post_format' ) ); ?>>
							<?php esc_html_e( 'Photo post using the featured image', 'social-hub' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-fb-version"><?php esc_html_e( 'Graph API version', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-fb-version"
						class="small-text"
						name="<?php echo esc_attr( $this->name( 'facebook[api_version]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'facebook.api_version' ) ); ?>"
					/>
					<p class="description"><?php esc_html_e( 'Meta retires each version after about two years. Bump this when you migrate.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'social-hub' ); ?></th>
				<td><?php $this->test_button( 'facebook' ); ?></td>
			</tr>
		</table>

		<div class="social-hub-help">
			<h2><?php esc_html_e( 'Getting a Page access token', 'social-hub' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Create an app at developers.facebook.com and add the "Facebook Login" product.', 'social-hub' ); ?></li>
				<li><?php esc_html_e( 'Open the Graph API Explorer, pick your app, and request the pages_manage_posts, pages_read_engagement and pages_show_list permissions.', 'social-hub' ); ?></li>
				<li><?php esc_html_e( 'Select your Page in the token dropdown to swap the user token for a Page token.', 'social-hub' ); ?></li>
				<li><?php esc_html_e( 'Exchange it for a long-lived token in the Access Token Debugger, then paste it above.', 'social-hub' ); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Telegram tab.
	 *
	 * @return void
	 */
	private function render_telegram() {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox( 'telegram[enabled]', Settings::get( 'telegram.enabled' ), __( 'Share new posts to this Telegram chat', 'social-hub' ) );
					?>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-tg-token"><?php esc_html_e( 'Bot token', 'social-hub' ); ?></label>
				</th>
				<td>
					<?php $this->secret( 'telegram', 'bot_token', 'social-hub-tg-token' ); ?>
					<p class="description"><?php esc_html_e( 'Create a bot with @BotFather and paste the token it gives you.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-tg-chat"><?php esc_html_e( 'Channel or chat ID', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-tg-chat"
						class="regular-text"
						name="<?php echo esc_attr( $this->name( 'telegram[chat_id]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'telegram.chat_id' ) ); ?>"
						placeholder="@mychannel"
						autocomplete="off"
					/>
					<p class="description"><?php esc_html_e( 'Use @channelname for a public channel, or the numeric ID for a private one. The bot must be an administrator there.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Link preview', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox(
						'telegram[disable_preview]',
						Settings::get( 'telegram.disable_preview' ),
						__( 'Send messages without the link preview card', 'social-hub' )
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection', 'social-hub' ); ?></th>
				<td><?php $this->test_button( 'telegram' ); ?></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Share buttons tab.
	 *
	 * @return void
	 */
	private function render_buttons() {
		$selected = (array) Settings::get( 'buttons.networks', array() );
		$position = (string) Settings::get( 'buttons.position', 'after' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox( 'buttons[enabled]', Settings::get( 'buttons.enabled' ), __( 'Show share buttons for readers', 'social-hub' ) );
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Networks', 'social-hub' ); ?></th>
				<td>
					<fieldset class="social-hub-networks">
						<?php foreach ( Share_Buttons::networks() as $id => $network ) : ?>
							<label class="social-hub-network">
								<input
									type="checkbox"
									name="<?php echo esc_attr( $this->name( 'buttons[networks][]' ) ); ?>"
									value="<?php echo esc_attr( $id ); ?>"
									<?php checked( in_array( $id, $selected, true ) ); ?>
								/>
								<span class="social-hub-network__swatch" style="background:<?php echo esc_attr( $network['color'] ); ?>"></span>
								<?php echo esc_html( $network['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-buttons-position"><?php esc_html_e( 'Position', 'social-hub' ); ?></label>
				</th>
				<td>
					<select id="social-hub-buttons-position" name="<?php echo esc_attr( $this->name( 'buttons[position]' ) ); ?>">
						<option value="after" <?php selected( 'after', $position ); ?>><?php esc_html_e( 'After the content', 'social-hub' ); ?></option>
						<option value="before" <?php selected( 'before', $position ); ?>><?php esc_html_e( 'Before the content', 'social-hub' ); ?></option>
						<option value="both" <?php selected( 'both', $position ); ?>><?php esc_html_e( 'Before and after', 'social-hub' ); ?></option>
						<option value="manual" <?php selected( 'manual', $position ); ?>><?php esc_html_e( 'Only where I place them', 'social-hub' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Place them yourself with the shortcode', 'social-hub' ); ?>
						<code>[social_hub_share]</code>
						<?php esc_html_e( 'or in a template with', 'social-hub' ); ?>
						<code>&lt;?php social_hub_share_buttons(); ?&gt;</code>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show on', 'social-hub' ); ?></th>
				<td><?php $this->post_types( 'buttons[post_types]', (array) Settings::get( 'buttons.post_types', array() ) ); ?></td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-buttons-heading"><?php esc_html_e( 'Heading', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-buttons-heading"
						class="regular-text"
						name="<?php echo esc_attr( $this->name( 'buttons[heading]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'buttons.heading' ) ); ?>"
						placeholder="<?php echo esc_attr__( 'Share this post', 'social-hub' ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Appearance', 'social-hub' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $this->name( 'buttons[style]' ) ); ?>">
						<option value="filled" <?php selected( 'filled', Settings::get( 'buttons.style' ) ); ?>><?php esc_html_e( 'Filled', 'social-hub' ); ?></option>
						<option value="outline" <?php selected( 'outline', Settings::get( 'buttons.style' ) ); ?>><?php esc_html_e( 'Outline', 'social-hub' ); ?></option>
					</select>
					<p>
						<?php
						$this->checkbox( 'buttons[show_labels]', Settings::get( 'buttons.show_labels' ), __( 'Show network names next to the icons', 'social-hub' ) );
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Link previews tab.
	 *
	 * @return void
	 */
	private function render_preview() {
		$image_id  = (int) Settings::get( 'open_graph.default_image', 0 );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<?php if ( Open_Graph::seo_plugin_active() ) : ?>
			<div class="notice notice-info inline">
				<p><?php esc_html_e( 'An SEO plugin that already outputs Open Graph tags was detected. Social Hub stays out of its way unless you turn the option below off.', 'social-hub' ); ?></p>
			</div>
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Open Graph tags', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox( 'open_graph[enabled]', Settings::get( 'open_graph.enabled' ), __( 'Add Open Graph and Twitter card tags to the page head', 'social-hub' ) );
					echo '<p>';
					$this->checkbox( 'open_graph[respect_seo_plugins]', Settings::get( 'open_graph.respect_seo_plugins' ), __( 'Skip them when an SEO plugin already handles previews', 'social-hub' ) );
					echo '</p>';
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Fallback image', 'social-hub' ); ?></th>
				<td>
					<div class="social-hub-media" data-social-hub-media>
						<input
							type="hidden"
							name="<?php echo esc_attr( $this->name( 'open_graph[default_image]' ) ); ?>"
							value="<?php echo esc_attr( (string) $image_id ); ?>"
							data-social-hub-media-value
						/>
						<div class="social-hub-media__preview" data-social-hub-media-preview>
							<?php if ( $image_url ) : ?>
								<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
							<?php endif; ?>
						</div>
						<p>
							<button type="button" class="button" data-social-hub-media-select><?php esc_html_e( 'Choose image', 'social-hub' ); ?></button>
							<button type="button" class="button-link" data-social-hub-media-clear><?php esc_html_e( 'Remove', 'social-hub' ); ?></button>
						</p>
					</div>
					<p class="description"><?php esc_html_e( 'Used when a post has no featured image. 1200×630 pixels works well everywhere.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-fb-app"><?php esc_html_e( 'Facebook App ID', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-fb-app"
						class="regular-text"
						name="<?php echo esc_attr( $this->name( 'open_graph[fb_app_id]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'open_graph.fb_app_id' ) ); ?>"
					/>
					<p class="description"><?php esc_html_e( 'Optional. Adds the fb:app_id tag so your Page shows up in Meta insights.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-twitter-site"><?php esc_html_e( 'X / Twitter handle', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-twitter-site"
						class="regular-text"
						name="<?php echo esc_attr( $this->name( 'open_graph[twitter_site]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'open_graph.twitter_site' ) ); ?>"
						placeholder="mysite"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Card size', 'social-hub' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $this->name( 'open_graph[twitter_card]' ) ); ?>">
						<option value="summary_large_image" <?php selected( 'summary_large_image', Settings::get( 'open_graph.twitter_card' ) ); ?>>
							<?php esc_html_e( 'Large image', 'social-hub' ); ?>
						</option>
						<option value="summary" <?php selected( 'summary', Settings::get( 'open_graph.twitter_card' ) ); ?>>
							<?php esc_html_e( 'Small thumbnail', 'social-hub' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Activity log tab.
	 *
	 * @return void
	 */
	private function render_log() {
		$entries = Log::all();
		?>
		<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice. ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'The log was cleared.', 'social-hub' ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped social-hub-log">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'When', 'social-hub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Network', 'social-hub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Post', 'social-hub' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Result', 'social-hub' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $entries ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'Nothing has been shared yet.', 'social-hub' ); ?></td></tr>
				<?php endif; ?>

				<?php
				foreach ( $entries as $entry ) :
					$provider = Providers::get( $entry['network'] );
					$title    = get_the_title( (int) $entry['post_id'] );
					$edit     = get_edit_post_link( (int) $entry['post_id'] );
					?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ); ?></td>
						<td><?php echo esc_html( $provider ? $provider->label() : $entry['network'] ); ?></td>
						<td>
							<?php if ( $edit && $title ) : ?>
								<a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( $title ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $title ? $title : '—' ); ?>
							<?php endif; ?>
						</td>
						<td class="social-hub-log__result social-hub-log__result--<?php echo esc_attr( $entry['status'] ); ?>">
							<?php echo esc_html( $entry['message'] ); ?>
							<?php if ( ! empty( $entry['url'] ) ) : ?>
								<a href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'social-hub' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $entries ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="social-hub-log__clear">
				<?php wp_nonce_field( 'social_hub_clear_log' ); ?>
				<input type="hidden" name="action" value="social_hub_clear_log" />
				<?php submit_button( __( 'Clear log', 'social-hub' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Builds an input name inside the plugin option.
	 *
	 * @param string $key Key path such as "facebook[page_id]".
	 * @return string
	 */
	private function name( $key ) {
		return Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Prints a hidden input.
	 *
	 * @param string $key   Key inside the option.
	 * @param string $value Value.
	 * @return void
	 */
	private function hidden( $key, $value ) {
		printf(
			'<input type="hidden" name="%s" value="%s" />',
			esc_attr( $this->name( $key ) ),
			esc_attr( $value )
		);
	}

	/**
	 * Prints a labelled checkbox.
	 *
	 * @param string $key     Key inside the option.
	 * @param mixed  $checked Current value.
	 * @param string $label   Label text.
	 * @return void
	 */
	private function checkbox( $key, $checked, $label ) {
		printf(
			'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
			esc_attr( $this->name( $key ) ),
			checked( true, (bool) $checked, false ),
			esc_html( $label )
		);
	}

	/**
	 * Prints the post type checkboxes.
	 *
	 * @param string   $key      Key inside the option.
	 * @param string[] $selected Selected post types.
	 * @return void
	 */
	private function post_types( $key, array $selected ) {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		echo '<fieldset>';

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			printf(
				'<label class="social-hub-inline"><input type="checkbox" name="%1$s" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $this->name( $key . '[]' ) ),
				esc_attr( $post_type->name ),
				checked( in_array( $post_type->name, $selected, true ), true, false ),
				esc_html( $post_type->labels->name )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Prints a secret field that keeps the stored value when left blank.
	 *
	 * @param string $group Settings group, e.g. "facebook".
	 * @param string $key   Secret key.
	 * @param string $id    Input id.
	 * @return void
	 */
	private function secret( $group, $key, $id ) {
		$stored = (string) Settings::get( $group . '.' . $key );

		printf(
			'<input type="password" id="%1$s" class="regular-text" name="%2$s" value="" autocomplete="new-password" placeholder="%3$s" />',
			esc_attr( $id ),
			esc_attr( $this->name( $group . '[' . $key . ']' ) ),
			esc_attr(
				'' !== $stored
					? __( 'Saved — leave blank to keep it', 'social-hub' )
					: __( 'Paste the token here', 'social-hub' )
			)
		);

		if ( '' !== $stored ) {
			echo '<p>';
			$this->checkbox( $group . '[clear_' . $key . ']', false, __( 'Delete the stored token', 'social-hub' ) );
			echo '</p>';
		}
	}

	/**
	 * Prints a "test connection" button.
	 *
	 * @param string $network Network id.
	 * @return void
	 */
	private function test_button( $network ) {
		printf(
			'<button type="button" class="button" data-social-hub-test="%1$s">%2$s</button> <span class="social-hub-test-result" data-social-hub-test-result="%1$s"></span>',
			esc_attr( $network ),
			esc_html__( 'Test connection', 'social-hub' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Save your changes first — the test uses the stored credentials.', 'social-hub' )
		);
	}
}
