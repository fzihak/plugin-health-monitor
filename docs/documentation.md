<div align="center">

<br>

# Health Radar — Documentation

**Version 1.0.0 · WordPress 6.3+ · PHP 8.1+**

</div>

---

## Table of Contents

1. [Overview](#1-overview)
2. [Installation](#2-installation)
3. [Getting Started](#3-getting-started)
4. [Admin Pages](#4-admin-pages)
   - [Dashboard](#41-dashboard)
   - [Conflicts](#42-conflicts)
   - [Performance](#43-performance)
   - [PHP Compatibility](#44-php-compatibility)
   - [Debug Log](#45-debug-log)
   - [Generate Report](#46-generate-report)
5. [How the Health Score Works](#5-how-the-health-score-works)
6. [WP-CLI Reference](#6-wp-cli-reference)
7. [Caching](#7-caching)
8. [Security](#8-security)
9. [Privacy](#9-privacy)
10. [Frequently Asked Questions](#10-frequently-asked-questions)
11. [Developer Reference](#11-developer-reference)

---

## 1. Overview

Health Radar is a diagnostic plugin for WordPress site administrators and developers. It audits your installed plugins across six dimensions and surfaces problems before they reach end users.

**What it detects:**

- Scripts and styles registered under different handles but pointing to the same source file
- WordPress hook callbacks shared by two or more plugins that can cause order-of-execution conflicts
- Excessive enqueued assets, high database query counts, and oversized autoloaded options
- Plugins whose `Requires PHP` header is higher than the server's active PHP version
- Usage of deprecated WordPress core functions inside plugin files
- Identical JS/CSS files loaded multiple times under different handles (including CDN-vs-local conflicts)
- Fatal errors, warnings, and notices in `wp-content/debug.log`, attributed to the plugin that caused them

**What it does NOT do:**

- It does not modify any plugin files, settings, or hooks
- It does not fix conflicts automatically
- It does not make any external HTTP requests
- It does not run any scan automatically on page load

---

## 2. Installation

### From the WordPress Dashboard

1. Go to **Plugins → Add New Plugin**
2. Search for `Health Radar`
3. Click **Install Now**, then **Activate**

### Manual Upload

1. Download `health-radar.zip` from the [Releases page](https://github.com/fzihak/plugin-health-monitor/releases)
2. Go to **Plugins → Add New Plugin → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Via WP-CLI

```bash
wp plugin install health-radar --activate
```

### Server Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.3 |
| PHP | 8.1 |
| MySQL / MariaDB | Standard WordPress requirement |
| `wp-content/` | Write permission (for debug.log reading) |

---

## 3. Getting Started

After activation:

1. A **Health Radar** menu item appears in the WordPress admin sidebar
2. Navigate to **Health Radar → Dashboard**
3. Click **Run Full Scan** to generate your first health report
4. Review your Health Score and navigate to each module for detailed findings

> **Note:** Scan results are cached for 1 hour. Use the **Re-scan** button on any page to force a fresh scan.

---

## 4. Admin Pages

### 4.1 Dashboard

**URL:** `/wp-admin/admin.php?page=wphm-dashboard`

The Dashboard provides a high-level overview of your site's plugin health.

**What it shows:**

- **Health Score gauge** — A 0–100 composite score calculated from four weighted factors
- **Score breakdown** — Individual scores for plugin count, assets, DB queries, and autoload size
- **Module summary cards** — Quick status for Conflicts, Performance, PHP Compatibility, and Debug Log
- **Run Full Scan button** — Triggers a fresh scan across all modules

**Score color coding:**

| Score | Color | Meaning |
|---|---|---|
| 80–100 | Green | Healthy |
| 50–79 | Yellow | Needs attention |
| 0–49 | Red | Critical issues |

---

### 4.2 Conflicts

**URL:** `/wp-admin/admin.php?page=wphm-conflicts`

The Conflicts page runs `WPHM_Plugin_Scanner` to detect two types of conflicts:

#### Duplicate Asset Conflicts

Scans `$wp_scripts` and `$wp_styles` globals for:
- Same `src` URL registered under different handles
- Each conflict shows: conflicting handles, file type (script/style), and the plugins that registered them

#### Hook Conflicts

Inspects `$wp_filter` for:
- Two or more plugins attaching callbacks to the same action or filter hook
- Shows: hook name, priority, callback function names, and originating plugin files

**Severity levels:**

| Level | Meaning |
|---|---|
| High | Same file loaded twice — definite performance impact |
| Medium | Multiple callbacks on same hook — potential behavior conflict |
| Low | Informational — worth reviewing |

---

### 4.3 Performance

**URL:** `/wp-admin/admin.php?page=wphm-performance`

Powered by `WPHM_Health_Scorer`, this page measures four performance dimensions:

#### Enqueued Assets
- Total count of enqueued JavaScript and CSS files
- Estimated payload size calculated from local file sizes on disk

#### Database Queries
- Total DB query count for the current page load
- Requires `SAVEQUERIES` to be `true` in `wp-config.php`
- If `SAVEQUERIES` is not enabled, the plugin displays a setup guide

To enable:
```php
// Add to wp-config.php
define( 'SAVEQUERIES', true );
```

> **Important:** Only enable `SAVEQUERIES` on development/staging environments. It increases memory usage and should not be used in production.

#### Autoloaded Options
- Queries `wp_options` for all rows where `autoload = 'yes'`
- Calculates total byte size of autoloaded data
- High autoload size (over 1 MB) increases every page load time

#### Performance Score Table

| Metric | Excellent | Moderate | Poor |
|---|---|---|---|
| Plugin count | ≤ 10 (30 pts) | 11–20 (20 pts) | 21+ (10 pts) |
| Asset count | ≤ 15 (30 pts) | 16–30 (20 pts) | 31+ (10 pts) |
| DB queries | ≤ 30 (20 pts) | 31–60 (10 pts) | 61+ (5 pts) |
| Autoload size | < 1 MB (20 pts) | 1–3 MB (10 pts) | > 3 MB (5 pts) |

---

### 4.4 PHP Compatibility

**URL:** `/wp-admin/admin.php?page=wphm-php-compat`

Powered by `WPHM_PHP_Checker`.

#### Version Compatibility Check

For each installed plugin:
1. Reads the `Requires PHP` header from `get_plugins()` data
2. Falls back to parsing the plugin's `readme.txt` for a `Requires PHP:` line
3. Compares the requirement against `PHP_VERSION`
4. Flags incompatible plugins with the required vs. current PHP version

#### Deprecated Function Scanner

Scans each plugin's PHP files for usage of deprecated WordPress core functions. Covers deprecations from WordPress 5.3 through 6.5.

**Detected deprecated functions include:**

| Function | Deprecated in |
|---|---|
| `wp_blacklist_check()` | WP 5.5 |
| `wp_no_robots()` | WP 6.1 |
| `get_page()` | WP 6.2 |
| `wp_get_loading_attr_default()` | WP 6.3 |
| `_wp_get_current_user()` | WP 6.4 |
| `the_block_template_skip_link()` | WP 6.4 |
| `_register_remote_theme_patterns()` | WP 6.5 |
| _(and more)_ | |

**Scanner limits (for performance):**
- Max 500 PHP files scanned per plugin
- Max 256 KB per file

---

### 4.5 Debug Log

**URL:** `/wp-admin/admin.php?page=wphm-debug-log`

Powered by `WPHM_Debug_Log_Reader`.

To enable WordPress debug logging, add these to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

#### What It Shows

- **Log file status** — Whether `wp-content/debug.log` exists and its file size
- **Error summary** — Counts of Fatal errors, Warnings, and Notices
- **Top offending plugins** — Up to 5 plugins responsible for the most errors, identified via stack trace path matching
- **Recent entries** — Last 50 log entries with timestamp, type, message, and originating file

#### Limits

| Setting | Value |
|---|---|
| Max bytes read from log | 1 MB (reads from tail of file) |
| Recent entries shown | 50 |
| Max entries retrievable | 200 |

---

### 4.6 Generate Report

**URL:** `/wp-admin/admin.php?page=wphm-report`

Powered by `WPHM_Report_Generator`.

Aggregates all module results into a single-page report containing:
- Site URL, WordPress version, PHP version, report timestamp
- Health Score with breakdown
- Conflict summary
- Duplicate asset summary
- PHP compatibility summary
- Debug log summary

**Export options:**

| Format | How |
|---|---|
| **JSON** | Click **Download JSON** in the admin, or `wp healthmonitor report --format=json` |
| **Plain Text** | Click **Download TXT** in the admin — line-delimited plain text, no HTML |
| **PDF** | Click **Print Report** (Ctrl+P / Cmd+P) — browser native print dialog |

---

## 5. How the Health Score Works

The Health Score is a composite 0–100 value calculated by `WPHM_Health_Scorer::get_score()`.

```
Health Score = Plugin Score + Asset Score + DB Score + Autoload Score
```

| Dimension | Max | Thresholds |
|---|---|---|
| Plugin count | 30 | ≤10 → 30 pts · 11–20 → 20 pts · 21+ → 10 pts |
| Enqueued assets | 30 | ≤15 → 30 pts · 16–30 → 20 pts · 31+ → 10 pts |
| Database queries | 20 | ≤30 → 20 pts · 31–60 → 10 pts · 61+ → 5 pts |
| Autoloaded options | 20 | <1 MB → 20 pts · 1–3 MB → 10 pts · >3 MB → 5 pts |

**Important:** DB query scoring requires `SAVEQUERIES` to be enabled. If it is not enabled, the DB dimension returns its minimum (5 pts) to avoid a misleading perfect score.

---

## 6. WP-CLI Reference

All WP-CLI commands use the `healthmonitor` command group.

### `wp healthmonitor scan`

Runs a full health scan across all six modules. Outputs a summary to the terminal.

```bash
wp healthmonitor scan
```

**Example output:**
```
Health Score: 74/100
Conflicts found: 2
Duplicate assets: 1
PHP compat issues: 0
Debug log entries: 15 (3 fatal, 8 warning, 4 notice)
```

---

### `wp healthmonitor score`

Displays only the Health Score and its breakdown.

```bash
wp healthmonitor score
```

---

### `wp healthmonitor conflicts`

Lists all detected plugin conflicts (duplicate assets and hook collisions).

```bash
wp healthmonitor conflicts
```

---

### `wp healthmonitor report`

Generates the full health report and outputs a human-readable summary.

```bash
wp healthmonitor report
```

**Output as JSON** (for pipelines and automation):

```bash
wp healthmonitor report --format=json
```

The JSON output includes all raw data from all modules. Pipe it to `jq` for filtering:

```bash
wp healthmonitor report --format=json | jq '.health_score.total'
wp healthmonitor report --format=json | jq '.conflicts.duplicate_assets'
```

---

### `wp healthmonitor log`

Displays recent debug log entries.

```bash
# Show last 50 entries (default)
wp healthmonitor log

# Show last N entries (max 200)
wp healthmonitor log --last=20
```

---

## 7. Caching

All scan results are cached using WordPress transients to avoid re-running expensive scans on every page load.

| Transient Key | Module | TTL |
|---|---|---|
| `wphm_health_score` | Health Scorer | 1 hour |
| `wphm_conflict_results` | Plugin Scanner | 1 hour |
| `wphm_performance_results` | Performance | 1 hour |
| `wphm_php_compat_results` | PHP Checker | 1 hour |
| `wphm_debug_log_results` | Debug Log Reader | 1 hour |
| `wphm_duplicate_asset_results` | Asset Analyzer | 1 hour |
| `wphm_last_report` | Report Generator | 1 hour |

**Force a fresh scan:**
- In the admin: click the **Re-scan** button on any module page
- Via WP-CLI: all commands automatically bypass the cache and run fresh scans
- On plugin deactivation: all transients are deleted automatically

---

## 8. Security

The plugin follows WordPress security best practices throughout.

| Security Measure | Implementation |
|---|---|
| File reads | `WP_Filesystem` — no direct `fopen`/`fread` calls anywhere |
| AJAX authentication | `check_ajax_referer()` on all AJAX handlers |
| Capability checks | `current_user_can('manage_options')` on all admin pages and AJAX |
| Output escaping | `esc_html()`, `esc_attr()`, `esc_url()` on all output |
| Database queries | `$wpdb->prepare()` on all raw SQL |
| File path validation | `realpath()` used before any file read to prevent path traversal |
| Direct file access | `if ( ! defined('ABSPATH') ) exit;` in every PHP file |
| External requests | None — zero outbound HTTP requests anywhere in the codebase |
| Automated audit | Automated Plugin Check scan: ✅ PASS (March 2026) |

---

## 9. Privacy

Health Radar does not collect, transmit, or store any data outside of the WordPress site it is installed on.

- No telemetry
- No usage tracking
- No external API calls
- No data is sent to the plugin author or any third party
- All scan data is stored locally in WordPress transients and deleted on deactivation

---

## 10. Frequently Asked Questions

**Does this plugin slow down my site?**

No. All scans are triggered manually. No scan runs automatically on page load. Results are cached for 1 hour.

**Does it fix conflicts automatically?**

No. The plugin detects and reports issues only. It does not modify plugin files, settings, or hook priorities.

**The DB query count shows 0. Why?**

The query counter only works when `SAVEQUERIES` is set to `true` in `wp-config.php`. The plugin never enables this on its own. See [Performance](#43-performance) for setup instructions.

**The Debug Log page says "No log file found." Why?**

WordPress debug logging must be enabled in `wp-config.php`. See [Debug Log](#45-debug-log) for the required constants.

**How does the plugin attribute errors to specific plugins?**

The Debug Log Analyzer reads the file path from each stack trace entry in `debug.log` and matches it against known plugin directory names under `wp-content/plugins/`.

**Is this plugin safe to use on a live production site?**

Yes. It only reads data — it never modifies anything. However, enabling `SAVEQUERIES` for DB query counting should only be done on staging environments.

**Can I run it from the command line in a CI/CD pipeline?**

Yes. Use `wp healthmonitor report --format=json` to get machine-readable output suitable for automated pipelines or monitoring scripts.

---

## 11. Developer Reference

### Plugin Constants

| Constant | Value | Description |
|---|---|---|
| `WPHM_VERSION` | `1.0.0` | Plugin version |
| `WPHM_PLUGIN_DIR` | Path with trailing slash | Absolute path to plugin directory |
| `WPHM_PLUGIN_URL` | URL with trailing slash | URL to plugin directory |
| `WPHM_PLUGIN_BASENAME` | `health-radar/health-radar.php` | Plugin basename |
| `WPHM_MIN_PHP` | `8.1` | Minimum required PHP version |

### Class Overview

| Class | File | Responsibility |
|---|---|---|
| `WPHM_Health_Scorer` | `includes/class-health-scorer.php` | Calculates 0–100 Health Score |
| `WPHM_Plugin_Scanner` | `includes/class-plugin-scanner.php` | Detects script/style and hook conflicts |
| `WPHM_Asset_Analyzer` | `includes/class-asset-analyzer.php` | Fingerprints assets, detects duplicates |
| `WPHM_PHP_Checker` | `includes/class-php-checker.php` | Checks PHP version compatibility and deprecated functions |
| `WPHM_Debug_Log_Reader` | `includes/class-debug-log-reader.php` | Parses and attributes debug.log entries |
| `WPHM_Report_Generator` | `includes/class-report-generator.php` | Aggregates all module data into a report |
| `WPHM_Admin_Menu` | `admin/class-admin-menu.php` | Registers admin pages, assets, and AJAX handlers |
| `WPHM_CLI_Commands` | `cli/class-cli-commands.php` | WP-CLI command implementations |

### Hook Reference

| Hook | Type | When |
|---|---|---|
| `plugins_loaded` | Action | Plugin initialization (`wphm_init`) |
| `plugins_loaded` | Action | WP-CLI command registration |
| `init` | Action | Text domain loading |
| `register_activation_hook` | — | PHP version check, option setup |
| `register_deactivation_hook` | — | Transient cleanup |

### Text Domain

```
health-radar
```

Translation files go in the `languages/` directory. Follows standard WordPress i18n conventions using `__()`, `esc_html__()`, and `_e()`.

---

<div align="center">

**Health Radar v1.0.0**

[GitHub](https://github.com/fzihak/plugin-health-monitor) · [WordPress.org](https://wordpress.org/plugins/health-radar/) · Built by [Foysal Zihak](https://github.com/fzihak)

</div>
