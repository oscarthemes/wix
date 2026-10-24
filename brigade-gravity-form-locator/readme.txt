=== Brigade Gravity Form Locator ===
Contributors: brigadethemes
Tags: gravity forms, forms, shortcode, page builder, admin
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Find every place a Gravity Forms form is used: pages, posts, templates, synced patterns, widgets, theme files and page builder layouts.

== Description ==

Brigade Gravity Form Locator scans your site and shows, in one dashboard, where each Gravity Forms form is embedded and how. Instead of opening every page, template and builder layout to find a form, run a scan and get a searchable, filterable list with links to view and edit each location.

This plugin is an independent tool and is not affiliated with or endorsed by Rocketgenius, the makers of Gravity Forms.

= What it finds =

* Gravity Forms shortcodes (`[gravityform id="5"]`, `[gravityforms …]`, `name="Form title"`, any attribute order or quoting)
* The Gravity Forms block, Shortcode blocks and shortcodes inside any block
* Synced patterns (reusable blocks) and site editor templates and template parts
* Classic Editor content
* Elementor: Shortcode, Text Editor and HTML widgets, shortcodes inside any widget setting, third-party Gravity Forms widgets, Theme Builder templates, popups and global widgets
* WPBakery: the Gravity Form element, Text Block, Raw HTML (decoded), Templatera templates, grid items and saved templates
* Divi: shortcodes inside modules, third-party Gravity Forms modules, Divi Library and Theme Builder layouts (Divi 4 shortcodes and Divi 5 block markup)
* Beaver Builder, Bricks, Oxygen, SiteOrigin, Themify and Cornerstone layouts stored in post meta, and custom fields such as ACF
* The Gravity Forms widget, block widgets, Text and Custom HTML widgets
* Theme files: `gravity_form( 5 )`, `GFForms::get_form( 5 )` and `do_shortcode( '[gravityform id="5"]' )` with literal IDs, plus block markup in theme templates, parts and patterns

For every usage the dashboard shows the form name and ID, the number of distinct locations, the page or template, view and edit links, the builder, the implementation method, the post status and when it was scanned. The builder and the method are reported separately: a shortcode inside an Elementor widget is "Builder: Elementor, Implementation: Shortcode".

= Honest results =

* **Confirmed** references are shortcodes, blocks, widgets and template function calls with a literal form ID or title.
* **Likely** references are form ID settings of third-party widgets or modules whose name mentions Gravity Forms.
* **Possible** references are rendered Gravity Forms markup pasted into content. They prove a form was rendered at some point, not that the page embeds it today. You can switch them off.
* References that cannot be resolved statically, such as `gravity_form( $form_id )` or `[gravityform id="{embed_id}"]`, are listed as unresolved and never counted as usages.
* Form IDs that do not exist in Gravity Forms are flagged "not found".
* Forms without any detection are labelled **No detected usage**, not "unused": a form can still be output by code this plugin cannot see.
* A global template (for example an Elementor footer) is counted once, as a template-level usage, not once for every page that displays it.

= Dashboard =

* Summary: registered forms, forms detected in use, forms with no detected usage, distinct usage locations and the last successful scan
* Three views: every usage location, one row per form with expandable locations, and forms with no detected usage
* Search by form name, form ID, page title or URL
* Filter by builder, implementation method, status, location type and confidence
* Sortable columns and pagination
* CSV export of the current filters, protected against spreadsheet formula injection
* Scans, Refresh, search, filters, sorting, tabs and pagination update the results in place, without reloading the page

= Safe, resumable scanning =

* Scans run in small AJAX batches with a progress bar, so large sites do not hit PHP time or memory limits.
* Each scan writes its results separately and only replaces the visible results when it completes. An interrupted or failed scan never deletes your last good results, and can be resumed.
* Overlapping scans are prevented with a lock.
* Optional daily or weekly scans with WP-Cron.
* The scan only reads data. It never renders pages, executes theme or builder code, or changes posts, templates, builder layouts, forms or entries.

= Privacy =

The plugin makes no external requests, loads no remote assets, and collects or sends no data. Results are stored in your own database and are only visible to administrators.

== Installation ==

1. Upload the `brigade-gravity-form-locator` folder to `/wp-content/plugins/`, or install the ZIP from Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Go to **Brigade GF Locator** in the admin menu and click **Scan website**.

Gravity Forms must be installed and active for a complete scan. Without it, the plugin still finds form IDs in your content, but cannot show form names or list forms with no detected usage.

== Frequently Asked Questions ==

= Who can see the results? =

Users with the `manage_options` capability (administrators). Developers can change this with the `bgfl_capability` filter.

= Does scanning change my content? =

No. The scanner reads posts, post meta, widgets, a builder option and theme files. It never writes to them. Resetting scan data deletes only this plugin's own results and history.

= Why is a form listed as "No detected usage" when I know it is on my site? =

The form may be output in a way that cannot be detected by reading stored data: a PHP call with a variable ID, a plugin that outputs the form from its own code, JavaScript, a popup plugin that stores its content in a custom format, or a remote service. Check "Unresolved references and skipped items" on the dashboard for references that were found but could not be matched to a form.

= Which builders are supported? =

Block editor, Classic Editor, Elementor, WPBakery and widgets are supported. Divi, theme files and builders that store layouts in post meta (Beaver Builder, Bricks, Oxygen, SiteOrigin, Themify, Cornerstone) are partially supported: shortcodes are found reliably, while builder-specific Gravity Forms elements are recognised only when their name mentions Gravity Forms and they store a form ID setting. The Settings screen lists every detector and what it reads.

Detection works on the stored data formats of these builders; it has been tested with fixture data, not with every version of every builder.

= Does it scan plugins' PHP files? =

No. Only the active theme and its parent theme are scanned. Developers can add directories with the `bgfl_theme_file_roots` filter.

= Is a template counted for every page that uses it? =

No. A form in a header, footer or other global template is reported once, on the template, with the template's edit link.

= How reliable are scheduled scans? =

Scheduled scans use WP-Cron, which runs when the site receives visits. On low-traffic sites, configure a real server cron job that calls `wp-cron.php` for reliable timing. Long scheduled scans continue in follow-up cron events.

= Can I add support for another builder? =

Yes. Implement the `Brigade\GravityFormLocator\Detectors\Detector` interface (or extend `Abstract_Detector`) and add an instance with the `bgfl_detectors` filter.

= What is removed when I delete the plugin? =

The plugin's database table, its options and its scheduled events. Gravity Forms, entries and your content are never touched. Deactivating the plugin only clears its scheduled events.

== Screenshots ==

1. Usage locations: every form, where it is used, the builder, implementation method, confidence and status.
2. By form: one row per form with an expandable list of its locations, including forms with no detected usage and referenced forms that do not exist.
3. Settings: scheduled scanning, post types and statuses, detectors with their support level, history and reset.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
