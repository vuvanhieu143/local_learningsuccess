# Prompt: Building Domain Rule Classes (Token-Efficient)

```plaintext
ROLE: Moodle 5.x PHP Specialist
TASK: Implement the Explanation Rule for '{{RULE_NAME}}' in local_learningsuccess.
CONSTRAINTS:
- File path: classes/local/explanation/rules/{{RULE_FILE_NAME}}.php
- Namespace: local_learningsuccess\local\explanation\rules
- Must implement: local_learningsuccess\local\explanation\rule_interface
- Target Moodle version: 5.x (PHP 8.2+)
- Pure PHP, strict type hinting, no raw SQL (use $DB if necessary, prefer core APIs).
- Must return a structured array: ['type' => string, 'severity' => string, 'value' => int|float, 'message' => string].
- Internationalization: Retrieve strings using get_string() from 'local_learningsuccess'.
```

