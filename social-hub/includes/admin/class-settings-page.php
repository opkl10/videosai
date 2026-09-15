<?php
/**
 * Renders the Social Hub settings screen.
 *
 * @package SocialHub
 */

namespace SocialHub\Admin;

use SocialHub\Advisor;
use SocialHub\Log;
use SocialHub\Open_Graph;
use SocialHub\Plugin;
use SocialHub\Providers;
use SocialHub\Settings;
use SocialHub\Share_Buttons;
use SocialHub\Timing;
use SocialHub\Tracking;

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
			} elseif ( 'tips' === $tab ) {
				$this->render_tips();
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
						case 'promote':
							$this->render_promote();
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
			'promote'  => __( 'Promotion', 'social-hub' ),
			'tips'     => __( 'Tips', 'social-hub' ),
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
						dir="ltr"
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
						dir="ltr"
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
						dir="ltr"
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
						dir="ltr"
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
						dir="ltr"
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
	 * Promotion tab: the site checklist, the sharing window and link tracking.
	 *
	 * @return void
	 */
	private function render_promote() {
		$checks    = Advisor::site_checks();
		$weekdays  = Timing::weekdays();
		$skip_days = array_map( 'intval', (array) Settings::get( 'timing.skip_days', array() ) );
		?>
		<h2><?php esc_html_e( 'How ready is this site?', 'social-hub' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %1$d: passed checks, %2$d: total checks. */
				esc_html__( '%1$d of %2$d checks passed.', 'social-hub' ),
				(int) Advisor::passed( $checks ),
				count( $checks )
			);
			?>
		</p>
		<?php $this->render_checklist( $checks ); ?>

		<h2><?php esc_html_e( 'Sharing window', 'social-hub' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Hold shares', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox(
						'timing[enabled]',
						Settings::get( 'timing.enabled' ),
						__( 'Only publish to the networks inside the hours below', 'social-hub' )
					);
					?>
					<p class="description"><?php esc_html_e( 'A post published outside the window waits for the next opening instead of going out to an empty feed.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Hours', 'social-hub' ); ?></th>
				<td>
					<label class="social-hub-inline">
						<?php esc_html_e( 'From', 'social-hub' ); ?>
						<input
							type="time"
							name="<?php echo esc_attr( $this->name( 'timing[start]' ) ); ?>"
							value="<?php echo esc_attr( (string) Settings::get( 'timing.start' ) ); ?>"
						/>
					</label>
					<label class="social-hub-inline">
						<?php esc_html_e( 'until', 'social-hub' ); ?>
						<input
							type="time"
							name="<?php echo esc_attr( $this->name( 'timing[end]' ) ); ?>"
							value="<?php echo esc_attr( (string) Settings::get( 'timing.end' ) ); ?>"
						/>
					</label>
					<p class="description">
						<?php
						printf(
							/* translators: %s: site timezone name. */
							esc_html__( 'Site time zone: %s.', 'social-hub' ),
							esc_html( wp_timezone_string() )
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Days to skip', 'social-hub' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $weekdays as $number => $name ) : ?>
							<label class="social-hub-inline">
								<input
									type="checkbox"
									name="<?php echo esc_attr( $this->name( 'timing[skip_days][]' ) ); ?>"
									value="<?php echo esc_attr( (string) $number ); ?>"
									<?php checked( in_array( (int) $number, $skip_days, true ) ); ?>
								/>
								<?php echo esc_html( $name ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Shares that fall on a skipped day move to the next day inside the window.', 'social-hub' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Link tracking', 'social-hub' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'UTM parameters', 'social-hub' ); ?></th>
				<td>
					<?php
					$this->checkbox( 'tracking[enabled]', Settings::get( 'tracking.enabled' ), __( 'Tag links published by Social Hub', 'social-hub' ) );
					echo '<p>';
					$this->checkbox( 'tracking[buttons]', Settings::get( 'tracking.buttons' ), __( 'Tag links shared by readers through the share buttons', 'social-hub' ) );
					echo '</p>';
					?>
					<p class="description"><?php esc_html_e( 'Without tags, analytics lumps every social visit together and you cannot tell which network is worth your time.', 'social-hub' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-utm-medium"><?php esc_html_e( 'utm_medium', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-utm-medium"
						class="regular-text"
						dir="ltr"
						name="<?php echo esc_attr( $this->name( 'tracking[medium]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'tracking.medium' ) ); ?>"
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="social-hub-utm-campaign"><?php esc_html_e( 'utm_campaign', 'social-hub' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="social-hub-utm-campaign"
						class="regular-text"
						dir="ltr"
						name="<?php echo esc_attr( $this->name( 'tracking[campaign]' ) ); ?>"
						value="<?php echo esc_attr( (string) Settings::get( 'tracking.campaign' ) ); ?>"
					/>
					<p class="description">
						<?php esc_html_e( 'Placeholders:', 'social-hub' ); ?>
						<code>{network}</code> <code>{slug}</code> <code>{year}</code> <code>{month}</code>
					</p>
					<p class="description" dir="ltr">
						<?php echo esc_html( Tracking::decorate( home_url( '/example-post/' ), 'facebook' ) ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Tips tab: the short version of what actually moves the numbers.
	 *
	 * @return void
	 */
	private function render_tips() {
		$sections = array(
			array(
				'title' => __( 'Before you publish', 'social-hub' ),
				'tips'  => array(
					__( 'Give every post a 1200×630 image. In a feed the image is the advertisement and the text is the footnote — a bare link takes a fraction of the space and gets a fraction of the clicks.', 'social-hub' ),
					__( 'Keep the headline under about 70 characters so a phone shows all of it instead of cutting it mid-sentence.', 'social-hub' ),
					__( 'Write the excerpt yourself. It becomes the description under the link, and the opening sentence of an article is rarely its best pitch.', 'social-hub' ),
					__( 'Do not paste the title twice. The share text should be the one sentence that makes somebody stop scrolling — the surprising number, the mistake you made, the thing they can use today.', 'social-hub' ),
					__( 'End with a question. Comments push a post to people who do not follow you, and the first hour of comments sets the reach for the rest of the day.', 'social-hub' ),
				),
			),
			array(
				'title' => __( 'Timing and frequency', 'social-hub' ),
				'tips'  => array(
					__( 'Publish when readers are awake. Weekday mornings around 08:00–10:00 and evenings around 19:00–22:00 work for most Hebrew-speaking audiences. The sharing window on the Promotion tab holds a 03:00 post until the morning.', 'social-hub' ),
					__( 'An Israeli week is not an American week. Friday afternoon and Saturday behave differently here, so check your own numbers before you copy a schedule from a blog post.', 'social-hub' ),
					__( 'Three good posts a week beat ten thin ones. Networks judge each post by its engagement, so a weak post quietly costs you reach on the next one.', 'social-hub' ),
					__( 'Reshare evergreen posts after two or three months with a new opening line. Almost nobody saw them the first time.', 'social-hub' ),
				),
			),
			array(
				'title' => __( 'Getting it in front of people', 'social-hub' ),
				'tips'  => array(
					__( 'Answer every comment in the first hour. It is the cheapest reach you will ever buy.', 'social-hub' ),
					__( 'Boost only what already works. Putting a small budget behind a post that is doing well organically beats guessing in advance which post deserves it.', 'social-hub' ),
					__( 'In groups and communities, contribute to the discussion and let the link follow. Dropping links is how accounts get muted.', 'social-hub' ),
					__( 'For Hebrew-speaking audiences, WhatsApp and Telegram often send more traffic than Facebook, because sharing there is one tap into a private conversation. Keep both buttons switched on.', 'social-hub' ),
					__( 'Cut one post into several formats: a quote card, a short video, a carousel. Same idea, different surfaces, more chances to be seen.', 'social-hub' ),
				),
			),
			array(
				'title' => __( 'Measuring', 'social-hub' ),
				'tips'  => array(
					__( 'Tag every link with UTM parameters and judge a network by the sessions it sends, not by the likes it collects.', 'social-hub' ),
					__( 'Watch clicks and saves rather than reach. Reach is what the network decided to give you; a click is what your audience decided to give you.', 'social-hub' ),
					__( 'Once a month, look at your three best posts and ask what they had in common. Then do that again on purpose.', 'social-hub' ),
				),
			),
		);
		?>
		<div class="social-hub-tips">
			<p class="description"><?php esc_html_e( 'The short version of what tends to work. The Promotion tab turns most of it into settings, and the box in the post editor checks each post against it before it goes out.', 'social-hub' ); ?></p>

			<?php foreach ( $sections as $section ) : ?>
				<h2><?php echo esc_html( $section['title'] ); ?></h2>
				<ul>
					<?php foreach ( $section['tips'] as $tip ) : ?>
						<li><?php echo esc_html( $tip ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Prints a list of advisor checks.
	 *
	 * @param array[] $checks Checks to print.
	 * @return void
	 */
	private function render_checklist( array $checks ) {
		echo '<ul class="social-hub-checklist">';

		foreach ( $checks as $check ) {
			printf(
				'<li class="social-hub-checklist__item social-hub-checklist__item--%1$s"><span class="social-hub-checklist__label">%2$s</span>%3$s</li>',
				esc_attr( $check['status'] ),
				esc_html( $check['label'] ),
				'' !== $check['hint'] ? '<span class="social-hub-checklist__hint">' . esc_html( $check['hint'] ) . '</span>' : ''
			);
		}

		echo '</ul>';
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
			'<input type="password" id="%1$s" class="regular-text" name="%2$s" value="" autocomplete="new-password" placeholder="%3$s" dir="ltr" />',
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
