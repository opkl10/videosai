=== Statista Charts ===
Contributors: statistacharts
Tags: chart, graph, statistics, gutenberg, csv
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, responsive statistics and charts inside posts: a Gutenberg block plus a matching [statista_chart] shortcode.

== Description ==

Statista Charts adds a "Statista Chart" block you can drop anywhere inside a post, and a shortcode for classic editors, widgets and page builders.

Features:

* Chart types: bars, lines, area, pie, doughnut and a single number ("stat card").
* Data entry in the block itself: write one line per item, `label: value`, and the chart follows every keystroke. A table with several series and CSV import wait under "Advanced options".
* Toggles for the chart itself, the title, the data source, the legend, the value labels, the grid and a visible data table.
* Six color palettes, light/dark/automatic themes, and full RTL and Hebrew support.
* Rendered in the browser with Chart.js, bundled with the plugin and loaded only on pages that actually contain a chart.
* The numbers are always printed as an HTML table (visually hidden unless you show it), so screen readers and search engines can read the data.

== Usage ==

Block: add "Statista Chart" from the inserter and write the data right under the chart, one line per item:

`January: 120
February: 138
March: 151`

A colon, a comma, a tab or a dash all separate the name from the number, empty lines are ignored, and values such as `1,200`, `58%` or `₪1,250` are understood. One click fills the example in. The multi-series table, CSV import and the ready-made templates sit behind "Advanced options"; everything else lives in the sidebar.

Shortcode:

`[statista_chart type="bar" title="Visitors" labels="Jan,Feb,Mar" series="2025: 10,20,30 | 2024: 8,15,22"]`

or with CSV inside the tag:

`[statista_chart type="line" title="Visitors" source="Analytics"]
Month,2025,2024
January,120,90
February,138,96
[/statista_chart]`

Shortcode attributes: type, title, show_title, source, show_source, show_chart, show_legend, show_values, show_grid, show_table, stacked, smooth, palette, theme, direction, height, decimals, prefix, suffix, labels, series, data, and for the stat card value, label, delta, trend and caption.

== Installation ==

1. Upload the plugin zip in Plugins → Add New → Upload Plugin.
2. Activate it.
3. Add the "Statista Chart" block to a post, or use the [statista_chart] shortcode.

== Development ==

`npm install`
`npm run build`
`npm run make-pot` and `npm run make-json` (WP-CLI) refresh the translation templates.

== Changelog ==

= 1.1.0 =
* Data is now entered as plain lines of "label: value" right under the chart, updating it live; the table, CSV import and templates moved behind "Advanced options".
* New blocks start empty with an example that fills itself in one click.
* Clear messages for lines that hold no number.

= 1.0.0 =
* First release: block, shortcode, CSV import, six palettes, dark mode, RTL and a Hebrew translation.
