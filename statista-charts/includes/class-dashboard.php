<?php
/**
 * The dashboard block: a banner, KPI cards, a grid of charts and a side panel.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the "Statista Dashboard" wrapper block.
 */
class Dashboard {

	/**
	 * Block name.
	 */
	const NAME = 'statista/dashboard';

	/**
	 * Registers the block.
	 *
	 * @return void
	 */
	public static function init() {
		$metadata = STATISTA_CHARTS_PATH . 'build/dashboard/block.json';

		if ( ! file_exists( $metadata ) ) {
			return;
		}

		register_block_type(
			$metadata,
			array(
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);

		wp_set_script_translations(
			generate_block_asset_handle( self::NAME, 'editorScript' ),
			'statista-charts',
			STATISTA_CHARTS_PATH . 'languages'
		);
	}

	/**
	 * Sanitizes the block attributes.
	 *
	 * @param array $raw Raw attributes.
	 * @return array<string, mixed> Clean attributes.
	 */
	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();

		$clean = array(
			'brandName'       => '',
			'logoUrl'         => '',
			'logoSize'        => 22,
			'title'           => '',
			'titleAccent'     => '',
			'meta'            => '',
			'updated'         => '',
			'bannerImage'     => '',
			'artworkUrl'      => '',
			'palette'         => 'statista',
			'theme'           => 'light',
			'direction'       => 'auto',
			'kpis'            => array(),
			'showPanel'       => true,
			'showPanelButton' => true,
			'panelImage'      => '',
			'panelKicker'     => '',
			'panelTitle'      => '',
			'panelText'       => '',
			'panelButtonText' => '',
			'panelButtonUrl'  => '',
		);

		foreach ( array( 'brandName', 'title', 'titleAccent', 'meta', 'updated', 'panelKicker', 'panelTitle', 'panelButtonText' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}

		if ( isset( $raw['panelText'] ) ) {
			$clean['panelText'] = sanitize_textarea_field( (string) $raw['panelText'] );
		}

		foreach ( array( 'logoUrl', 'bannerImage', 'artworkUrl', 'panelImage', 'panelButtonUrl' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$clean[ $key ] = esc_url_raw( (string) $raw[ $key ] );
			}
		}

		$palettes         = Config::palettes();
		$palette          = isset( $raw['palette'] ) ? sanitize_key( (string) $raw['palette'] ) : '';
		$clean['palette'] = isset( $palettes[ $palette ] ) ? $palette : $clean['palette'];
		$clean['colors']  = $palettes[ $clean['palette'] ]['colors'];

		$theme          = isset( $raw['theme'] ) ? sanitize_key( (string) $raw['theme'] ) : '';
		$clean['theme'] = in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ? $theme : $clean['theme'];

		$direction          = isset( $raw['direction'] ) ? sanitize_key( (string) $raw['direction'] ) : '';
		$clean['direction'] = in_array( $direction, array( 'auto', 'rtl', 'ltr' ), true ) ? $direction : $clean['direction'];

		if ( isset( $raw['showPanel'] ) ) {
			$clean['showPanel'] = Config::to_bool( $raw['showPanel'] );
		}

		if ( isset( $raw['showPanelButton'] ) ) {
			$clean['showPanelButton'] = Config::to_bool( $raw['showPanelButton'] );
		}

		if ( isset( $raw['logoSize'] ) ) {
			$clean['logoSize'] = min( 160, max( 14, absint( $raw['logoSize'] ) ) );
		}

		if ( isset( $raw['kpis'] ) && is_array( $raw['kpis'] ) ) {
			foreach ( $raw['kpis'] as $kpi ) {
				if ( ! is_array( $kpi ) ) {
					continue;
				}

				$clean['kpis'][] = array(
					'icon'   => isset( $kpi['icon'] ) ? sanitize_text_field( (string) $kpi['icon'] ) : '',
					'label'  => isset( $kpi['label'] ) ? sanitize_text_field( (string) $kpi['label'] ) : '',
					'value'  => isset( $kpi['value'] ) ? sanitize_text_field( (string) $kpi['value'] ) : '',
					'icon2'  => isset( $kpi['icon2'] ) ? sanitize_text_field( (string) $kpi['icon2'] ) : '',
					'value2' => isset( $kpi['value2'] ) ? sanitize_text_field( (string) $kpi['value2'] ) : '',
				);
			}
		}

		return $clean;
	}

	/**
	 * Renders the dashboard.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Rendered inner blocks, i.e. the charts.
	 * @return string Dashboard HTML.
	 */
	public static function render( $attributes, $content = '' ) {
		$data = self::sanitize( $attributes );

		$direction = 'auto' === $data['direction'] ? ( is_rtl() ? 'rtl' : 'ltr' ) : $data['direction'];
		$classes   = array(
			'statista-dashboard',
			'sc-theme-' . $data['theme'],
			'sc-palette-' . $data['palette'],
			'sc-dir-' . $direction,
		);

		$panel_button = $data['showPanelButton'] ? $data['panelButtonText'] : '';

		if ( ! $data['showPanel'] || '' === $data['panelTitle'] . $data['panelText'] . $panel_button ) {
			$classes[] = 'sd-no-panel';
		}

		$style = sprintf(
			'--sc-accent: %1$s; --sc-accent-2: %2$s; --sd-banner: %3$s; --sd-logo-size: %4$dpx;',
			$data['colors'][0],
			$data['colors'][1],
			self::banner_background( $data ),
			$data['logoSize']
		);

		ob_start();
		?>
		<section
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			dir="<?php echo esc_attr( $direction ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
		>
			<?php echo self::banner( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in banner(). ?>

			<div class="sd-body">
				<div class="sd-main">
					<?php echo self::kpis( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in kpis(). ?>

					<?php if ( '' !== trim( (string) $content ) ) : ?>
						<div class="sd-grid"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks, already rendered by core. ?></div>
					<?php endif; ?>
				</div>

				<?php echo self::panel( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in panel(). ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Builds the banner background: the artwork when there is one, over a
	 * gradient taken from the palette.
	 *
	 * @param array $data Clean attributes.
	 * @return string CSS background-image value.
	 */
	private static function banner_background( $data ) {
		$gradient = sprintf(
			'linear-gradient(115deg, %1$s 0%%, %2$s 55%%, %3$s 100%%)',
			$data['colors'][0],
			$data['colors'][4],
			$data['colors'][1]
		);

		if ( '' === $data['bannerImage'] ) {
			return $gradient;
		}

		return sprintf(
			'linear-gradient(90deg, %1$s 0%%, %2$s 45%%, %3$s 85%%), url(%4$s)',
			self::rgba( $data['colors'][0], 0.97 ),
			self::rgba( $data['colors'][0], 0.72 ),
			self::rgba( $data['colors'][0], 0 ),
			esc_url( $data['bannerImage'] )
		);
	}

	/**
	 * Converts a hex color to an rgba() string.
	 *
	 * @param string $hex     Hex color.
	 * @param float  $opacity Opacity between 0 and 1.
	 * @return string rgba() color.
	 */
	private static function rgba( $hex, $opacity ) {
		$hex = ltrim( (string) $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
			return 'rgba(31, 111, 235, ' . $opacity . ')';
		}

		return sprintf(
			'rgba(%d, %d, %d, %s)',
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
			$opacity
		);
	}

	/**
	 * Renders the banner.
	 *
	 * @param array $data Clean attributes.
	 * @return string Banner HTML.
	 */
	private static function banner( $data ) {
		ob_start();
		?>
		<header class="sd-banner">
			<div class="sd-banner__text">
				<?php if ( '' !== $data['logoUrl'] || '' !== $data['brandName'] ) : ?>
					<p class="sd-brand">
						<?php if ( '' !== $data['logoUrl'] ) : ?>
							<img class="sd-brand__logo" src="<?php echo esc_url( $data['logoUrl'] ); ?>" alt="" />
						<?php endif; ?>
						<?php if ( '' !== $data['brandName'] ) : ?>
							<span class="sd-brand__name"><?php echo esc_html( $data['brandName'] ); ?></span>
						<?php endif; ?>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $data['title'] || '' !== $data['titleAccent'] ) : ?>
					<h2 class="sd-title">
						<span class="sd-title__main"><?php echo esc_html( $data['title'] ); ?></span>
						<?php if ( '' !== $data['titleAccent'] ) : ?>
							<span class="sd-title__accent"><?php echo esc_html( $data['titleAccent'] ); ?></span>
						<?php endif; ?>
					</h2>
				<?php endif; ?>

				<?php if ( '' !== $data['meta'] || '' !== $data['updated'] ) : ?>
					<p class="sd-meta">
						<?php if ( '' !== $data['meta'] ) : ?>
							<span class="sd-meta__item"><?php echo esc_html( $data['meta'] ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $data['updated'] ) : ?>
							<span class="sd-meta__item sd-meta__updated">
								<?php
								printf(
									/* translators: %s: date or time the data was last refreshed. */
									esc_html__( 'Last updated %s', 'statista-charts' ),
									esc_html( $data['updated'] )
								);
								?>
							</span>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( '' !== $data['artworkUrl'] ) : ?>
				<div class="sd-artwork">
					<img src="<?php echo esc_url( $data['artworkUrl'] ); ?>" alt="" />
				</div>
			<?php endif; ?>
		</header>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders the KPI cards.
	 *
	 * @param array $data Clean attributes.
	 * @return string Cards HTML.
	 */
	private static function kpis( $data ) {
		if ( empty( $data['kpis'] ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="sd-kpis">
			<?php foreach ( $data['kpis'] as $kpi ) : ?>
				<?php $split = '' !== $kpi['value2']; ?>
				<div class="sd-kpi<?php echo $split ? ' sd-kpi--split' : ''; ?>">
					<?php if ( '' !== $kpi['icon'] && ! $split ) : ?>
						<span class="sd-kpi__icon" aria-hidden="true"><?php echo esc_html( $kpi['icon'] ); ?></span>
					<?php endif; ?>

					<?php if ( '' !== $kpi['label'] ) : ?>
						<span class="sd-kpi__label"><?php echo esc_html( $kpi['label'] ); ?></span>
					<?php endif; ?>

					<div class="sd-kpi__values">
						<span class="sd-kpi__value">
							<?php if ( $split && '' !== $kpi['icon'] ) : ?>
								<span class="sd-kpi__badge" aria-hidden="true"><?php echo esc_html( $kpi['icon'] ); ?></span>
							<?php endif; ?>
							<span class="sd-kpi__number" dir="ltr"><?php echo esc_html( $kpi['value'] ); ?></span>
						</span>

						<?php if ( $split ) : ?>
							<span class="sd-kpi__value">
								<?php if ( '' !== $kpi['icon2'] ) : ?>
									<span class="sd-kpi__badge" aria-hidden="true"><?php echo esc_html( $kpi['icon2'] ); ?></span>
								<?php endif; ?>
								<span class="sd-kpi__number" dir="ltr"><?php echo esc_html( $kpi['value2'] ); ?></span>
							</span>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders the side panel.
	 *
	 * @param array $data Clean attributes.
	 * @return string Panel HTML.
	 */
	private static function panel( $data ) {
		$button      = $data['showPanelButton'] ? $data['panelButtonText'] : '';
		$has_content = '' !== $data['panelTitle'] || '' !== $data['panelText'] || '' !== $button;

		if ( ! $data['showPanel'] || ! $has_content ) {
			return '';
		}

		ob_start();
		?>
		<aside class="sd-panel">
			<?php if ( '' !== $data['panelImage'] ) : ?>
				<img class="sd-panel__image" src="<?php echo esc_url( $data['panelImage'] ); ?>" alt="" />
			<?php endif; ?>

			<?php if ( '' !== $data['panelKicker'] ) : ?>
				<p class="sd-panel__kicker"><?php echo esc_html( $data['panelKicker'] ); ?></p>
			<?php endif; ?>

			<?php if ( '' !== $data['panelTitle'] ) : ?>
				<h3 class="sd-panel__title"><?php echo esc_html( $data['panelTitle'] ); ?></h3>
			<?php endif; ?>

			<?php if ( '' !== $data['panelText'] ) : ?>
				<p class="sd-panel__text"><?php echo esc_html( $data['panelText'] ); ?></p>
			<?php endif; ?>

			<?php if ( $data['showPanelButton'] && '' !== $data['panelButtonText'] ) : ?>
				<a
					class="sd-panel__button"
					href="<?php echo esc_url( '' !== $data['panelButtonUrl'] ? $data['panelButtonUrl'] : '#' ); ?>"
					<?php echo '' === $data['panelButtonUrl'] ? 'aria-disabled="true"' : ''; ?>
				>
					<?php echo esc_html( $data['panelButtonText'] ); ?>
				</a>
			<?php endif; ?>
		</aside>
		<?php

		return (string) ob_get_clean();
	}
}
