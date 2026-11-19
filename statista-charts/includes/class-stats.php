<?php
/**
 * The stat cards block: a row of numbers, percentages and short labels.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the "Statista Stats" block.
 */
class Stats {

	/**
	 * Block name.
	 */
	const NAME = 'statista/stats';

	/**
	 * Registers the block.
	 *
	 * @return void
	 */
	public static function init() {
		$metadata = STATISTA_CHARTS_PATH . 'build/stats/block.json';

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
			'cards'     => array(),
			'columns'   => 0,
			'showBar'   => true,
			'palette'   => 'statista',
			'theme'     => 'light',
			'direction' => 'auto',
		);

		$palettes         = Config::palettes();
		$palette          = isset( $raw['palette'] ) ? sanitize_key( (string) $raw['palette'] ) : '';
		$clean['palette'] = isset( $palettes[ $palette ] ) ? $palette : $clean['palette'];
		$clean['colors']  = $palettes[ $clean['palette'] ]['colors'];

		$theme          = isset( $raw['theme'] ) ? sanitize_key( (string) $raw['theme'] ) : '';
		$clean['theme'] = in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ? $theme : $clean['theme'];

		$direction          = isset( $raw['direction'] ) ? sanitize_key( (string) $raw['direction'] ) : '';
		$clean['direction'] = in_array( $direction, array( 'auto', 'rtl', 'ltr' ), true ) ? $direction : $clean['direction'];

		if ( isset( $raw['columns'] ) ) {
			$columns           = absint( $raw['columns'] );
			$clean['columns']  = in_array( $columns, array( 0, 2, 3, 4 ), true ) ? $columns : 0;
		}

		if ( isset( $raw['showBar'] ) ) {
			$clean['showBar'] = Config::to_bool( $raw['showBar'] );
		}

		$clean['cards'] = self::sanitize_cards( isset( $raw['cards'] ) ? $raw['cards'] : array() );

		return $clean;
	}

	/**
	 * Sanitizes a list of cards, wherever it comes from.
	 *
	 * @param mixed $raw Raw cards.
	 * @return array<int, array<string, string>> Clean cards.
	 */
	public static function sanitize_cards( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();

		foreach ( $raw as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}

			$trend = isset( $card['trend'] ) ? sanitize_key( (string) $card['trend'] ) : 'none';

			$clean[] = array(
				'icon'  => isset( $card['icon'] ) ? sanitize_text_field( (string) $card['icon'] ) : '',
				'label' => isset( $card['label'] ) ? sanitize_text_field( (string) $card['label'] ) : '',
				'value' => isset( $card['value'] ) ? sanitize_text_field( (string) $card['value'] ) : '',
				'delta' => isset( $card['delta'] ) ? sanitize_text_field( (string) $card['delta'] ) : '',
				'note'  => isset( $card['note'] ) ? sanitize_text_field( (string) $card['note'] ) : '',
				'trend' => in_array( $trend, array( 'up', 'down', 'none' ), true ) ? $trend : 'none',
			);
		}

		return $clean;
	}

	/**
	 * Renders the cards.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Cards HTML.
	 */
	public static function render( $attributes ) {
		$data = self::sanitize( $attributes );

		if ( empty( $data['cards'] ) ) {
			return '';
		}

		$wrapper = get_block_wrapper_attributes();

		return self::markup( $data, $wrapper );
	}

	/**
	 * Builds the markup, shared by the block and the Elementor widget.
	 *
	 * @param array  $data    Sanitized attributes.
	 * @param string $wrapper Extra attributes for the wrapper element.
	 * @return string Cards HTML.
	 */
	public static function markup( $data, $wrapper = '' ) {
		$direction = 'auto' === $data['direction'] ? ( is_rtl() ? 'rtl' : 'ltr' ) : $data['direction'];
		$classes   = array(
			'statista-stats',
			'sc-theme-' . $data['theme'],
			'sc-palette-' . $data['palette'],
			'sc-dir-' . $direction,
			$data['columns'] > 0 ? 'ss-cols-' . $data['columns'] : 'ss-cols-auto',
		);

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			dir="<?php echo esc_attr( $direction ); ?>"
			style="--sc-accent: <?php echo esc_attr( $data['colors'][0] ); ?>;"
			<?php echo $wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
		>
			<?php foreach ( $data['cards'] as $index => $card ) : ?>
				<?php
				$color   = $data['colors'][ $index % count( $data['colors'] ) ];
				$percent = self::percentage( $card['value'] );
				?>
				<div class="ss-card" style="--ss-color: <?php echo esc_attr( $color ); ?>;">
					<?php if ( '' !== $card['icon'] ) : ?>
						<span class="ss-card__icon" aria-hidden="true"><?php echo esc_html( $card['icon'] ); ?></span>
					<?php endif; ?>

					<?php if ( '' !== $card['label'] ) : ?>
						<span class="ss-card__label"><?php echo esc_html( $card['label'] ); ?></span>
					<?php endif; ?>

					<span class="ss-card__value" dir="ltr"><?php echo esc_html( $card['value'] ); ?></span>

					<?php if ( '' !== $card['delta'] ) : ?>
						<span class="ss-card__delta ss-trend-<?php echo esc_attr( $card['trend'] ); ?>">
							<span class="ss-card__arrow" aria-hidden="true">
								<?php
								if ( 'up' === $card['trend'] ) {
									echo '▲';
								} elseif ( 'down' === $card['trend'] ) {
									echo '▼';
								}
								?>
							</span>
							<span dir="ltr"><?php echo esc_html( $card['delta'] ); ?></span>
						</span>
					<?php endif; ?>

					<?php if ( $data['showBar'] && null !== $percent ) : ?>
						<span class="ss-card__bar">
							<span class="ss-card__bar-fill" style="width: <?php echo esc_attr( (string) $percent ); ?>%;"></span>
						</span>
					<?php endif; ?>

					<?php if ( '' !== $card['note'] ) : ?>
						<span class="ss-card__note"><?php echo esc_html( $card['note'] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Reads a card value as a percentage, so the bar can be drawn.
	 *
	 * Only values written as a percentage get a bar; "17.07M" does not.
	 *
	 * @param string $value Card value.
	 * @return float|null Percentage between 0 and 100, or null.
	 */
	private static function percentage( $value ) {
		if ( false === strpos( (string) $value, '%' ) ) {
			return null;
		}

		$number = Config::to_number( $value );

		if ( null === $number ) {
			return null;
		}

		return max( 0, min( 100, $number ) );
	}
}
