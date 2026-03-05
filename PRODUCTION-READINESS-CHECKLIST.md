# Health Radar — Production Readiness Checklist (v1)

Use this checklist before each public release.

## 1) Security & Hardening

- [ ] **Nonce coverage complete** for all AJAX actions (`check_ajax_referer` present and validated).
- [ ] **Capability checks enforced** on all admin pages and AJAX handlers (`current_user_can( 'manage_options' )`).
- [ ] **Input sanitization complete** (`sanitize_text_field`, `sanitize_key`, `absint`, `wp_unslash` as needed).
- [ ] **Output escaping complete** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` where needed).
- [ ] **Direct file access blocked** (`if ( ! defined( 'ABSPATH' ) ) exit;`) in all PHP files.
- [ ] **No unsafe file operations** without path validation.
- [ ] **No sensitive data leakage** in admin UI, JSON output, or debug views.
- [ ] **PHPCS (WordPress ruleset) clean** for touched files.

## 2) Performance

- [ ] **Assets loaded only on plugin pages** (admin CSS/JS scope validated).
- [ ] **No duplicate heavy queries** in same request.
- [ ] **Transients used for scan/report caching** and expiry reviewed.
- [ ] **Debug parsing bounded** (large `debug.log` handling tested).
- [ ] **No unbounded loops** over plugin/file lists in admin request path.
- [ ] **No blocking scan on normal page load** (manual trigger only).

## 3) Stability & Error Handling

- [ ] **Graceful empty-state handling** for each module page.
- [ ] **Graceful failure messages** for AJAX/network/server failures.
- [ ] **Missing file states handled** (`debug.log` absent, permissions denied, etc.).
- [ ] **Backward compatibility** verified for minimum WP/PHP versions in readme.
- [ ] **Activation/deactivation hooks safe** and idempotent.

## 4) UX / Premium Quality Bar

- [ ] **Visual consistency across all pages** (Dashboard, Conflicts, Performance, PHP Compat, Debug Log, Report).
- [ ] **Spacing/typography hierarchy consistent** at desktop + tablet + mobile breakpoints.
- [ ] **Button/loading/empty/error states visually consistent**.
- [ ] **Color contrast accessible** (especially badges/alerts/status chips).
- [ ] **No layout break** at 782px and below.

## 5) Internationalization (i18n)

- [ ] **User-visible strings wrapped** in translation functions (`__`, `esc_html__`, etc.).
- [ ] **Text domain consistent**: `wp-plugin-health-monitor`.
- [ ] **Translator comments added** where placeholders are used.

## 6) Data Integrity & Reporting Accuracy

- [ ] **Health score math verified** with deterministic fixtures.
- [ ] **Conflict detection output validated** against controlled plugin set.
- [ ] **PHP compatibility parsing validated** for `Requires PHP` variants.
- [ ] **Report output validated** for JSON/text consistency.

## 7) QA Matrix

- [ ] Manual matrix run completed: `QA-MANUAL-MATRIX.md`
- [ ] **WordPress versions tested**: minimum supported + latest stable.
- [ ] **PHP versions tested**: 8.1, 8.2, 8.3.
- [ ] **Single site + multisite smoke tests** completed.
- [ ] **Fresh install + upgrade path test** completed.
- [ ] **Common plugin ecosystem smoke test** (WooCommerce/Elementor/ACF site profile).

## 8) Release Hygiene

- [ ] **Version bump complete** in plugin header and any constants.
- [ ] **`readme.txt` updated** (`Stable tag`, `Tested up to`, `Changelog`, `Upgrade Notice`).
- [ ] **No debug leftovers** (`var_dump`, console noise, temporary flags).
- [ ] **Package contents reviewed** (no dev artifacts, no large unnecessary files).
- [ ] **Rollback plan documented** (how to revert safely).

---

## Current Priority (Suggested)

1. Run a focused **security + escaping audit** on all AJAX render paths in `admin/js/admin-script.js` and PHP handlers.
2. Add a **manual QA pass checklist** for each admin page with screenshots per breakpoint.
3. Verify **asset scope and cache-busting behavior** after style/script changes.
4. Prepare **release candidate checklist run** and only then tag next stable version.

## Hardening Pass Completed (March 2026)

- [x] Admin page asset scope enforcement reviewed and tightened.
- [x] Debug log reader hardened (path validation, readability checks, bounded entry retrieval).
- [x] PHP compatibility scanner hardened (file count/size limits, vendor/test folder skip, deduplicated findings).
- [x] Asset analyzer hardened (safe local-path resolution, hash-size limits, hostname normalization).
- [x] Frontend report/debug rendering hardened with safer numeric normalization and escaping behavior.

## Next Execution Order

1. Run manual QA across all admin pages on at least 2 WordPress installs (small + large plugin sets).
2. Validate performance on large `debug.log` and plugin-heavy environments.
3. Complete PHPCS + security review pass for all modified files.
4. Bump version and publish release candidate only after checklist gates are green.

## Definition of “Production Ready” for this plugin

Treat the plugin as production ready only when:

- All checkboxes above are complete,
- No blocker/critical issues remain from QA,
- One full release candidate cycle passes without regressions.
