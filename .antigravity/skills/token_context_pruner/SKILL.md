---
name: token_context_pruner
description: Compress context size before LLM generation passes by injecting minimal interfaces and using structured JSON contracts.
---

# Token Context Pruner (`token_context_pruner`)

## Purpose
Compress prompt context size, eliminate superfluous token overhead, and maximize LLM reasoning precision before code generation passes.

## Execution Rules & Guidelines

### 1. Minimal Interface Ingestion
- **NEVER** inject entire Moodle core classes or large framework files into agent prompt contexts.
- Strip implementation bodies and extract only the target abstract signatures and interface contracts needed for the specific task.
- Example contract extractions:
  - `\core\task\scheduled_task`: only `execute(): void`, `get_name(): string`.
  - `\core_privacy\local\metadata\null_provider`: only `get_reason(): string`.
  - `\core_analytics\local\target\base`: only target prediction & calculation signatures.
  - `\local_learningsuccess\local\explanation\rule_interface`: method signature and expected return schema.

### 2. Structured JSON Inter-Agent Communication
- Agents communicate state, handoffs, and intermediate schemas strictly using typed JSON objects.
- Ban natural language conversational filler during inter-agent transfers.
- Standard JSON envelope for handoff:
  ```json
  {
    "stage": "Phase 2: Analytics & Explanation Engine",
    "agent": "moodle-core-engine",
    "status": "COMPLETED",
    "artifacts_produced": [
      "classes/local/analytics/analytics_adapter.php",
      "classes/local/explanation/signal_collector.php"
    ],
    "contracts_exposed": {
      "rule_output_schema": {
        "type": "string",
        "severity": "string (low|medium|high|critical)",
        "value": "number",
        "message": "string"
      }
    },
    "dependencies_for_next_stage": [
      "classes/local/recommendation/recommendation_engine.php"
    ]
  }
  ```

### 3. File Diff & Focused Patch Generation
- Do not regenerate unchanged files in full.
- Target only single-responsibility classes under their scoped namespaces.
- Keep agent focus narrow: each agent consumes only its designated layer (Database schema vs Domain Logic vs Presentation vs Testing).

