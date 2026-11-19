<?php
/**
 * The [statista_chart] shortcode, for themes and editors outside Gutenberg.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the shortcode.
 *
 * Data can be given either with attributes:
 *
 *     [statista_chart type="bar" labels="A,B,C" series="Revenue: 10,20,30 | Cost: 5,8,9"]
 *
 * or as CSV between the opening and closing tags:
 *
 *     [statista_chart type="line" title="Visitors"]
 *     Month,2024,2025
 *     January,120,180
 *     [/statista_chart]
 */
class Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'statista_chart';

	/**
	 * Registers the shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Shortcode content, optionally CSV data.
	 * @return string Chart HTML.
	 */
	public static function render( $atts, $content = null ) {
		$atts = shortcode_atts(
			array(
				'type'         => 'bar',
				'title'        => '',
				'show_title'   => 'true',
				'source'       => '',
				'show_source'  => 'true',
				'show_chart'   => 'true',
				'show_cards'   => 'false',
				'cards_bar'    => 'true',
				'cards'        => '',
				'show_legend'  => 'true',
				'show_values'  => 'false',
				'show_grid'    => 'true',
				'show_table'   => 'false',
				'stacked'      => 'false',
				'smooth'       => 'true',
				'palette'      => 'statista',
				'theme'        => 'light',
				'direction'    => 'auto',
				'height'       => '360',
				'decimals'     => '0',
				'prefix'       => '',
				'suffix'       => '',
				'labels'       => '',
				'series'       => '',
				'data'         => '',
				'value'        => '',
				'label'        => '',
				'delta'        => '',
				'trend'        => 'up',
				'caption'      => '',
			),
			$atts,
			self::TAG
		);

		$raw = array(
			'type'        => $atts['type'],
			'title'       => $atts['title'],
			'showTitle'   => $atts['show_title'],
			'source'      => $atts['source'],
			'showSource'  => $atts['show_source'],
			'showChart'   => $atts['show_chart'],
			'showCards'   => $atts['show_cards'],
			'cardsBar'    => $atts['cards_bar'],
			'cards'       => self::parse_cards( $atts['cards'] ),
			'showLegend'  => $atts['show_legend'],
			'showValues'  => $atts['show_values'],
			'showGrid'    => $atts['show_grid'],
			'showTable'   => $atts['show_table'],
			'stacked'     => $atts['stacked'],
			'smooth'      => $atts['smooth'],
			'palette'     => $atts['palette'],
			'theme'       => $atts['theme'],
			'direction'   => $atts['direction'],
			'height'      => $atts['height'],
			'decimals'    => $atts['decimals'],
			'prefix'      => $atts['prefix'],
			'suffix'      => $atts['suffix'],
			'statValue'   => $atts['value'],
			'statLabel'   => $atts['label'],
			'statDelta'   => $atts['delta'],
			'statTrend'   => $atts['trend'],
			'statCaption' => $atts['caption'],
		);

		$csv = trim( wp_strip_all_tags( (string) $content ) );

		if ( '' !== $csv ) {
			$parsed        = Config::parse_data( $csv );
			$raw['labels'] = $parsed['labels'];
			$raw['series'] = $parsed['series'];
		} else {
			$raw['labels'] = self::split_list( $atts['labels'] );
			$raw['series'] = self::parse_series( $atts['series'], $atts['data'] );
		}

		$config = Config::sanitize( $raw );

		self::enqueue_assets( $config );

		return Renderer::render( $config );
	}

	/**
	 * Enqueues the same assets the block uses, since the block itself is absent here.
	 *
	 * @param array $config Sanitized configuration.
	 * @return void
	 */
	private static function enqueue_assets( $config ) {
		wp_enqueue_style( generate_block_asset_handle( Block::NAME, 'style' ) );

		if ( $config['showCards'] && ! empty( $config['cards'] ) ) {
			wp_enqueue_style( generate_block_asset_handle( Stats::NAME, 'style' ) );
		}

		if ( 'stat' !== $config['type'] ) {
			wp_enqueue_script( generate_block_asset_handle( Block::NAME, 'viewScript' ) );
		}
	}

	/**
	 * Parses the `cards` attribute.
	 *
	 * One card per semicolon, its parts separated by pipes:
	 * `📈|Conversion rate|27.4%|+3.2%|up ; 🛒|Abandonment|68%|-1.5%|down`.
	 *
	 * @param string $value Attribute value.
	 * @return array<int, array<string, string>> Cards.
	 */
	private static function parse_cards( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return array();
		}

		$cards = array();

		foreach ( explode( ';', $value ) as $chunk ) {
			if ( '' === trim( $chunk ) ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $chunk ) );

			$cards[] = array(
				'icon'  => isset( $parts[0] ) ? $parts[0] : '',
				'label' => isset( $parts[1] ) ? $parts[1] : '',
				'value' => isset( $parts[2] ) ? $parts[2] : '',
				'delta' => isset( $parts[3] ) ? $parts[3] : '',
				'trend' => isset( $parts[4] ) ? $parts[4] : 'none',
				'note'  => isset( $parts[5] ) ? $parts[5] : '',
			);
		}

		return $cards;
	}

	/**
	 * Splits a comma separated attribute into trimmed items.
	 *
	 * @param string $value Attribute value.
	 * @return string[] Items.
	 */
	private static function split_list( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return array();
		}

		return array_map( 'trim', explode( ',', (string) $value ) );
	}

	/**
	 * Parses the `series` (and legacy `data`) attributes.
	 *
	 * @param string $series Series attribute, e.g. "Revenue: 1,2,3 | Cost: 4,5,6".
	 * @param string $data   Single series shorthand, e.g. "1,2,3".
	 * @return array<int, array<string, mixed>> Series.
	 */
	private static function parse_series( $series, $data ) {
		$series = trim( (string) $series );

		if ( '' === $series ) {
			$data = trim( (string) $data );

			return '' === $data
				? array()
				: array(
					array(
						'label' => '',
						'data'  => self::split_list( $data ),
					),
				);
		}

		$sets = array();
		foreach ( explode( '|', $series ) as $chunk ) {
			$chunk = trim( $chunk );
			if ( '' === $chunk ) {
				continue;
			}

			$label  = '';
			$values = $chunk;

			if ( false !== strpos( $chunk, ':' ) ) {
				list( $label, $values ) = explode( ':', $chunk, 2 );
			}

			$sets[] = array(
				'label' => trim( $label ),
				'data'  => self::split_list( $values ),
			);
		}

		return $sets;
	}
}
