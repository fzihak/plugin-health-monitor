# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-18
### Changed
- Removed inline JavaScript redirect from the admin documentation page and replaced it with a standard admin link/button.
- Removed direct loading of `wp-admin/includes/plugin.php`.
- Refactored path/directory resolution to use WordPress helper-based locations instead of hardcoded internal constants.
- Updated debug log path handling to use centralized resolver methods.
- Updated autoload-size SQL handling to use PHPCS-compliant prepared query placeholders.

## [1.0.0] - 2026-03-05
### Added
- 🔍 Plugin Conflict Detector — hook collisions + duplicate script/style handles
- ⚡ Performance Insight Panel — DB queries, asset count, autoload size, 0–100 Health Score
- 🐘 PHP Compatibility Checker — per-plugin PHP validation + deprecated function scan
- 📋 Debug Log Analyzer — fatal/warning/notice grouping by top-offending plugin
- 🧬 Duplicate Asset Detector — md5 fingerprinting across JS/CSS handles
- 📄 Health Report Generator — JSON/TXT download + print-to-PDF
- 🖥️ Full WP-CLI command suite (`scan`, `score`, `conflicts`, `report`, `log`)
- Custom radar SVG admin menu icon
- Automated Plugin Check scan configuration
