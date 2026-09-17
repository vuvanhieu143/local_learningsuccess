# Changelog

All notable changes to the **Learning Success & Early Intervention** (`local_learningsuccess`) plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [v1.1.0] - 2026-09-17

### Added
- **Plugin Stylesheet (`styles.css`)**: Created dedicated plugin stylesheet automatically loaded by Moodle's theme engine, providing WCAG 2.1 Level AA compliant color styling across all components.
- **Dynamic Signal Severity Badging**: Added dynamic severity styling in the student detail view, ensuring Critical signals render in prominent danger red (`text-bg-danger text-white`) instead of plain gray.
- **Explicit Contrast Utility Classes**: Added `text-white` and `text-dark` attributes to struggle tags, status pills, and actionability badges to guarantee text legibility regardless of theme configuration.

### Changed
- **Accessibility & Contrast Overhaul (WCAG 2.1 Level AA)**:
  - Replaced low-contrast cyan (`#0dcaf0`, contrast ratio 1.62:1) across section headers, outline buttons (`btn-outline-info`), and badges with deep accessible teal (`#055160` text / `#087990` border, contrast >= 5.06:1 to 8.93:1).
  - Replaced yellow warning icons (`#ffc107`, contrast ratio 1.63:1) on white cards with accessible amber (`#944a00`, contrast ratio 6.48:1).
  - Eliminated washed-out `.text-secondary` and `.text-muted` across all templates, replacing them with high-contrast `#212529` (contrast ratio 15.43:1 on white).
  - Enhanced action note quote previews (`"Direct chat discussion"`) with dedicated styling for crisp readability.
- **Moodle Coding Standards (PHPCS)**:
  - Full codebase compliance with Moodle CodeSniffer standards across all 73 plugin files (`--max-warnings 0` compliant).
  - Removed prohibited `MOODLE_INTERNAL` checks from autoloaded classes and test files per Moodle 4.3+ conventions.
  - Converted promoted constructor properties in value objects to explicit typed properties with complete `@var` docblocks.
  - Added `@covers` annotations and `final class` declarations to all 16 PHPUnit test classes.
  - Reordered language strings in `lang/en/local_learningsuccess.php` and interface declarations in `classes/privacy/provider.php` alphabetically.
- **SQL Formatting Standards**:
  - Aligned all database queries with Moodle SQL coding style guidelines (right-aligned keywords and vertically aligned `AND`/`OR` clauses).

### Fixed
- **Database Query Error**: Resolved `dml_read_exception` in `student_success_service.php` caused by an undefined `$userfields` variable during `get_enrolled_users()` calls.
- **Fallback Risk Logic**: Refined inactivity signal threshold and grace period calculations for newly enrolled learners with no historical activity data.
- **Cache Management**: Cleaned up obsolete cache clearing methods and invalidation handlers.
- **Test Artifacts**: Removed temporary debugging pauses from Behat feature files.

---

## [v1.0.0] - 2026-09-16

### Added
- **Class Pulse Widget**: Course overview widget aggregating active student metrics, risk levels (Healthy, Monitor, At Risk, Critical, No Data), and active intervention counts.
- **Today's Priorities Queue**: Priority action queue with struggle archetype tagging (chronic disengagement, assessment struggle, completion stall, pacing stall) and group/cohort filtering.
- **Student Success Details**: Comprehensive individual learner view displaying active risk indicators, explanation breakdowns, predictive risk scores, and recommended actions.
- **Intervention Tracking**: Complete intervention lifecycle management (creation modal, quick check-in presets, scheduled follow-ups, status transitions, and outcome evaluation).
- **Dual Risk Engine**: Hybrid risk assessment combining Moodle Analytics machine learning models with deterministic rule-based fallback evaluation.
- **Privacy & GDPR Compliance**: Full implementation of Moodle's core Privacy Subsystem (`provider.php`) for data export and deletion.
- **Automated Testing Suite**: Comprehensive PHPUnit integration test suite and Behat BDD acceptance test scenarios.

