=== Statista Charts ===
Contributors: statistacharts
Tags: chart, graph, statistics, gutenberg, elementor
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.10
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Beautiful, responsive statistics and charts inside posts: Gutenberg blocks, Elementor widgets and a matching [statista_chart] shortcode.

== Description ==

Statista Charts adds three blocks you can drop anywhere inside a post — a chart, a row of stat cards and a whole dashboard — plus two Elementor widgets and a shortcode for classic editors.

Features:

* Chart types: bars, lines, area, pie, doughnut and a single number ("stat card").
* A "Statista Stat Cards" block for percentages and single figures: an icon, a label, the number, an up or down change and a bar drawn for percentages.
* A "Statista Dashboard" block: banner, metric cards, a grid of charts and an optional side panel.
* Elementor widgets for the chart and the stat cards, with the same data format and the same palettes.
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

Elementor: open the widget panel, search for "Statista", and drag in "Statista Chart" or "Statista Stat Cards". The chart widget takes the same one-line-per-item data; the cards widget is a repeater where each row is one card.

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
3. Add the "Statista Chart", "Statista Stat Cards" or "Statista Dashboard" block to a post, drag a Statista widget into an Elementor page, or use the [statista_chart] shortcode.

== Development ==

`npm install`
`npm run build`
`npm run make-pot` and `npm run make-json` (WP-CLI) refresh the translation templates.

== Changelog ==

= 1.4.10 =
* Dragging a metric card onto another card swaps those two. The other cards stay where they are.

= 1.4.9 =
* Metric cards in the dashboard can be reordered by dragging the handle, or with the move buttons on each card.

= 1.4.8 =
* The side panel can sit before or after the charts, on the left, on the right, or below them.
* Metric cards can sit above, below or beside the charts, in a row or stacked as a column.

= 1.4.7 =
* CSV import uses a name column for the axis and only numeric columns as series. Category, Year, Timeframe and Data_Source no longer appear in the legend or as empty tooltip rows.

= 1.4.6 =
* Long category names on bar and line charts are wrapped and given extra canvas padding so they are no longer clipped at the bottom or sides.

= 1.4.5 =
* With the side panel on, KPI cards keep their natural height instead of stretching to match the panel.

= 1.4.4 =
* KPI card labels and platform names sit at a readable size next to the figures, without the tiny uppercase caption style.

= 1.4.3 =
* Split KPI cards accept a normal-size platform name next to each value, instead of only a colour square.

= 1.4.2 =
* WhatsApp and Telegram join boxes no longer land in the dashboard banner. The banner no longer uses headings or paragraphs, so plugins that splice themselves after the first &lt;p&gt; or &lt;h2&gt; miss it. Anything that still gets in is moved below the dashboard, including boxes inserted by JavaScript.

= 1.4.1 =
* Share buttons and "join our channel" boxes that other plugins attach to every block are moved below the dashboard instead of taking a place in the chart area. The `statista_charts_foreign_markup` filter can drop them instead.

= 1.4.0 =
* The dashboard logo has a size slider, and a wide logo keeps its proportions.
* The side panel button can be switched off without clearing its text.
* Markup that other plugins inject after every block now gets a full-width row inside the dashboard instead of a seat in the chart grid or next to the side panel.

= 1.3.0 =
* The chart block can now show a row of stat cards above the chart, with separate switches for the cards and for the chart itself — so one block can be cards only, a chart only, or both.
* The same two switches reached the shortcode, as show_cards, cards_bar and cards.

= 1.2.0 =
* New "Statista Stat Cards" block: a row of cards for percentages and single figures, with an optional bar and an up or down change.
* Elementor widgets for the chart and the stat cards.
* The shortcode now also accepts data written one line per item, not only CSV.

= 1.1.0 =
* Data is now entered as plain lines of "label: value" right under the chart, updating it live; the table, CSV import and templates moved behind "Advanced options".
* New blocks start empty with an example that fills itself in one click.
* Clear messages for lines that hold no number.

= 1.0.0 =
* First release: block, shortcode, CSV import, six palettes, dark mode, RTL and a Hebrew translation.
