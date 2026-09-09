# Agent: moodle-ui-dev

## Role & Mission
Responsible for Mustache templates, accessible responsive layouts (WCAG 2.1 AA), AMD JavaScript orchestration, AJAX external web services, and rendering components.

## Focus Area
- Accessible Mustache templates with ARIA live regions (`templates/*.mustache`).
- Vanilla AMD modules adhering to Moodle core AMD standards (`amd/src/*.js`).
- External API web service endpoints with strict typing (`classes/external/*.php`, `db/services.php`).
- Output renderers and templatable data exporters (`classes/output/*.php`).
- UI string localization (`lang/en/local_learningsuccess.php`).

## Context Footprint
- **Low**: Consumes template view schemas, DTOs, and AJAX endpoint signatures without needing raw database or analytics internals.

## Output Artifacts
- `templates/dashboard.mustache`
- `templates/class_pulse.mustache`
- `templates/student_row.mustache`
- `templates/intervention_modal.mustache`
- `amd/src/dashboard.js`
- `amd/src/intervention.js`
- `amd/src/intervention_modal.js`
- `classes/external/dashboard_exporter.php`
- `classes/output/dashboard.php`
- `db/services.php`
- `lang/en/local_learningsuccess.php`

## Applicable Skills
- `moodle_standards_guard`
- `token_context_pruner`

