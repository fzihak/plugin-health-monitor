=== Health Radar ===
Contributors: fzihak
Tags: plugin health monitor, plugin conflicts, performance audit, debug log analyzer, WordPress security, plugin management, site optimization
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

**WordPress Plugin Health Monitor & Conflict Detector** — Audit plugin conflicts, detect performance issues, verify PHP compatibility, and analyze debug errors from your WordPress admin dashboard.

== Description ==

**Health Radar** is a comprehensive WordPress plugin auditing and health monitoring tool for site administrators. Get a clear, real-time picture of their site's plugin ecosystem—conflicts, performance bottlenecks, compatibility issues, and errors—without guessing or digging through logs.

Stop managing plugins blindly. Stop performance issues before they impact your users. Stop compatibility problems from becoming critical failures.

**Core Features:**

* **🔍 Plugin Conflict Detector** — Instantly detects duplicate script and style handles, identifies hook collisions between active plugins, and highlights which plugins are fighting for the same resources.

* **⚡ Performance Insight Panel** — Measures total enqueued assets, calculates JS/CSS payload, counts database queries (when SAVEQUERIES enabled), audits wp_options autoload bloat, and generates a 0–100 health score with actionable recommendations.

* **🐘 PHP Compatibility Checker** — Validates each plugin's Requires PHP header against your server version, scans for deprecated WordPress function usage, and flags incompatible plugins before they break your site.

* **📋 Debug Log Analyzer** — Intelligently parses wp-content/debug.log, categorizes errors as fatal, warnings, or notices, attributes errors to specific plugins via stack trace analysis, and shows trends over time.

* **🧬 Duplicate Asset Detector** — Uses file fingerprinting (MD5) to identify identical JavaScript and CSS libraries loaded by multiple plugins (detects jQuery, Lodash, Moment.js, Chart.js duplicates, and more).

* **📄 Health Report Generator** — Compiles a full site health snapshot into a single printable page; export as JSON for automation, or generate PDF from browser print dialog.

**WP-CLI Integration:**

Automate plugin health audits and reporting via command line:

* `wp healthmonitor scan` — Trigger a full plugin health scan and display results.
* `wp healthmonitor score` — Get your current health score (0–100) instantly.
* `wp healthmonitor conflicts` — List all detected plugin conflicts and collisions.
* `wp healthmonitor report` — Generate a complete health report in table format.
* `wp healthmonitor report --format=json` — Export full report as JSON for programmatic access and dashboards.
* `wp healthmonitor log --last=50` — Display recent debug log entries (customizable number).

Perfect for scheduled audits, CI/CD integration, and headless WordPress monitoring.

**Who Should Use Health Radar?**

* **WordPress Site Administrators** — Take control of your plugin ecosystem.
* **Web Hosting Providers** — Offer health audits to your clients as a value-add service.
* **WordPress Developers & Agencies** — Audit client sites, debug complex plugin interactions, optimize performance.
* **WP-CLI Power Users** — Integrate plugin audits into automation workflows and monitoring scripts.

**Security & Privacy First:**

This plugin operates with zero external dependencies and complete data privacy:

* ✅ **No external HTTP requests** — All analysis happens locally on your server.
* ✅ **No telemetry or tracking** — We don't collect usage data.
* ✅ **No data transmission** — Your plugin list, errors, and reports never leave your site.
* ✅ **WPCS & Security Compliance** — Follows WordPress Coding Standards and best practices.
* ✅ **WP_Filesystem API** — Uses WordPress's secure file operations, never direct fopen/fread.
* ✅ **Nonce-protected AJAX** — All admin actions verified with WordPress security tokens.
* ✅ **Capability Checks** — Only administrators (manage_options) can run scans or view reports.

== Installation ==

**Option 1: From WordPress Dashboard (Recommended)**

1. Go to **Plugins → Add New Plugin**
2. Search for "**Health Radar**"
3. Click **Install Now**, then **Activate**
4. Navigate to **Health Radar** in the WordPress admin sidebar
5. Click **Run Full Scan** to generate your first health report

**Option 2: Manual Upload**

1. Download the latest version from the [WordPress.org plugin directory](https://wordpress.org/plugins/health-radar/)
2. Extract the `health-radar` folder
3. Upload to `/wp-content/plugins/` via SFTP or file manager
4. Activate from the **Plugins** page in WordPress

**Option 3: Via WP-CLI**

```bash
wp plugin install health-radar --activate
```

After activation, navigate to **Health Radar** in your WordPress dashboard. All modules are ready to use immediately.

== Frequently Asked Questions ==

= How do I run a plugin health scan? =

From the WordPress admin dashboard, navigate to **Health Radar** in the sidebar and click **Run Full Scan**. Alternatively, use WP-CLI: `wp healthmonitor scan`

= Will Health Radar slow down my site? =

No. All scans run only when you manually trigger them—never on page load or in the background. Scan results are cached for 1 hour to minimize database queries.

= What happens if I have a plugin conflict? =

Health Radar detects and reports conflicts but does **not** automatically fix or disable plugins. You decide which plugin to keep or remove based on the report.

= Can I export my health report? =

Yes, three ways:
1. **PDF** — Use your browser's print dialog to save as PDF directly from the report page
2. **JSON** — Export for programmatic access: `wp healthmonitor report --format=json`
3. **Plain text** — View raw data on the report generation page

= Does Health Radar hook into my live site or make external calls? =

No. Health Radar is 100% self-contained and never makes external HTTP requests. All analysis happens locally on your servers.

= What plugins are scanned? =

Health Radar scans all active plugins on your site. It reads plugin headers, analyzes enqueued scripts/styles, inspects debug.log, checks plugin files for deprecated functions, and monitors database queries.

= Can I schedule automated health scans? =

Yes, with WP-CLI and a cron job. Example: `0 2 * * * cd /var/www/html && wp healthmonitor scan >> /tmp/health-radar.log`

= Is my debug.log data safe? =

Yes. Health Radar reads your debug.log but never transmits it anywhere. All error analysis happens on your server. The plugin respects your file permissions and uses the secure WP_Filesystem API.

= What PHP version do I need? =

PHP 8.1 or higher. Health Radar is built for modern WordPress and PHP standards.

= Can I use Health Radar on production sites? =

Absolutely. Health Radar performs read-only analysis and never modifies plugin files, settings, or database values. It's safe for production use.

== System Requirements ==

* **WordPress:** 6.3 or newer
* **PHP:** 8.1 or newer (object-oriented PHP with type hints)
* **Database:** MySQL 5.7+ or MariaDB 10.2+
* **Recommended:** WP_DEBUG enabled during audits for maximum visibility

== Performance & Compatibility ==

**Performance Impact:**
* Zero performance impact on front-end
* Admin dashboard scans: 1–3 seconds (depending on plugin count)
* Results cached for 1 hour
* No background processes or cron jobs

**Tested Compatibility:**
* ✅ WordPress 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9+
* ✅ PHP 8.1, 8.2, 8.3, 8.4
* ✅ All major hosting providers (Kinsta, WP Engine, Bluehost, SiteGround, etc.)
* ✅ Multisite WordPress installations
* ✅ WP-CLI compatible

== Screenshots ==

1. **Dashboard** — Real-time health score gauge (0–100), visual module summary cards, quick-access navigation to all audit modules, and health status indicators.

2. **Plugin Conflicts Detector** — Visual display of duplicate script/style handles, hook collision matrix, affected plugins list, and actionable recommendations for conflict resolution.

3. **Performance Analytics Panel** — Asset count breakdown, CSS and JavaScript payload estimation, database query counter, autoloaded options bloat report, and weighted health scoring metrics.

4. **PHP Compatibility Audit** — Per-plugin PHP version requirements vs. active server version, incompatibility warnings, deprecated WordPress function scanner, and migration recommendations.

5. **Debug Log Analyzer** — Real-time error parsing with fatal/warning/notice categorization, stack trace attribution to specific plugins, error frequency trending, and quick links to problematic plugins.

6. **Health Report Generator** — Comprehensive single-page audit report with all module data, Export to JSON for programmatic use, print-to-PDF via browser, and shareable HTML snapshots.

== Changelog ==

= 1.0.0 (March 19, 2026) – Review & Security Update =
* **WordPress.org Review Compliance:**
  - Removed inline JavaScript redirect; replaced with secure admin link/button
  - Eliminated direct core plugin loader includes  
  - Refactored path/directory resolution to use WordPress helper functions
  - Centralized debug log path handling via resolver methods
  - Updated autoload-size SQL queries with PHPCS-compliant prepared placeholders
* **Initial Feature Release:**
  - 🔍 Plugin Conflict Detector — dual-detection for script/style handles and hook collisions
  - ⚡ Performance Insight Panel — multi-dimensional health scoring (0–100 scale)
  - 🐘 PHP Compatibility Checker — active plugin validation and deprecated function scanner
  - 📋 Debug Log Analyzer — intelligent error attribution and severity categorization
  - 🧬 Duplicate Asset Detector — MD5-based library fingerprinting
  - 📄 Health Report Generator — multi-format export (PDF, JSON, HTML)
  - 🖥️ Full WP-CLI integration with seven commands
  - 🎨 Custom dashboard UI with radar-themed icon
  - ✅ WordPress Plugin Check: PASS

== Upgrade Notice ==

= 1.0.0 =
Initial release with full WordPress.org compliance. Audits plugin health across six dimensions. No breaking changes (first release).
