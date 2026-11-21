<?php
/**
 * Chart configuration: defaults, palettes and sanitization.
 *
 * @package StatistaCharts
 */

namespace StatistaCharts;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes and sanitizes every chart configuration before it is rendered.
 */
class Config {

	/**
	 * Chart types supported by the renderer.
	 */
	const TYPES = array( 'bar', 'line', 'area', 'pie', 'doughnut', 'stat' );

	/**
	 * Color palettes. Single source of truth, shared with the JavaScript side.
	 *
	 * @return array<string, array<string, mixed>> Palette slug => label and colors.
	 */
	public static function palettes() {
		$palettes = array(
			'statista' => array(
				'label'  => __( 'Statista', 'statista-charts' ),
				'colors' => array( '#1f6feb', '#00b3a4', '#ffb020', '#ef476f', '#7c5cff', '#2bb3ff', '#5dd39e', '#f78c6b' ),
			),
			'ocean'    => array(
				'label'  => __( 'Ocean', 'statista-charts' ),
				'colors' => array( '#0ea5e9', '#2563eb', '#14b8a6', '#6366f1', '#22d3ee', '#3b82f6', '#0d9488', '#818cf8' ),
			),
			'sunset'   => array(
				'label'  => __( 'Sunset', 'statista-charts' ),
				'colors' => array( '#f97316', '#ef4444', '#f59e0b', '#db2777', '#fb7185', '#facc15', '#c2410c', '#9d174d' ),
			),
			'forest'   => array(
				'label'  => __( 'Forest', 'statista-charts' ),
				'colors' => array( '#16a34a', '#65a30d', '#0d9488', '#4d7c0f', '#22c55e', '#84cc16', '#047857', '#a3e635' ),
			),
			'grape'    => array(
				'label'  => __( 'Grape', 'statista-charts' ),
				'colors' => array( '#7c3aed', '#a855f7', '#d946ef', '#6366f1', '#c084fc', '#e879f9', '#4f46e5', '#f0abfc' ),
			),
			'mono'     => array(
				'label'  => __( 'Monochrome', 'statista-charts' ),
				'colors' => array( '#111827', '#374151', '#4b5563', '#6b7280', '#9ca3af', '#c0c6cf', '#d1d5db', '#e5e7eb' ),
			),
		);

		/**
		 * Filters the available chart color palettes.
		 *
		 * @param array $palettes Palette slug => array with `label` and `colors`.
		 */
		return apply_filters( 'statista_charts_palettes', $palettes );
	}

	/**
	 * Default configuration values.
	 *
	 * @return array<string, mixed> Defaults.
	 */
	public static function defaults() {
		return array(
			'type'        => 'bar',
			'title'       => '',
			'showTitle'   => true,
			'source'      => '',
			'showSource'  => true,
			'showChart'   => true,
			'showLegend'  => true,
			'showValues'  => false,
			'showGrid'    => true,
			'showTable'   => false,
			'stacked'     => false,
			'smooth'      => true,
			'palette'     => 'statista',
			'theme'       => 'light',
			'direction'   => 'auto',
			'height'      => 360,
			'decimals'    => 0,
			'prefix'      => '',
			'suffix'      => '',
			'showCards'   => false,
			'cardsBar'    => true,
			'cards'       => array(),
			'labels'      => array(),
			'series'      => array(),
			'statValue'   => '',
			'statLabel'   => '',
			'statDelta'   => '',
			'statTrend'   => 'up',
			'statCaption' => '',
		);
	}

	/**
	 * Sanitizes a raw configuration array coming from the block, the shortcode or the REST API.
	 *
	 * @param array $raw Raw, untrusted configuration.
	 * @return array<string, mixed> Sanitized configuration, ready to be rendered.
	 */
	public static function sanitize( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$config = self::defaults();

		$config['type'] = in_array( (string) self::pick( $raw, 'type' ), self::TYPES, true )
			? (string) $raw['type']
			: $config['type'];

		foreach ( array( 'title', 'source', 'statLabel', 'statCaption', 'statValue', 'statDelta', 'prefix', 'suffix' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$config[ $key ] = sanitize_text_field( (string) $raw[ $key ] );
			}
		}

		foreach ( array( 'showTitle', 'showSource', 'showChart', 'showCards', 'cardsBar', 'showLegend', 'showValues', 'showGrid', 'showTable', 'stacked', 'smooth' ) as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$config[ $key ] = self::to_bool( $raw[ $key ] );
			}
		}

		$palettes          = self::palettes();
		$palette           = isset( $raw['palette'] ) ? sanitize_key( (string) $raw['palette'] ) : '';
		$config['palette'] = isset( $palettes[ $palette ] ) ? $palette : $config['palette'];
		$config['colors']  = $palettes[ $config['palette'] ]['colors'];

		$theme           = isset( $raw['theme'] ) ? sanitize_key( (string) $raw['theme'] ) : '';
		$config['theme'] = in_array( $theme, array( 'light', 'dark', 'auto' ), true ) ? $theme : $config['theme'];

		$direction           = isset( $raw['direction'] ) ? sanitize_key( (string) $raw['direction'] ) : '';
		$config['direction'] = in_array( $direction, array( 'auto', 'rtl', 'ltr' ), true ) ? $direction : $config['direction'];

		$trend               = isset( $raw['statTrend'] ) ? sanitize_key( (string) $raw['statTrend'] ) : '';
		$config['statTrend'] = in_array( $trend, array( 'up', 'down', 'flat' ), true ) ? $trend : $config['statTrend'];

		if ( isset( $raw['height'] ) ) {
			$config['height'] = min( 1000, max( 120, absint( $raw['height'] ) ) );
		}

		if ( isset( $raw['decimals'] ) ) {
			$config['decimals'] = min( 4, max( 0, absint( $raw['decimals'] ) ) );
		}

		$config['cards']  = Stats::sanitize_cards( self::pick( $raw, 'cards' ) );
		$config['labels'] = self::sanitize_labels( self::pick( $raw, 'labels' ) );
		$config['series'] = self::sanitize_series( self::pick( $raw, 'series' ), count( $config['labels'] ) );

		return $config;
	}

	/**
	 * Sanitizes the category labels.
	 *
	 * @param mixed $labels Raw labels.
	 * @return string[] Sanitized labels.
	 */
	private static function sanitize_labels( $labels ) {
		if ( ! is_array( $labels ) ) {
			return array();
		}

		$clean = array();
		foreach ( $labels as $label ) {
			if ( is_scalar( $label ) ) {
				$clean[] = sanitize_text_field( (string) $label );
			}
		}

		return $clean;
	}

	/**
	 * Sanitizes the data series.
	 *
	 * @param mixed $series       Raw series.
	 * @param int   $label_count  Number of labels, used to pad or trim each series.
	 * @return array<int, array<string, mixed>> Sanitized series.
	 */
	private static function sanitize_series( $series, $label_count ) {
		if ( ! is_array( $series ) ) {
			return array();
		}

		$all   = array();
		$clean = array();
		foreach ( $series as $set ) {
			if ( ! is_array( $set ) ) {
				continue;
			}

			$values = array();
			$data   = isset( $set['data'] ) && is_array( $set['data'] ) ? $set['data'] : array();
			foreach ( $data as $value ) {
				$values[] = self::to_number( $value );
			}

			if ( $label_count > 0 ) {
				$values = array_slice( $values, 0, $label_count );
				$values = array_pad( $values, $label_count, null );
			}

			$item = array(
				'label' => isset( $set['label'] ) && is_scalar( $set['label'] ) ? sanitize_text_field( (string) $set['label'] ) : '',
				'data'  => $values,
			);

			$all[] = $item;

			if ( self::csv_header_is_dimension( $item['label'] ) || self::csv_all_empty( $values ) ) {
				continue;
			}

			$clean[] = $item;
		}

		return empty( $clean ) ? $all : $clean;
	}

	/**
	 * Converts a raw cell to a float, or to null when it is not a number.
	 *
	 * @param mixed $value Raw value.
	 * @return float|null Number or null for a gap in the series.
	 */
	public static function to_number( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (float) $value;
		}

		if ( ! is_string( $value ) ) {
			return null;
		}

		// Accept "1,234.5", "1 234,5", "45%" and "₪1,200".
		$normalized = trim( $value );
		$normalized = str_replace( array( "\xc2\xa0", ' ', '%' ), '', $normalized );
		$normalized = preg_replace( '/[^0-9,.\-+]/u', '', $normalized );

		if ( '' === $normalized || null === $normalized ) {
			return null;
		}

		$last_comma = strrpos( $normalized, ',' );
		$last_dot   = strrpos( $normalized, '.' );

		if ( false !== $last_comma && false !== $last_dot ) {
			// The right-most separator is the decimal one.
			$decimal    = $last_comma > $last_dot ? ',' : '.';
			$thousands  = ',' === $decimal ? '.' : ',';
			$normalized = str_replace( $thousands, '', $normalized );
			$normalized = str_replace( $decimal, '.', $normalized );
		} elseif ( false !== $last_comma ) {
			$decimals   = strlen( $normalized ) - $last_comma - 1;
			$normalized = 3 === $decimals ? str_replace( ',', '', $normalized ) : str_replace( ',', '.', $normalized );
		}

		return is_numeric( $normalized ) ? (float) $normalized : null;
	}

	/**
	 * Casts loose booleans ("1", "true", "yes", "on") to real booleans.
	 *
	 * @param mixed $value Raw value.
	 * @return bool Boolean value.
	 */
	public static function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
		}

		return (bool) $value;
	}

	/**
	 * Reads a key from an array without notices.
	 *
	 * @param array  $source Source array.
	 * @param string $key    Key to read.
	 * @return mixed Value or null.
	 */
	private static function pick( $source, $key ) {
		return isset( $source[ $key ] ) ? $source[ $key ] : null;
	}

	/**
	 * Parses pasted data, in whichever of the two formats it is written.
	 *
	 * Most lines of `label: value` content carry a colon, which a CSV export
	 * practically never does.
	 *
	 * @param string $text Raw text.
	 * @return array{labels: string[], series: array<int, array<string, mixed>>} Parsed data.
	 */
	public static function parse_data( $text ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", str_replace( array( "\r\n", "\r" ), "\n", (string) $text ) ) ) );

		if ( empty( $lines ) ) {
			return array(
				'labels' => array(),
				'series' => array(),
			);
		}

		$delimiter = self::detect_delimiter( $lines[0] );
		$header    = str_getcsv( $lines[0], $delimiter, '"', '\\' );

		// A row with three or more cells is a table, even when titles contain
		// a colon ("Avengers: Endgame,1209,Movie").
		if ( count( $header ) >= 3 ) {
			return self::parse_csv( $text );
		}

		$with_colon = 0;
		foreach ( $lines as $line ) {
			if ( false !== strpos( $line, ':' ) ) {
				++$with_colon;
			}
		}

		return $with_colon > count( $lines ) / 2 ? self::parse_simple_list( $text ) : self::parse_csv( $text );
	}

	/**
	 * Parses the "one line per item" format, `label: value`.
	 *
	 * The counterpart of `src/lib/simple-list.js`, used wherever the editor is
	 * not available: the shortcode and the Elementor widgets. A colon, an equals
	 * sign, a tab, a spaced dash, plain spaces or a comma all separate the label
	 * from the value; empty lines are dropped.
	 *
	 * @param string $text Raw text, one item per line.
	 * @return array{labels: string[], series: array<int, array<string, mixed>>} Parsed data.
	 */
	public static function parse_simple_list( $text ) {
		$text  = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
		$lines = explode( "\n", $text );

		$labels = array();
		$values = array();

		foreach ( $lines as $raw_line ) {
			$line = trim( $raw_line );

			if ( '' === $line ) {
				continue;
			}

			// A bare number keeps its position as the label.
			if ( preg_match( '/^[\d\s.,%+\-–₪$€£]+$/u', $line ) ) {
				$only = self::to_number( $line );

				if ( null !== $only ) {
					$labels[] = (string) ( count( $labels ) + 1 );
					$values[] = $only;
					continue;
				}
			}

			list( $label, $raw_value ) = self::split_simple_line( $line );
			$value                     = self::to_number( $raw_value );

			// "120 January" reads as well as "January 120".
			if ( null === $value && '' !== $raw_value && null !== self::to_number( $label ) ) {
				$value = self::to_number( $label );
				$label = $raw_value;
			}

			$labels[] = $label;
			$values[] = $value;
		}

		return array(
			'labels' => $labels,
			'series' => empty( $labels ) ? array() : array(
				array(
					'label' => '',
					'data'  => $values,
				),
			),
		);
	}

	/**
	 * Splits one line of the simple format into a label and a raw value.
	 *
	 * Separators are tried in order, and whitespace comes before the comma so
	 * that "January 1,200" keeps its thousands separator.
	 *
	 * @param string $line One trimmed line.
	 * @return array{0: string, 1: string} Label and raw value.
	 */
	private static function split_simple_line( $line ) {
		$separators = array( '/[:：]/u', '/=/u', '/\t/u', '/\s[-–—]\s/u', '/\s+/u', '/,/u' );

		foreach ( $separators as $pattern ) {
			if ( ! preg_match_all( $pattern, $line, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$last   = end( $matches[0] );
			$offset = (int) $last[1];

			if ( $offset > 0 ) {
				return array(
					self::trim_edges( substr( $line, 0, $offset ) ),
					self::trim_edges( substr( $line, $offset + strlen( $last[0] ) ) ),
				);
			}
		}

		return array( self::trim_edges( $line ), '' );
	}

	/**
	 * Strips separator characters left at either end of a label or a value.
	 *
	 * @param string $text Raw text.
	 * @return string Trimmed text.
	 */
	private static function trim_edges( $text ) {
		return (string) preg_replace( '/^[\s,;:=–—-]+|[\s,;:=–—-]+$/u', '', (string) $text );
	}

	/**
	 * Parses a CSV/TSV string into labels and series.
	 *
	 * The first row is the header. A text column (Title, Name, …) becomes the
	 * category axis; columns that are mostly numbers become series. Dimension
	 * columns such as Category, Year, Timeframe or Data_Source are skipped so
	 * they do not show up as empty legend entries or as stacked bars.
	 *
	 * @param string $csv Raw CSV text.
	 * @return array{labels: string[], series: array<int, array<string, mixed>>} Parsed data.
	 */
	public static function parse_csv( $csv ) {
		$csv = (string) $csv;
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv ); // Strip the UTF-8 BOM Excel adds.
		$csv = str_replace( array( "\r\n", "\r" ), "\n", $csv );

		$lines = array_values( array_filter( explode( "\n", $csv ), static function ( $line ) {
			return '' !== trim( $line );
		} ) );

		if ( empty( $lines ) ) {
			return array(
				'labels' => array(),
				'series' => array(),
			);
		}

		$delimiter = self::detect_delimiter( $lines[0] );
		$rows      = array();
		foreach ( $lines as $line ) {
			$rows[] = str_getcsv( $line, $delimiter, '"', '\\' );
		}

		$header = array_map( 'trim', array_shift( $rows ) );
		$width  = count( $header );

		foreach ( $rows as $row ) {
			$width = max( $width, count( $row ) );
		}

		$columns = array();
		for ( $index = 0; $index < $width; $index++ ) {
			$columns[ $index ] = array();
			foreach ( $rows as $row ) {
				$columns[ $index ][] = isset( $row[ $index ] ) ? (string) $row[ $index ] : '';
			}
		}

		$label_index = self::csv_label_index( $header, $columns );
		$labels      = isset( $columns[ $label_index ] ) ? $columns[ $label_index ] : array();
		$series      = array();

		foreach ( $header as $index => $name ) {
			if ( $index === $label_index || ! isset( $columns[ $index ] ) ) {
				continue;
			}

			if ( ! self::csv_is_measure( $name, $columns[ $index ] ) ) {
				continue;
			}

			$data = array_map( array( __CLASS__, 'to_number' ), $columns[ $index ] );

			if ( self::csv_all_empty( $data ) ) {
				continue;
			}

			$series[] = array(
				'label' => (string) $name,
				'data'  => $data,
			);
		}

		if ( empty( $series ) ) {
			foreach ( $header as $index => $name ) {
				if ( $index === $label_index || ! isset( $columns[ $index ] ) ) {
					continue;
				}

				$series[] = array(
					'label' => (string) $name,
					'data'  => array_map( array( __CLASS__, 'to_number' ), $columns[ $index ] ),
				);
			}
		}

		return array(
			'labels' => $labels,
			'series' => $series,
		);
	}

	/**
	 * Picks the column that should label the x-axis.
	 *
	 * @param string[] $header  Header cells.
	 * @param array    $columns Column values, keyed by index.
	 * @return int Column index.
	 */
	private static function csv_label_index( $header, $columns ) {
		$preferred = array( 'title', 'name', 'label', 'product', 'game', 'item', 'movie', 'album', 'app', 'כותרת', 'שם', 'מוצר', 'משחק' );

		foreach ( $header as $index => $name ) {
			if ( in_array( self::csv_normalize_header( $name ), $preferred, true ) ) {
				return (int) $index;
			}
		}

		foreach ( $header as $index => $name ) {
			if ( ! self::csv_column_mostly_numeric( isset( $columns[ $index ] ) ? $columns[ $index ] : array() ) ) {
				return (int) $index;
			}
		}

		return 0;
	}

	/**
	 * Whether a column should be plotted as a numeric series.
	 *
	 * @param string $name   Header.
	 * @param array  $values Column cells.
	 * @return bool
	 */
	private static function csv_is_measure( $name, $values ) {
		if ( self::csv_header_is_dimension( $name ) ) {
			return false;
		}

		return self::csv_column_mostly_numeric( $values );
	}

	/**
	 * Headers that name a dimension, not a measured value.
	 *
	 * @param string $name Header.
	 * @return bool
	 */
	private static function csv_header_is_dimension( $name ) {
		$normalized = self::csv_normalize_header( $name );
		$exact      = array(
			'category',
			'categories',
			'type',
			'types',
			'genre',
			'source',
			'datasource',
			'timeframe',
			'period',
			'date',
			'year',
			'releaseyear',
			'releasedate',
			'id',
			'uid',
			'url',
			'link',
			'notes',
			'note',
			'comment',
			'platform',
			'region',
			'country',
			'status',
			'format',
			'קטגוריה',
			'סוג',
			'מקור',
			'שנה',
			'תאריך',
			'תקופה',
		);

		if ( in_array( $normalized, $exact, true ) ) {
			return true;
		}

		return (bool) preg_match( '/(^|[_\s-])(category|categories|source|timeframe|genre|platform|year|date|id|type)s?$/i', $name );
	}

	/**
	 * Whether at least 70% of filled cells in a column are numbers.
	 *
	 * @param array $values Column cells.
	 * @return bool
	 */
	private static function csv_column_mostly_numeric( $values ) {
		$numeric = 0;
		$filled  = 0;

		foreach ( $values as $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			++$filled;

			if ( null !== self::to_number( $value ) ) {
				++$numeric;
			}
		}

		return $filled > 0 && ( $numeric / $filled ) >= 0.7;
	}

	/**
	 * @param array $data Series values.
	 * @return bool
	 */
	private static function csv_all_empty( $data ) {
		foreach ( $data as $value ) {
			if ( null !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $name Header.
	 * @return string
	 */
	private static function csv_normalize_header( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = str_replace( array( ' ', '-', '_' ), '', $name );

		return $name;
	}

	/**
	 * Guesses the delimiter of a CSV header line.
	 *
	 * @param string $line Header line.
	 * @return string Delimiter.
	 */
	private static function detect_delimiter( $line ) {
		$candidates = array( ',' => 0, "\t" => 0, ';' => 0, '|' => 0 );
		foreach ( $candidates as $candidate => $unused ) {
			$candidates[ $candidate ] = substr_count( $line, $candidate );
		}

		arsort( $candidates );
		$best = array_key_first( $candidates );

		return $candidates[ $best ] > 0 ? $best : ',';
	}
}
