# Health Radar — Production Readiness Checklist (v1)

Use this checklist before each public release.

> **QA Status: PASSED — v1.0.0 — March 6, 2026**
> Reviewed by: QA (fzihak) | Plugin submitted to WordPress.org ✓

## 1) Security & Hardening

- [x] **Nonce coverage complete** for all AJAX actions (`check_ajax_referer` present and validated).
  - All 6 AJAX handlers in `class-admin-menu.php` call `check_ajax_referer('wphm_nonce')` as first line.
- [x] **Capability checks enforced** on all admin pages and AJAX handlers (`current_user_can( 'manage_options' )`).
  - Admin menu registration uses `'manage_options'` capability on all `add_submenu_page()` calls.
  - AJAX handlers verify `current_user_can('manage_options')` and die with `-1` on failure.
- [x] **Input sanitization complete** (`sanitize_text_field`, `sanitize_key`, `absint`, `wp_unslash` as needed).
  - All `$_POST` / `$_GET` values go through sanitization before use.
- [x] **Output escaping complete** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` where needed).
  - Dashboard view: all variables escaped with `esc_html()` / `absint()` / `esc_attr()`.
  - JS report generator: both HTML and plain-text copies use safe string concatenation; no `innerHTML` injection from raw server data.
- [x] **Direct file access blocked** (`if ( ! defined( 'ABSPATH' ) ) exit;`) in all PHP files.
  - Verified in: `health-radar.php`, all `includes/`, all `admin/`, `cli/`, `uninstall.php`.
- [x] **No unsafe file operations** without path validation.
  - `class-debug-log-reader.php` and `class-php-checker.php` both use `WP_Filesystem` exclusively; path is validated before access.
- [x] **No sensitive data leakage** in admin UI, JSON output, or debug views.
  - Debug log entries are bounded (last 200 entries); raw log path never exposed to client.
- [x] **PHPCS (WordPress ruleset) clean** for touched files.
  - Automated Plugin Check scan: **PASS** (March 5, 2026). All ERRORs resolved; remaining WARNINGs are acknowledged false-positives with inline `phpcs:ignore` annotations.

## 2) Performance

- [x] **Assets loaded only on plugin pages** (admin CSS/JS scope validated).
  - `enqueue_assets()` conditional: `strpos($screen->id, 'wphm') !== false` — assets skipped on all non-plugin pages.
- [x] **No duplicate heavy queries** in same request.
  - `get_autoload_size()` in `class-health-scorer.php` wrapped with `wp_cache_get/set('wphm_autoload_size', 'wphm', HOUR_IN_SECONDS)`.
- [x] **Transients used for scan/report caching** and expiry reviewed.
  - Scan results cached in transients; cache busted on manual rescan.
- [x] **Debug parsing bounded** (large `debug.log` handling tested).
  - `class-debug-log-reader.php`: `get_last_entries()` reads only last 200 lines via `WP_Filesystem` + `array_slice`.
- [x] **No unbounded loops** over plugin/file lists in admin request path.
  - `class-php-checker.php`: max 100 plugin files, max 8 KB per file; vendor/test dirs skipped.
- [x] **No blocking scan on normal page load** (manual trigger only).
  - All heavy scans are triggered via explicit AJAX button click; page load only renders cached/empty state.

## 3) Stability & Error Handling

- [x] **Graceful empty-state handling** for each module page.
  - Each view (`conflicts.php`, `performance.php`, `php-compat.php`, `debug-log.php`, `report.php`) has a "no data yet" notice rendered when scan data is absent.
- [x] **Graceful failure messages** for AJAX/network/server failures.
  - `admin-script.js` catches AJAX `fail()` callback on all requests and renders inline error notice.
- [x] **Missing file states handled** (`debug.log` absent, permissions denied, etc.).
  - `class-debug-log-reader.php` returns structured error array when file is missing or unreadable.
- [x] **Backward compatibility** verified for minimum WP/PHP versions in readme.
  - `Requires at least: 6.3`, `Requires PHP: 8.1` — tested on WP 6.3 + PHP 8.1 LocalWP environment.
- [x] **Activation/deactivation hooks safe** and idempotent.
  - `wphm_activate()` uses `get_option` guard; `wphm_deactivate()` flushes rewrite only. No schema changes on activate.

## 4) UX / Premium Quality Bar

- [x] **Visual consistency across all pages** (Dashboard, Conflicts, Performance, PHP Compat, Debug Log, Report).
  - Header block, card layout, button styles, and badge colors consistent across all 6 views.
- [x] **Spacing/typography hierarchy consistent** at desktop + tablet + mobile breakpoints.
  - Tested at 1440px, 1024px, 782px, 375px. No overflow or collapsed content observed.
- [x] **Button/loading/empty/error states visually consistent**.
  - Spinner `.wphm-spinner` shown on all async actions; button disabled during request; restored on complete.
- [x] **Color contrast accessible** (especially badges/alerts/status chips).
  - Status chips: green/yellow/red on white background — all pass WCAG AA 4.5:1 ratio.
- [x] **No layout break** at 782px and below.
  - WordPress admin collapses sidebar at this breakpoint; plugin pages reflow to single-column correctly.

## 5) Internationalization (i18n)

- [x] **User-visible strings wrapped** in translation functions (`__`, `esc_html__`, etc.).
  - All PHP string literals in views and class files use `__()` or `esc_html__()`.
- [x] **Text domain consistent**: `health-radar`.
  - Verified via grep: 0 occurrences of old domain `wp-plugin-health-monitor` remaining.
- [x] **Translator comments added** where placeholders are used.
  - `/* translators: %s = plugin name */` comments present for all `sprintf()` wrapped strings.

## 6) Data Integrity & Reporting Accuracy

- [x] **Health score math verified** with deterministic fixtures.
  - Score dimensions (conflicts, performance, PHP compat, debug log, asset health) each return 0–100; weighted average verified manually with controlled test data.
- [x] **Conflict detection output validated** against controlled plugin set.
  - Tested with 3 plugins registering identical hook slugs — all flagged correctly.
- [x] **PHP compatibility parsing validated** for `Requires PHP` variants.
  - Handles `Requires PHP: 8.0`, `Requires PHP: 7.4`, missing header (treated as compatible), and deprecated function scan.
- [x] **Report output validated** for JSON/text consistency.
  - JSON download: valid parseable JSON confirmed via `JSON.parse()` in devtools. TXT download: line-delimited plain text, no raw HTML.

## 7) QA Matrix

- [x] Manual matrix run completed: `QA-MANUAL-MATRIX.md`
- [x] **WordPress versions tested**: WP 6.3 (minimum) + WP 6.9 (latest).
- [x] **PHP versions tested**: 8.1, 8.2, 8.3.
- [x] **Single site + multisite smoke tests** completed.
  - Single site: full pass. Multisite: plugin admin pages load correctly; network-activate not applicable (single-site plugin).
- [x] **Fresh install + upgrade path test** completed.
  - Fresh install: activation hook runs, options set, no PHP notices.
- [x] **Common plugin ecosystem smoke test** (WooCommerce/Elementor/ACF site profile).
  - Tested alongside WooCommerce 9.x + ACF 6.x — no hook collisions with Health Radar's own hooks.

## 8) Release Hygiene

- [x] **Version bump complete** in plugin header and any constants.
  - `Version: 1.0.0` in `health-radar.php` header; `WPHM_VERSION = '1.0.0'`.
- [x] **`readme.txt` updated** (`Stable tag`, `Tested up to`, `Changelog`, `Upgrade Notice`).
  - `Stable tag: 1.0.0`, `Tested up to: 6.9`, Changelog section complete.
- [x] **No debug leftovers** (`var_dump`, console noise, temporary flags).
  - Grep clean: 0 `var_dump`, 0 `console.log` (only `console.error` in AJAX error paths — intentional).
- [x] **Package contents reviewed** (no dev artifacts, no large unnecessary files).
  - ZIP: 47.0 KB, 20 entries. No `.DS_Store`, no `node_modules`, no test fixtures.
- [x] **Rollback plan documented** (how to revert safely).
  - Rollback: deactivate plugin, delete via WP admin. No DB schema created — no migration needed. Options flushed on uninstall via `uninstall.php`.

---

## Current Priority (Suggested)

~~1. Run a focused **security + escaping audit** on all AJAX render paths in `admin/js/admin-script.js` and PHP handlers.~~
~~2. Add a **manual QA pass checklist** for each admin page with screenshots per breakpoint.~~
~~3. Verify **asset scope and cache-busting behavior** after style/script changes.~~
~~4. Prepare **release candidate checklist run** and only then tag next stable version.~~

> ✅ All priorities completed as part of v1.0.0 QA (March 6, 2026).

## Hardening Pass Completed (March 2026)

- [x] Admin page asset scope enforcement reviewed and tightened.
- [x] Debug log reader hardened (path validation, readability checks, bounded entry retrieval).
- [x] PHP compatibility scanner hardened (file count/size limits, vendor/test folder skip, deduplicated findings).
- [x] Asset analyzer hardened (safe local-path resolution, hash-size limits, hostname normalization).
- [x] Frontend report/debug rendering hardened with safer numeric normalization and escaping behavior.
- [x] Custom SVG radar icon replaces dashicons-heart (no external icon dependency).
- [x] Report footer "Generated by Health Radar" verified in both HTML and plain-text output paths.
- [x] Text domain `wp-plugin-health-monitor` fully renamed to `health-radar` — grep confirmed zero legacy references.

## Next Execution Order

~~1. Run manual QA across all admin pages on at least 2 WordPress installs (small + large plugin sets).~~
~~2. Validate performance on large `debug.log` and plugin-heavy environments.~~
~~3. Complete PHPCS + security review pass for all modified files.~~
~~4. Bump version and publish release candidate only after checklist gates are green.~~

> ✅ All execution steps completed — v1.0.0 released and submitted to WordPress.org (March 5–6, 2026).

## Definition of "Production Ready" for this plugin

Treat the plugin as production ready only when:

- All checkboxes above are complete,
- No blocker/critical issues remain from QA,
- One full release candidate cycle passes without regressions.

---

## v1.0.0 Release Sign-off

| Item                          | Status                                      | Date           |
|-------------------------------|---------------------------------------------|----------------|
| All checklist gates green     | ✅ PASS                                     | March 6, 2026  |
| Automated Plugin Check scan   | ✅ PASS                                     | March 5, 2026  |
| Manual QA matrix completed    | ✅ PASS                                     | March 6, 2026  |
| Plugin submitted to WP.org    | ✅ Done                                     | March 5, 2026  |
| Slug assigned                 | ✅ `health-radar`                           | March 6, 2026  |
| GitHub pushed (latest commit) | ✅ `29773a8`                                | March 6, 2026  |
| ZIP artifact                  | ✅ `health-radar.zip` (47.0 KB, 20 entries) | March 6, 2026  |

**Approved by:** fzihak  
**Date:** March 6, 2026
