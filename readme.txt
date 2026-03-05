=== Health Radar ===
Contributors: fzihak
Tags: plugins, health, conflicts, performance, debug
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Pinpoint plugin conflicts, performance issues, PHP compat problems, and debug log errors — all from one WordPress admin dashboard.

== Description ==

Health Radar gives WordPress administrators a clear picture of their site's plugin health. Stop guessing which plugin is causing problems — get actionable insights from your own dashboard.

**Features:**

* **Plugin Conflict Detector** — Detects duplicate script/style handles and hook conflicts between active plugins.
* **Performance Insight Panel** — Shows enqueued assets, database query counts, and autoloaded option sizes with a 0–100 health score.
* **PHP Compatibility Checker** — Verifies each plugin's PHP version requirements against your server and scans for deprecated WordPress function usage.
* **Debug Log Analyzer** — Parses your debug.log for fatal errors, warnings, and notices, and attributes them to specific plugins.
* **Duplicate Asset Detector** — Fingerprints JS/CSS files to find identical libraries loaded by multiple plugins.
* **Health Report Generator** — Compiles all module data into a single printable report.

**WP-CLI Support:**

* `wp healthmonitor scan` — Run a full health scan.
* `wp healthmonitor report` — Generate a full report.
* `wp healthmonitor report --format=json` — JSON output.
* `wp healthmonitor conflicts` — Show detected conflicts.
* `wp healthmonitor score` — Display the health score.
* `wp healthmonitor log --last=<number>` — Show recent debug log entries.

**Privacy:**

This plugin makes no external HTTP requests. All data stays on your server. No telemetry, no tracking, no data collection of any kind.

== Installation ==

1. Upload the `wp-plugin-health-monitor` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Health Radar** in the admin sidebar.
4. Click **Run Full Scan** to generate your first health report.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. All scans run only when you manually trigger them. No scans run on page load. Results are cached for 1 hour.

= Does this plugin fix conflicts automatically? =

No. This plugin detects and reports issues. It does not modify any plugin files or settings.

= Does this plugin make external requests? =

No. There are zero external HTTP requests anywhere in the codebase.

= What PHP version is required? =

PHP 8.1 or higher.

= Does the database query counter work automatically? =

The DB query count only works when `SAVEQUERIES` is already defined as `true` in your `wp-config.php`. The plugin never enables this on its own.

== Screenshots ==

1. Dashboard with health score gauge and module summary cards.
2. Plugin Conflicts page showing duplicate script handles and hook collisions.
3. Performance Insights panel with asset count, DB query count, and autoload size.
4. PHP Compatibility checker with per-plugin PHP version requirements.
5. Debug Log Analyzer with errors grouped by plugin and severity.
6. Health Report Generator — full single-page printable report.

== Changelog ==

= 1.0.0 =
* Initial release.
* Plugin Conflict Detector module.
* Performance Insight Panel module.
* PHP Compatibility Checker module.
* Debug Log Analyzer module.
* Duplicate Asset Detector module.
* Health Report Generator module.
* WP-CLI command support.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
