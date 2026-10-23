# Brigade Gravity Form Locator

WordPress plugin that scans a site and shows where every Gravity Forms form is used.
User-facing documentation is in [`readme.txt`](readme.txt); the detection support matrix and
extension API are in [`docs/INTEGRATIONS.md`](docs/INTEGRATIONS.md).

## Layout

```
brigade-gravity-form-locator.php   Plugin header and bootstrap
uninstall.php                      Removes the plugin's table, options and cron events
includes/
  class-plugin.php                 Wires components (admin code loads only in wp-admin)
  class-scanner.php                Batched, resumable scan engine with an atomic step lock
  class-results-repository.php     Usage table; scans commit atomically by scan ID
  class-parser.php                 Shortcode, block and nested-data parsing helpers
  class-forms.php                  GFAPI access and form reference resolution
  detectors/                       One detector per builder / data source
  admin/                           WP_List_Table views
admin/views, admin/css, admin/js   Dashboard and settings UI (Donix design tokens)
tests/                             Detector and scanner tests (not shipped)
bin/build-zip.sh                   Builds the installable ZIP
```

## Development

Coding standards (WordPress Coding Standards 3):

```sh
phpcs            # uses phpcs.xml.dist
phpcs --standard=PHPCompatibilityWP --runtime-set testVersion 7.4- --ignore=tests/fixtures .
```

Tests run inside a **disposable** WordPress site with the plugin active. They create and
delete content and refuse to run on a site that already contains Gravity Forms embeds.
A minimal `GFAPI` stub (`tests/fixtures/gfapi-stub.php`) stands in for Gravity Forms.

```sh
wp eval-file wp-content/plugins/brigade-gravity-form-locator/tests/run.php
```

Build the ZIP:

```sh
bin/build-zip.sh [output-dir]
```

WordPress.org banner, icon and screenshot files live in `.wordpress-org/` and belong in the
SVN `assets/` directory, not in the plugin ZIP.
