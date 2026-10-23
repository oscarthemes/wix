# Detection support matrix

Brigade Gravity Form Locator detects forms by **reading stored data**. It never renders
pages or executes theme, builder or plugin code. This page lists what each detector reads,
how confident its results are, and what cannot be detected.

Confidence levels:

- **Confirmed**: a Gravity Forms shortcode, block, widget or template function call with a
  literal form ID or title.
- **Likely**: a form ID setting on a third-party widget, module or element whose name mentions
  Gravity Forms (for example `eael-gravity-form`, `dsm_gravity_form`).
- **Possible**: rendered Gravity Forms markup (`id="gform_wrapper_5"`) pasted into content.
  Can be turned off in Settings.

Testing status: every detector is covered by automated tests that use fixture data in each
builder's stored format (see `tests/`). The builders themselves were **not installed** during
testing, and Gravity Forms was replaced by a minimal `GFAPI` stub. Verify against your real
builder versions before relying on a "supported" label.

## Supported

| Integration | Where it looks | What it recognises |
| --- | --- | --- |
| Block editor | `post_content` of posts, pages, custom post types, `wp_block` (synced patterns), `wp_template`, `wp_template_part` | `gravityforms/form` block (`formId`), Shortcode block, shortcodes inside any block's HTML or attributes, Classic (freeform) blocks |
| Classic Editor | `post_content` not claimed by a builder | `[gravityform]` / `[gravityforms]` shortcodes |
| Elementor | `_elementor_data` of posts with `_elementor_edit_mode = builder` and all `elementor_library` posts (Theme Builder, popups, sections, global widgets) | Shortcode widget, Text Editor and HTML widgets, shortcodes in any widget setting, third-party widgets mentioning Gravity Forms with a form ID setting |
| WPBakery | `post_content` with `[vc_row]`/`_wpb_vc_js_status`, `templatera`, `vc_grid_item`, `wpb_js_templates` option | Gravity Form element (shortcode directly in a column), Text Block, any element, base64-encoded Raw HTML, third-party elements mentioning Gravity Forms |
| Widgets | `sidebars_widgets` and `widget_*` options, including inactive widgets | Gravity Forms widget (`gform_widget`), block widgets, Text, Custom HTML, shortcodes in any widget |

## Partially supported

| Integration | What works | Limits |
| --- | --- | --- |
| Divi 4 | Shortcodes inside any `et_pb_*` module (attributed to the module), Divi Library and Theme Builder layouts | Divi has no core Gravity Forms module; third-party modules are recognised only by name |
| Divi 5 | Block-style layouts (`<!-- wp:divi/... -->`) parsed with `parse_blocks()` | Divi 5's format is new; module-specific attributes are not interpreted beyond shortcodes and Gravity Forms block markup |
| Beaver Builder, Bricks, Oxygen, SiteOrigin, Themify, Cornerstone | Shortcodes anywhere in their stored post meta; elements whose type mentions Gravity Forms with a form ID setting | Native elements of these builders that embed a form under a different name are not recognised |
| Custom fields (ACF and others) | Shortcodes in any post meta value | Fields stored outside post meta (options pages, term meta, user meta) are not scanned |
| Theme files | Active theme and parent: `gravity_form( 5 )`, `GFForms::get_form( 5 )`, `RGForms::get_form()`, `GFFormDisplay::get_form()` with literal arguments, shortcodes in PHP strings and inline HTML, block markup in `.html` templates, parts and PHP patterns | Calls with variables are listed as unresolved; files over 1 MB and beyond 3,000 files are skipped |

## Not detectable through static scanning

- Forms rendered by **other plugins' PHP code** (plugin files are not scanned).
- Form IDs computed at runtime: `gravity_form( get_option( 'x' ) )`, `[gravityform id="{var}"]`
  (reported as **unresolved**, not counted).
- Forms injected with **JavaScript**, iframes or remote services.
- Popup or modal plugins that store their content in custom tables or encoded formats.
- Content stored outside posts, post meta, widgets and theme files: options pages, term meta,
  user meta, custom tables, remote content.
- Which **pages display a global template** (inherited usage). Templates are reported once,
  as template-level usages; page-by-page inheritance is not calculated.

## Adding a detector

Implement `Brigade\GravityFormLocator\Detectors\Detector` (or extend `Abstract_Detector`) and
register it:

```php
add_filter( 'bgfl_detectors', function ( $detectors ) {
	$detectors[] = new My_Builder_Detector();
	return $detectors;
} );
```

`detect()` receives a `Scan_Item` (post, widget, theme file or builder option) and returns
`Usage` objects (`Usage::make( $form_id_or_title, $method, $source, $confidence, $builder )`).
If the builder keeps the real layout outside `post_content` but writes a rendered copy there,
return `true` from `claims_content()` so the copy is not reported a second time.

Other filters: `bgfl_scan_post_types`, `bgfl_theme_file_roots`, `bgfl_capability`.
Action: `bgfl_scan_completed` (receives the scan summary).
