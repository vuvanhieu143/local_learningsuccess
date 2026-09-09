---
name: moodle_standards_guard
description: Enforce Moodle 5.x coding conventions and quality standards before any file write operation.
---

# Moodle Standards Guard (`moodle_standards_guard`)

## Purpose
Enforce strict Moodle 5.x coding conventions, architecture patterns, and security guardrails before any file write operation or code generation pass.

## Execution Rules & Checkpoints

### 1. File Security Header
- Every PHP file MUST begin with the Moodle internal execution guard directly after the opening tag:
  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();
  ```
- No closing PHP tags (`?>`) at the end of files.

### 2. Strict Namespacing
- All classes must follow the standard Moodle PSR-4 classloader mapping under:
  ```php
  namespace local_learningsuccess\<subnamespace>;
  ```
- Root classes reside in `classes/` and map to `namespace local_learningsuccess;`.
- Subdirectories inside `classes/` map directly to subnamespaces (e.g., `classes/local/explanation/` maps to `namespace local_learningsuccess\local\explanation;`).

### 3. Localization & Internationalization (i18n)
- Hardcoded user-facing strings are strictly forbidden.
- All user-facing strings MUST use `get_string('key', 'local_learningsuccess', $a)` or AMD equivalent `core/str.get_string('key', 'local_learningsuccess')`.
- All keys referenced MUST be defined in `lang/en/local_learningsuccess.php`.

### 4. Database Access Layer ($DB API)
- Raw SQL string concatenations or unescaped variables are strictly prohibited.
- Always use the global `$DB` instance and parameterized queries:
  - `$DB->get_record('local_ls_intervention', ['id' => $id], '*', MUST_EXIST);`
  - `$DB->get_records_select('local_ls_intervention', 'courseid = :courseid AND status = :status', ['courseid' => $courseid, 'status' => $status]);`
  - `$DB->insert_record('local_ls_intervention', $record);`
  - `$DB->update_record('local_ls_intervention', $record);`
- Use transactions (`$transaction = $DB->start_delegated_transaction();`) for multi-step mutations.

### 5. External API & Web Services
- External endpoints must extend `core_external\external_api` (or `external_api` in Moodle core).
- All parameters MUST be defined with `external_function_parameters` using explicit `PARAM_*` cleaning types (e.g. `PARAM_INT`, `PARAM_ALPHA`, `PARAM_RAW`).
- Responses must be strictly typed using `external_single_structure`, `external_multiple_structure`, and `external_value`.
- Require explicit capability and session checks before performing actions:
  ```php
  self::validate_parameters(self::execute_parameters(), [...]);
  require_capability('local/learningsuccess:createintervention', $context);
  ```

### 6. Modern PHP 8.2+ Compatibility
- Strict typing declared where appropriate (`declare(strict_types=1);`).
- Explicit parameter and return type hints on all methods and functions.
- Class properties must have explicit visibility and type declarations.

