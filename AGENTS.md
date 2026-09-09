# Antigravity Project Instructions: local_learningsuccess

## Moodle 5.x Development Standards
1. **File Security Guard**: Every PHP file must begin with:
   ```php
   defined('MOODLE_INTERNAL') || die();
   ```
2. **Namespace Conventions**: Strictly use PSR-4 namespaces: `namespace local_learningsuccess\...`.
3. **Database Access ($DB API)**: Never use raw SQL concatenations. Always use `$DB` parameterized methods (`get_records`, `insert_record`, `update_record`).
4. **Localization (i18n)**: All UI strings must use `get_string()` referencing `lang/en/local_learningsuccess.php`.
5. **External API**: Web services must extend `external_api`, using `external_function_parameters` and strict `PARAM_*` types.

## Token Optimization & Context Pruning
1. **Never dump monolithic Moodle core files** into the conversation context.
2. Ingest only minimal interface contracts and signatures needed for the immediate task.
3. Use structured JSON envelopes for all inter-agent communications and state handoffs.
4. Execute via partitioned sub-agents: `moodle-architect`, `moodle-core-engine`, `moodle-ui-dev`, and `moodle-qa-auditor`.

## Product Specification & Technical Plan
- Strictly conform to the product architecture, boundaries, domain model, and UI workflows documented in [technical.md](technical.md).
- Follow the multi-phase roadmap:
  - Phase 1: Risk Provider Abstraction (`classes/local/risk/`) & Signals/Explanation (`classes/local/signal/`, `classes/local/explanation/`)
  - Phase 2: Recommendations (`classes/local/recommendation/`) & Teacher Actions / Notes (`classes/local/intervention/`, `db/install.xml`)
  - Phase 3: Follow-up System (`classes/task/process_followups.php`, follow-up notifications)
  - Phase 4: Before/After Snapshots (`local_ls_snapshot`) & Outcome Evaluator (`classes/local/outcome/`)
  - Phase 5: Student Summary Application Service (`classes/local/service/`), UI Views & External Exporters


