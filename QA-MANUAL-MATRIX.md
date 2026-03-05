# Health Radar — Manual QA Matrix (v1)

Use this matrix before release candidate and final release.

## Test Environment

- WordPress: minimum supported + latest stable
- PHP: 8.1, 8.2, 8.3
- Site profiles:
  - Small site (5–10 active plugins)
  - Large site (30+ active plugins)
- Viewports:
  - Desktop: 1440px+
  - Tablet: 782px–1100px
  - Mobile: <=782px

---

## A) Global UI Consistency

- [ ] Header spacing and typography consistent across all plugin pages
- [ ] Button style/hover/disabled/loading states consistent
- [ ] Alert and badge color contrast readable
- [ ] Tables do not overflow/break layout unexpectedly
- [ ] No visible unstyled content on initial page load
- [ ] No layout shift after AJAX result render

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## B) Dashboard (`wphm-dashboard`)

### Visual
- [ ] Score ring is centered and readable
- [ ] Dimension bars align correctly and animate without glitch
- [ ] Module cards align in grid, hover state smooth
- [ ] Scan status text remains readable after repeated scans

### Functional
- [ ] "Run Full Scan" button triggers request and returns response
- [ ] Score + dimension values update correctly
- [ ] No JS error in browser console

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## C) Conflicts (`wphm-conflicts`)

### Visual
- [ ] Header icon/title/description spacing consistent
- [ ] Empty state/info notice renders correctly
- [ ] Result table layout remains clean for long plugin names

### Functional
- [ ] Scan action returns data correctly
- [ ] Severity badges display expected status colors
- [ ] Repeated scan does not duplicate broken markup

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## D) Performance (`wphm-performance`)

### Visual
- [ ] SAVEQUERIES warning notice style is readable and non-breaking
- [ ] Top autoloaded options table aligns on all viewports
- [ ] Breakdown cards spacing consistent after AJAX refresh

### Functional
- [ ] Refresh button updates metrics
- [ ] Numeric values are sane and not NaN/undefined
- [ ] Page remains responsive on large options table

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## E) PHP Compatibility (`wphm-php-compat`)

### Visual
- [ ] Compatibility table displays correctly for long plugin names
- [ ] Deprecated usage subsection is readable
- [ ] Badge/status colors are consistent

### Functional
- [ ] Check action returns plugin compatibility data
- [ ] Incompatible plugins are highlighted correctly
- [ ] Deprecated usage rows render safely

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## F) Debug Log (`wphm-debug-log`)

### Visual
- [ ] Status table aligns properly (Enabled/Disabled/Found)
- [ ] Setup guide and code block styling readable
- [ ] Summary cards, offender chart, and log viewer remain stable on mobile

### Functional
- [ ] Missing debug.log state handled gracefully
- [ ] Existing debug.log parses without freezing UI
- [ ] Large file still responds within acceptable time
- [ ] Last entries rendering does not break on special characters

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## G) Report (`wphm-report`)

### Visual
- [ ] Report header, score hero, and section cards are aligned
- [ ] Download bar appears only after report generation
- [ ] Tables and mini charts are readable across breakpoints

### Functional
- [ ] Generate report returns complete sections
- [ ] JSON/TXT download works and file opens correctly
- [ ] PDF/print flow works (popup allowed + blocked fallback)
- [ ] No JS runtime error during report rendering

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## H) Security/Regression Smoke

- [ ] Access denied for non-admin users
- [ ] AJAX endpoint rejects invalid nonce
- [ ] No raw unescaped HTML from scan results in rendered output
- [ ] Cached results and force-refresh behavior are correct

Evidence:
- Screenshot(s): ____________________
- Notes: ____________________________

---

## Release Gate Decision

- [ ] PASS
- [ ] FAIL

Blockers:
- ________________________________

Approved by:
- ________________________________

Date:
- ________________________________
