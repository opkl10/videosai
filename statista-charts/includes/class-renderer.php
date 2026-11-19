<?php
/**
 * Front-end markup for a chart.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the server side markup that the view script enhances with Chart.js.
 */
class Renderer {

	/**
	 * Counter used to build unique element ids.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Builds the chart markup.
	 *
	 * @param array  $config        Sanitized configuration, see Config::sanitize().
	 * @param string $wrapper_attrs Extra attributes for the wrapper element (already escaped).
	 * @return string Chart HTML.
	 */
	public static function render( $config, $wrapper_attrs = '' ) {
		++self::$instance;
		$id = 'statista-chart-' . self::$instance . '-' . wp_rand( 1000, 9999 );

		$classes = array(
			'statista-chart',
			'sc-type-' . $config['type'],
			'sc-theme-' . $config['theme'],
			'sc-palette-' . $config['palette'],
		);

		if ( ! $config['showChart'] ) {
			$classes[] = 'sc-chart-hidden';
		}

		$direction = 'auto' === $config['direction'] ? ( is_rtl() ? 'rtl' : 'ltr' ) : $config['direction'];
		$classes[] = 'sc-dir-' . $direction;

		$payload = $config;
		$payload['resolvedDirection'] = $direction;

		$has_data = ! empty( $config['labels'] ) && ! empty( $config['series'] );

		ob_start();
		?>
		<figure
			id="<?php echo esc_attr( $id ); ?>"
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			dir="<?php echo esc_attr( $direction ); ?>"
			style="--sc-accent: <?php echo esc_attr( $config['colors'][0] ); ?>; --sc-accent-2: <?php echo esc_attr( $config['colors'][1] ); ?>;"
			data-statista-chart="<?php echo esc_attr( wp_json_encode( $payload ) ); ?>"
			<?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by get_block_wrapper_attributes(). ?>
		>
			<?php if ( $config['showTitle'] && '' !== $config['title'] ) : ?>
				<figcaption class="sc-header">
					<h3 class="sc-title"><?php echo esc_html( $config['title'] ); ?></h3>
				</figcaption>
			<?php endif; ?>

			<?php if ( $config['showCards'] && ! empty( $config['cards'] ) ) : ?>
				<?php echo self::cards( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Stats::markup(). ?>
			<?php endif; ?>

			<?php if ( $config['showChart'] ) : ?>
				<?php if ( 'stat' === $config['type'] ) : ?>
					<?php echo self::stat_card( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in stat_card(). ?>
				<?php elseif ( $has_data ) : ?>
					<div class="sc-canvas-wrap" style="height: <?php echo esc_attr( (string) $config['height'] ); ?>px;">
						<canvas
							class="sc-canvas"
							role="img"
							aria-label="<?php echo esc_attr( self::chart_aria_label( $config ) ); ?>"
						></canvas>
						<div class="sc-loading" aria-hidden="true"><span class="sc-spinner"></span></div>
					</div>
				<?php elseif ( ! $config['showCards'] || empty( $config['cards'] ) ) : ?>
					<p class="sc-empty"><?php esc_html_e( 'No data to display yet.', 'statista-charts' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $has_data && 'stat' !== $config['type'] ) : ?>
				<?php echo self::data_table( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in data_table(). ?>
			<?php endif; ?>

			<?php if ( $config['showSource'] && '' !== $config['source'] ) : ?>
				<p class="sc-source">
					<span class="sc-source-label"><?php esc_html_e( 'Source:', 'statista-charts' ); ?></span>
					<span class="sc-source-text"><?php echo esc_html( $config['source'] ); ?></span>
				</p>
			<?php endif; ?>
		</figure>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders the stat cards that sit above the chart, in the chart's own colours.
	 *
	 * @param array $config Sanitized configuration.
	 * @return string Cards HTML.
	 */
	private static function cards( $config ) {
		return Stats::markup(
			array(
				'cards'     => $config['cards'],
				'columns'   => 0,
				'showBar'   => $config['cardsBar'],
				'palette'   => $config['palette'],
				'colors'    => $config['colors'],
				'theme'     => $config['theme'],
				'direction' => $config['direction'],
			)
		);
	}

	/**
	 * Renders the single number card. It needs no JavaScript at all.
	 *
	 * @param array $config Sanitized configuration.
	 * @return string Stat card HTML.
	 */
	private static function stat_card( $config ) {
		$value = '' !== $config['statValue'] ? $config['statValue'] : self::first_value( $config );
		$trend = $config['statTrend'];
		$arrow = array(
			'up'   => '▲',
			'down' => '▼',
			'flat' => '■',
		);

		ob_start();
		?>
		<div class="sc-stat">
			<div class="sc-stat-value" dir="ltr">
				<?php if ( '' !== $config['prefix'] ) : ?>
					<span class="sc-stat-affix"><?php echo esc_html( $config['prefix'] ); ?></span>
				<?php endif; ?>
				<span class="sc-stat-number"><?php echo esc_html( $value ); ?></span>
				<?php if ( '' !== $config['suffix'] ) : ?>
					<span class="sc-stat-affix"><?php echo esc_html( $config['suffix'] ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $config['statLabel'] ) : ?>
				<div class="sc-stat-label"><?php echo esc_html( $config['statLabel'] ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $config['statDelta'] ) : ?>
				<div class="sc-stat-delta sc-trend-<?php echo esc_attr( $trend ); ?>">
					<span class="sc-stat-arrow" aria-hidden="true"><?php echo esc_html( $arrow[ $trend ] ); ?></span>
					<span><?php echo esc_html( $config['statDelta'] ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( '' !== $config['statCaption'] ) : ?>
				<p class="sc-stat-caption"><?php echo esc_html( $config['statCaption'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders the data behind the chart as a table.
	 *
	 * The table stays in the markup even when it is not shown, so screen readers
	 * and search engines can always read the numbers.
	 *
	 * @param array $config Sanitized configuration.
	 * @return string Table HTML.
	 */
	private static function data_table( $config ) {
		$classes = 'sc-data-table' . ( $config['showTable'] ? '' : ' sc-sr-only' );

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<table>
				<caption>
					<?php
					echo esc_html(
						'' !== $config['title']
							? $config['title']
							: __( 'Chart data', 'statista-charts' )
					);
					?>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Category', 'statista-charts' ); ?></th>
						<?php foreach ( $config['series'] as $index => $series ) : ?>
							<th scope="col">
								<?php
								echo esc_html(
									'' !== $series['label']
										/* translators: %d: series number. */
										? $series['label'] : sprintf( __( 'Series %d', 'statista-charts' ), $index + 1 )
								);
								?>
							</th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $config['labels'] as $row => $label ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $label ); ?></th>
							<?php foreach ( $config['series'] as $series ) : ?>
								<td>
									<?php
									$value = isset( $series['data'][ $row ] ) ? $series['data'][ $row ] : null;
									echo esc_html( null === $value ? '—' : self::format_number( $value, $config ) );
									?>
								</td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Formats a number the way the chart will format it.
	 *
	 * @param float $value  Value.
	 * @param array $config Sanitized configuration.
	 * @return string Formatted number.
	 */
	private static function format_number( $value, $config ) {
		return $config['prefix'] . number_format_i18n( (float) $value, (int) $config['decimals'] ) . $config['suffix'];
	}

	/**
	 * First numeric value of the first series, used as the stat card fallback.
	 *
	 * @param array $config Sanitized configuration.
	 * @return string Value or an em dash.
	 */
	private static function first_value( $config ) {
		if ( empty( $config['series'][0]['data'] ) ) {
			return '—';
		}

		foreach ( $config['series'][0]['data'] as $value ) {
			if ( null !== $value ) {
				return number_format_i18n( (float) $value, (int) $config['decimals'] );
			}
		}

		return '—';
	}

	/**
	 * Accessible description of the canvas.
	 *
	 * @param array $config Sanitized configuration.
	 * @return string Label.
	 */
	private static function chart_aria_label( $config ) {
		$types = array(
			'bar'      => __( 'Bar chart', 'statista-charts' ),
			'line'     => __( 'Line chart', 'statista-charts' ),
			'area'     => __( 'Area chart', 'statista-charts' ),
			'pie'      => __( 'Pie chart', 'statista-charts' ),
			'doughnut' => __( 'Doughnut chart', 'statista-charts' ),
			'stat'     => __( 'Statistic', 'statista-charts' ),
		);

		$type = isset( $types[ $config['type'] ] ) ? $types[ $config['type'] ] : $types['bar'];

		if ( '' === $config['title'] ) {
			return $type;
		}

		/* translators: 1: chart type, 2: chart title. */
		return sprintf( __( '%1$s: %2$s', 'statista-charts' ), $type, $config['title'] );
	}
}
