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

		// Run after share / WhatsApp / Telegram plugins have already glued
		// their boxes onto the first paragraph or heading in the post.
		add_filter( 'render_block', array( __CLASS__, 'scrub_rendered_block' ), 999, 2 );
		add_filter( 'the_content', array( __CLASS__, 'scrub_content' ), 9999 );
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
			'panelPosition'   => 'end',
			'kpiPosition'     => 'top',
			'kpiLayout'       => 'row',
			'kpiPerRow'       => 0,
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

		$panel_position = isset( $raw['panelPosition'] ) ? sanitize_key( (string) $raw['panelPosition'] ) : '';
		$clean['panelPosition'] = in_array( $panel_position, array( 'end', 'start', 'left', 'right', 'bottom' ), true )
			? $panel_position
			: 'end';

		$kpi_position = isset( $raw['kpiPosition'] ) ? sanitize_key( (string) $raw['kpiPosition'] ) : '';
		$clean['kpiPosition'] = in_array( $kpi_position, array( 'top', 'bottom', 'start', 'end' ), true )
			? $kpi_position
			: 'top';

		$kpi_layout = isset( $raw['kpiLayout'] ) ? sanitize_key( (string) $raw['kpiLayout'] ) : '';
		$clean['kpiLayout'] = in_array( $kpi_layout, array( 'row', 'column' ), true ) ? $kpi_layout : 'row';

		if ( isset( $raw['kpiPerRow'] ) ) {
			$clean['kpiPerRow'] = min( 6, max( 0, absint( $raw['kpiPerRow'] ) ) );
		}

		if ( isset( $raw['logoSize'] ) ) {
			$clean['logoSize'] = min( 160, max( 14, absint( $raw['logoSize'] ) ) );
		}

		if ( isset( $raw['kpis'] ) && is_array( $raw['kpis'] ) ) {
			foreach ( $raw['kpis'] as $kpi ) {
				if ( ! is_array( $kpi ) ) {
					continue;
				}

				if ( ! empty( $kpi['empty'] ) ) {
					$clean['kpis'][] = array(
						'empty' => true,
					);
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

		list( $content, $foreign ) = self::split_foreign_markup( (string) $content );

		$panel_button = $data['showPanelButton'] ? $data['panelButtonText'] : '';

		if ( ! $data['showPanel'] || '' === $data['panelTitle'] . $data['panelText'] . $panel_button ) {
			$classes[] = 'sd-no-panel';
		} else {
			$classes[] = 'sd-panel-pos-' . $data['panelPosition'];
		}

		if ( empty( $data['kpis'] ) ) {
			$classes[] = 'sd-no-kpis';
		} else {
			$classes[] = 'sd-kpis-pos-' . $data['kpiPosition'];
			$classes[] = 'sd-kpis-layout-' . $data['kpiLayout'];

			if ( 'column' !== $data['kpiLayout'] && $data['kpiPerRow'] >= 1 ) {
				$classes[] = 'sd-kpis-cols';
			}
		}

		$style = sprintf(
			'--sc-accent: %1$s; --sc-accent-2: %2$s; --sd-banner: %3$s; --sd-logo-size: %4$dpx;',
			$data['colors'][0],
			$data['colors'][1],
			self::banner_background( $data ),
			$data['logoSize']
		);

		if ( 'column' !== $data['kpiLayout'] && $data['kpiPerRow'] >= 1 ) {
			$style .= sprintf( ' --sd-kpi-cols: %d;', (int) $data['kpiPerRow'] );
		}

		$data['resolvedDirection'] = $direction;
		$pin_panel                 = in_array( $data['panelPosition'], array( 'left', 'right' ), true )
			&& ! in_array( 'sd-no-panel', $classes, true );

		ob_start();
		?>
		<section
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			dir="<?php echo esc_attr( $direction ); ?>"
			style="<?php echo esc_attr( $style ); ?>"
			<?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?>
		>
			<?php echo self::banner( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in banner(). ?>

			<div class="sd-body"<?php echo $pin_panel ? ' dir="ltr"' : ''; ?>>
				<div class="sd-main"<?php echo $pin_panel ? ' dir="' . esc_attr( $direction ) . '"' : ''; ?>>
					<?php echo self::kpis( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in kpis(). ?>

					<?php if ( '' !== trim( (string) $content ) ) : ?>
						<div class="sd-grid"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks, already rendered by core. ?></div>
					<?php endif; ?>
				</div>

				<?php echo self::panel( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in panel(). ?>
			</div>
		</section>
		<?php if ( '' !== $foreign ) : ?>
			<div class="statista-dashboard-after"><?php echo $foreign; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Another plugin's own markup, moved as it is. ?></div>
		<?php endif; ?>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Separates our own charts from markup other plugins glued onto them.
	 *
	 * Share buttons, "join our channel" boxes and ad slots are commonly added
	 * with a render_block filter, which lands them inside the dashboard, next
	 * to a chart. They are moved below the dashboard rather than dropped.
	 *
	 * @param string $content Rendered inner blocks.
	 * @return array {
	 *     @type string $0 The charts.
	 *     @type string $1 Everything else, in document order.
	 * }
	 */
	private static function split_foreign_markup( $content ) {
		$content = (string) $content;

		if ( '' === trim( $content ) || ! class_exists( '\DOMDocument' ) ) {
			return array( $content, '' );
		}

		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );

		$loaded = $doc->loadHTML(
			'<?xml encoding="utf-8" ?><body><div id="statista-inner">' . $content . '</div></body>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $loaded ? $doc->getElementById( 'statista-inner' ) : null;

		if ( ! $root ) {
			return array( $content, '' );
		}

		list( $ours, $foreign ) = self::sort_nodes( $root->childNodes, $doc );

		if ( '' === trim( $foreign ) ) {
			return array( $content, '' );
		}

		/**
		 * Filters the markup other plugins added inside the dashboard, which
		 * is about to be printed below it. Return an empty string to drop it.
		 *
		 * @param string $foreign The markup.
		 */
		$foreign = (string) apply_filters( 'statista_charts_foreign_markup', $foreign );

		return array( $ours, $foreign );
	}

	/**
	 * Walks a list of rendered nodes and tells our charts from the rest.
	 *
	 * A node that only wraps one of our blocks is opened up: the block stays
	 * in the dashboard and the wrapper leaves with the markup it carried.
	 *
	 * @param \DOMNodeList  $nodes Nodes to sort.
	 * @param \DOMDocument  $doc   Owner document.
	 * @return array {
	 *     @type string $0 Our markup.
	 *     @type string $1 Everything else.
	 * }
	 */
	private static function sort_nodes( $nodes, $doc ) {
		$ours    = '';
		$foreign = '';

		foreach ( iterator_to_array( $nodes ) as $node ) {
			if ( XML_TEXT_NODE === $node->nodeType && '' === trim( $node->textContent ) ) {
				continue;
			}

			if ( self::is_ours( $node ) ) {
				$ours .= $doc->saveHTML( $node );
				continue;
			}

			if ( ! self::wraps_ours( $node ) ) {
				$foreign .= $doc->saveHTML( $node );
				continue;
			}

			list( $inner_ours, $inner_foreign ) = self::sort_nodes( $node->childNodes, $doc );

			$ours .= $inner_ours;

			if ( '' !== trim( $inner_foreign ) ) {
				$shell = $node->cloneNode( false );
				$shell->appendChild( $doc->createTextNode( '' ) );

				$html     = $doc->saveHTML( $shell );
				$close    = '</' . $node->nodeName . '>';
				$foreign .= str_replace( $close, $inner_foreign . $close, $html );
			}
		}

		return array( $ours, $foreign );
	}

	/**
	 * Tells whether a node is one of our blocks.
	 *
	 * @param \DOMNode $node Node from the inner block markup.
	 * @return bool
	 */
	private static function is_ours( $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return false;
		}

		$classes = ' ' . (string) $node->getAttribute( 'class' ) . ' ';

		foreach ( array( 'statista-chart', 'statista-stats' ) as $name ) {
			if ( false !== strpos( $classes, ' ' . $name . ' ' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Tells whether one of our blocks sits somewhere below a node.
	 *
	 * @param \DOMNode $node Node from the inner block markup.
	 * @return bool
	 */
	private static function wraps_ours( $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return false;
		}

		$xpath = new \DOMXPath( $node->ownerDocument );

		foreach ( array( 'statista-chart', 'statista-stats' ) as $name ) {
			$found = $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $name . " ')]", $node );

			if ( $found && $found->length > 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Cleans a rendered dashboard block after other plugins have had a turn.
	 *
	 * @param string $html  Rendered block HTML.
	 * @param array  $block Block being rendered.
	 * @return string
	 */
	public static function scrub_rendered_block( $html, $block ) {
		if ( ! isset( $block['blockName'] ) || self::NAME !== $block['blockName'] ) {
			return $html;
		}

		return self::scrub_html( (string) $html );
	}

	/**
	 * Cleans dashboards inside post content after `the_content` filters.
	 *
	 * WhatsApp / Telegram plugins commonly splice their box after the first
	 * `<p>` or `<h2>` in the post. That paragraph used to be the brand line
	 * inside the banner, which is why the buttons appeared there.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function scrub_content( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, 'statista-dashboard' ) ) {
			return $content;
		}

		return self::scrub_html( $content );
	}

	/**
	 * Pulls foreign nodes out of every dashboard in an HTML fragment.
	 *
	 * @param string $html HTML that may contain a dashboard.
	 * @return string
	 */
	private static function scrub_html( $html ) {
		if ( '' === trim( $html ) || ! class_exists( '\DOMDocument' ) ) {
			return $html;
		}

		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $doc->loadHTML(
			'<?xml encoding="utf-8" ?><div id="statista-root">' . $html . '</div>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $loaded ? $doc->getElementById( 'statista-root' ) : null;

		if ( ! $root ) {
			return $html;
		}

		$xpath      = new \DOMXPath( $doc );
		$dashboards = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " statista-dashboard ")]', $root );

		if ( ! $dashboards || 0 === $dashboards->length ) {
			return $html;
		}

		$changed = false;

		foreach ( iterator_to_array( $dashboards ) as $dashboard ) {
			$foreign = array();
			self::pull_foreign_from( $dashboard, $doc, $foreign );

			if ( empty( $foreign ) ) {
				continue;
			}

			$changed = true;
			$markup  = (string) apply_filters( 'statista_charts_foreign_markup', implode( '', $foreign ) );

			if ( '' === trim( $markup ) ) {
				continue;
			}

			$after = $dashboard->nextSibling;
			$place = null;

			while ( $after ) {
				if ( XML_ELEMENT_NODE === $after->nodeType ) {
					$classes = ' ' . (string) $after->getAttribute( 'class' ) . ' ';

					if ( false !== strpos( $classes, ' statista-dashboard-after ' ) ) {
						$place = $after;
						break;
					}

					break;
				}

				$after = $after->nextSibling;
			}

			if ( ! $place ) {
				$place = $doc->createElement( 'div' );
				$place->setAttribute( 'class', 'statista-dashboard-after' );

				if ( $dashboard->parentNode ) {
					$dashboard->parentNode->insertBefore( $place, $dashboard->nextSibling );
				}
			}

			// The foreign HTML was serialized from this same document, so a
			// second parse is the reliable way to append it back.
			$tmp      = new \DOMDocument();
			$tmp_prev = libxml_use_internal_errors( true );
			$tmp->loadHTML(
				'<?xml encoding="utf-8" ?><div id="statista-moved">' . $markup . '</div>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
			);
			libxml_clear_errors();
			libxml_use_internal_errors( $tmp_prev );

			$moved = $tmp->getElementById( 'statista-moved' );

			if ( $moved ) {
				foreach ( iterator_to_array( $moved->childNodes ) as $child ) {
					$place->appendChild( $doc->importNode( $child, true ) );
				}
			}
		}

		if ( ! $changed ) {
			return $html;
		}

		$out = '';

		foreach ( $root->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}

		return $out;
	}

	/**
	 * Moves unknown element children of a node (and of its descendants) out.
	 *
	 * @param \DOMNode $parent  Node to walk.
	 * @param \DOMDocument $doc Owner document.
	 * @param array        $foreign Collected HTML, by reference.
	 * @return void
	 */
	private static function pull_foreign_from( $parent, $doc, &$foreign ) {
		if ( ! $parent || ! $parent->childNodes ) {
			return;
		}

		foreach ( iterator_to_array( $parent->childNodes ) as $node ) {
			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}

			if ( self::node_belongs( $node ) ) {
				self::pull_foreign_from( $node, $doc, $foreign );
				continue;
			}

			$foreign[] = $doc->saveHTML( $node );
			$parent->removeChild( $node );
		}
	}

	/**
	 * Tells whether a node is part of the dashboard, a chart, or a native
	 * element that those components legitimately contain (image, canvas…).
	 *
	 * @param \DOMNode $node Node to test.
	 * @return bool
	 */
	private static function node_belongs( $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return false;
		}

		$tag = strtolower( $node->nodeName );

		static $keep_tags = array(
			'img'      => true,
			'canvas'   => true,
			'svg'      => true,
			'path'     => true,
			'g'        => true,
			'table'    => true,
			'thead'    => true,
			'tbody'    => true,
			'tfoot'    => true,
			'tr'       => true,
			'th'       => true,
			'td'       => true,
			'col'      => true,
			'colgroup' => true,
			'caption'  => true,
			'br'       => true,
			'script'   => true,
			'style'    => true,
			'noscript' => true,
		);

		if ( isset( $keep_tags[ $tag ] ) ) {
			return true;
		}

		if ( self::class_is_ours( (string) $node->getAttribute( 'class' ) ) ) {
			return true;
		}

		return self::wraps_ours( $node ) || self::wraps_frame( $node );
	}

	/**
	 * Tells whether a class list belongs to this plugin or a WP block wrapper.
	 *
	 * @param string $class Class attribute.
	 * @return bool
	 */
	private static function class_is_ours( $class ) {
		foreach ( preg_split( '/\s+/', (string) $class ) as $token ) {
			if ( '' === $token ) {
				continue;
			}

			if ( 0 === strpos( $token, 'sd-' )
				|| 0 === strpos( $token, 'sc-' )
				|| 0 === strpos( $token, 'ss-' )
				|| 0 === strpos( $token, 'statista-' )
				|| 0 === strpos( $token, 'wp-block-' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Tells whether a dashboard frame (banner, body, panel) sits below a node.
	 *
	 * @param \DOMNode $node Node to search.
	 * @return bool
	 */
	private static function wraps_frame( $node ) {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return false;
		}

		$xpath = new \DOMXPath( $node->ownerDocument );

		foreach ( array( 'sd-banner', 'sd-body', 'sd-main', 'sd-grid', 'sd-panel', 'sd-kpis' ) as $name ) {
			$found = $xpath->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $name . " ')]", $node );

			if ( $found && $found->length > 0 ) {
				return true;
			}
		}

		return false;
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
					<div class="sd-brand">
						<?php if ( '' !== $data['logoUrl'] ) : ?>
							<img class="sd-brand__logo" src="<?php echo esc_url( $data['logoUrl'] ); ?>" alt="" />
						<?php endif; ?>
						<?php if ( '' !== $data['brandName'] ) : ?>
							<span class="sd-brand__name"><?php echo esc_html( $data['brandName'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $data['title'] || '' !== $data['titleAccent'] ) : ?>
					<div class="sd-title" role="heading" aria-level="2">
						<span class="sd-title__main"><?php echo esc_html( $data['title'] ); ?></span>
						<?php if ( '' !== $data['titleAccent'] ) : ?>
							<span class="sd-title__accent"><?php echo esc_html( $data['titleAccent'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( '' !== $data['meta'] || '' !== $data['updated'] ) : ?>
					<div class="sd-meta">
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
					</div>
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
				<?php if ( ! empty( $kpi['empty'] ) ) : ?>
					<div class="sd-kpi sd-kpi--slot" aria-hidden="true"></div>
					<?php continue; ?>
				<?php endif; ?>
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
							<?php echo self::kpi_mark( $kpi['icon'], $split ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in kpi_mark(). ?>
							<span class="sd-kpi__number" dir="ltr"><?php echo esc_html( $kpi['value'] ); ?></span>
						</span>

						<?php if ( $split ) : ?>
							<span class="sd-kpi__value">
								<?php echo self::kpi_mark( $kpi['icon2'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in kpi_mark(). ?>
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
	 * Renders the colour swatch or platform name next to a split KPI value.
	 *
	 * @param string $mark     Icon emoji or a platform name.
	 * @param bool   $as_badge Whether this card is split (badges only then).
	 * @return string
	 */
	private static function kpi_mark( $mark, $as_badge ) {
		$mark = (string) $mark;

		if ( ! $as_badge || '' === trim( $mark ) ) {
			return '';
		}

		$is_text = self::kpi_mark_is_text( $mark );
		$class   = 'sd-kpi__badge' . ( $is_text ? ' sd-kpi__badge--text' : '' );

		return sprintf(
			'<span class="%1$s"%2$s>%3$s</span>',
			esc_attr( $class ),
			$is_text ? '' : ' aria-hidden="true"',
			esc_html( $mark )
		);
	}

	/**
	 * A swatch is a colour emoji or symbol. Letters or digits mean a name.
	 *
	 * @param string $mark Icon or label.
	 * @return bool
	 */
	private static function kpi_mark_is_text( $mark ) {
		return (bool) preg_match( '/\p{L}|\p{N}/u', (string) $mark );
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

		$pin = in_array( $data['panelPosition'], array( 'left', 'right' ), true );
		$dir = isset( $data['resolvedDirection'] ) ? $data['resolvedDirection'] : '';

		ob_start();
		?>
		<aside class="sd-panel"<?php echo $pin && '' !== $dir ? ' dir="' . esc_attr( $dir ) . '"' : ''; ?>>
			<?php if ( '' !== $data['panelImage'] ) : ?>
				<img class="sd-panel__image" src="<?php echo esc_url( $data['panelImage'] ); ?>" alt="" />
			<?php endif; ?>

			<?php if ( '' !== $data['panelKicker'] ) : ?>
				<div class="sd-panel__kicker"><?php echo esc_html( $data['panelKicker'] ); ?></div>
			<?php endif; ?>

			<?php if ( '' !== $data['panelTitle'] ) : ?>
				<div class="sd-panel__title" role="heading" aria-level="3"><?php echo esc_html( $data['panelTitle'] ); ?></div>
			<?php endif; ?>

			<?php if ( '' !== $data['panelText'] ) : ?>
				<div class="sd-panel__text"><?php echo esc_html( $data['panelText'] ); ?></div>
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
