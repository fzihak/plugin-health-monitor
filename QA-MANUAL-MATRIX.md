# Health Radar — Manual QA Matrix (v1)

Use this matrix before release candidate and final release.

> **QA Run: PASSED — v1.0.0 — March 6, 2026**
> Tester: fzihak | Environment: LocalWP (Windows 11)

## Test Environment

- WordPress: **6.3** (minimum) + **6.9** (latest stable)
- PHP: **8.1**, **8.2**, **8.3**
- Site profiles:
  - **Small site** — 8 active plugins (Classic Editor, Yoast SEO, WP Super Cache, Contact Form 7, Jetpack, WooCommerce, Akismet, Health Radar)
  - **Large site** — 34 active plugins (WooCommerce full stack + Elementor + ACF + various utility plugins)
- Viewports tested:
  - Desktop: 1440px (primary), 1920px
  - Tablet: 1024px, 820px
  - Mobile: 782px, 390px (iPhone 14)

---

## A) Global UI Consistency

- [x] Header spacing and typography consistent across all plugin pages
- [x] Button style/hover/disabled/loading states consistent
- [x] Alert and badge color contrast readable
- [x] Tables do not overflow/break layout unexpectedly
- [x] No visible unstyled content on initial page load
- [x] No layout shift after AJAX result render

Evidence:
- Screenshot(s): All 6 pages captured at 1440px and 782px — consistent header block, card grid, and button styles observed.
- Notes: Minor 1px border-radius inconsistency on Safari 17 (cosmetic only, not blocking).

---

## B) Dashboard (`wphm-dashboard`)

### Visual
- [x] Score ring is centered and readable
- [x] Dimension bars align correctly and animate without glitch
- [x] Module cards align in grid, hover state smooth
- [x] Scan status text remains readable after repeated scans

### Functional
- [x] "Run Full Scan" button triggers request and returns response
- [x] Score + dimension values update correctly
- [x] No JS error in browser console

Evidence:
- Screenshot(s): Score ring renders at 72/100 on small site (8 plugins). Bars animate on scan complete. Module cards 2-column at 782px.
- Notes: On large site (34 plugins), scan takes ~1.8s. Spinner maintained throughout — no premature resolve. Console: clean.

---

## C) Conflicts (`wphm-conflicts`)

### Visual
- [x] Header icon/title/description spacing consistent
- [x] Empty state/info notice renders correctly
- [x] Result table layout remains clean for long plugin names

### Functional
- [x] Scan action returns data correctly
- [x] Severity badges display expected status colors
- [x] Repeated scan does not duplicate broken markup

Evidence:
- Screenshot(s): Small site — "No conflicts detected" empty state shown correctly. Large site — 3 hook collisions detected (jQuery UI + two plugins registering `admin_footer` callback at same priority).
- Notes: Severity badge colors: INFO = blue, WARNING = amber, ERROR = red. All pass WCAG AA contrast. Long plugin names (`woocommerce-gateway-stripe`) wrap cleanly.

---

## D) Performance (`wphm-performance`)

### Visual
- [x] SAVEQUERIES warning notice style is readable and non-breaking
- [x] Top autoloaded options table aligns on all viewports
- [x] Breakdown cards spacing consistent after AJAX refresh

### Functional
- [x] Refresh button updates metrics
- [x] Numeric values are sane and not NaN/undefined
- [x] Page remains responsive on large options table

Evidence:
- Screenshot(s): Autoload size 0.34 MB on small site, 1.82 MB on large site. DB queries: 28 (small), 74 (large). All values render as integers/decimals — no NaN.
- Notes: SAVEQUERIES notice shown only when `SAVEQUERIES` is not defined as `true` in `wp-config.php`. Warning notice styling consistent with other alert blocks.

---

## E) PHP Compatibility (`wphm-php-compat`)

### Visual
- [x] Compatibility table displays correctly for long plugin names
- [x] Deprecated usage subsection is readable
- [x] Badge/status colors are consistent

### Functional
- [x] Check action returns plugin compatibility data
- [x] Incompatible plugins are highlighted correctly
- [x] Deprecated usage rows render safely

Evidence:
- Screenshot(s): 34-plugin test: 2 plugins with `Requires PHP: 7.4` (flagged INCOMPATIBLE vs PHP 8.2 env). 1 deprecated `get_magic_quotes_gpc` usage found. All highlighted in red with clear label.
- Notes: Missing `Requires PHP` header treated as "Compatible" — matches WordPress.org convention. Deprecated function scan runs on first 100 plugin files only (bounded correctly).

---

## F) Debug Log (`wphm-debug-log`)

### Visual
- [x] Status table aligns properly (Enabled/Disabled/Found)
- [x] Setup guide and code block styling readable
- [x] Summary cards, offender chart, and log viewer remain stable on mobile

### Functional
- [x] Missing debug.log state handled gracefully
- [x] Existing debug.log parses without freezing UI
- [x] Large file still responds within acceptable time
- [x] Last entries rendering does not break on special characters

Evidence:
- Screenshot(s): Missing log: "Debug log not found" notice shown cleanly. With 4.2 MB debug.log (stress test): last 200 entries returned in ~0.6s. Top offender: WooCommerce (38 errors).
- Notes: Special characters in log entries (HTML tags, SQL fragments) rendered as plain text — no XSS injection. Line `</div>` in log entry correctly escaped as `&lt;/div&gt;` in UI.

---

## G) Report (`wphm-report`)

### Visual
- [x] Report header, score hero, and section cards are aligned
- [x] Download bar appears only after report generation
- [x] Tables and mini charts are readable across breakpoints

### Functional
- [x] Generate report returns complete sections
- [x] JSON/TXT download works and file opens correctly
- [x] PDF/print flow works (popup allowed + blocked fallback)
- [x] No JS runtime error during report rendering

Evidence:
- Screenshot(s): Report generated with all 5 sections (Dashboard, Conflicts, Performance, PHP Compat, Debug Log). JSON download validated via `JSON.parse()` — parses cleanly. TXT download: line-delimited, no HTML tags present.
- Notes: PDF via `window.print()` — tested with popup blocker active: fallback message "Please allow popups and try again" shown. Footer reads "Generated by Health Radar" in both output formats. Console: zero errors.

---

## H) Security/Regression Smoke

- [x] Access denied for non-admin users
- [x] AJAX endpoint rejects invalid nonce
- [x] No raw unescaped HTML from scan results in rendered output
- [x] Cached results and force-refresh behavior are correct

Evidence:
- Screenshot(s): Logged in as Editor role — all 6 plugin menu pages return "Sorry, you are not allowed to access this page." (WordPress default). AJAX call with tampered nonce returns `-1` and UI shows "Security check failed." notice.
- Notes: Forced refresh (`?force=1` equivalent via rescan button) correctly invalidates cached transient and re-runs scan. Cached state on subsequent load confirmed via network tab (no AJAX call fired on view-only).

---

## Release Gate Decision

- [x] PASS
- [ ] FAIL

Blockers: **None**

Approved by: **fzihak**

Date: **March 6, 2026**

---

## QA Summary

| Module              | Visual | Functional | Result  |
|---------------------|--------|------------|---------|
| Global UI           | ✅     | N/A        | PASS    |
| Dashboard           | ✅     | ✅         | PASS    |
| Conflicts           | ✅     | ✅         | PASS    |
| Performance         | ✅     | ✅         | PASS    |
| PHP Compatibility   | ✅     | ✅         | PASS    |
| Debug Log           | ✅     | ✅         | PASS    |
| Report              | ✅     | ✅         | PASS    |
| Security Smoke      | N/A    | ✅         | PASS    |

**Overall QA Result: ✅ PASS — Ready for Production (v1.0.0)**
