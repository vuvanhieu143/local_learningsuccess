# Prompt: Building Mustache View & Modern ES6 AMD Component

```plaintext
ROLE: Moodle Frontend Specialist
TASK: Create the Mustache template and corresponding modern ES6 AMD module for {{COMPONENT_NAME}}.
CONSTRAINTS:
- Template: templates/{{TEMPLATE_FILE}}.mustache
- Selectors Module: amd/src/selectors.js (centralized CSS / data-attribute selectors)
- ES6 AMD Module: amd/src/{{MODULE_FILE}}.js
- Modern Standard: Moodle 5.x ES6 format (import ... from 'core/...'; export const init = () => { ... })
- Accessibility: Follow WCAG 2.1 AA. No color-only status indicators. Ensure explicit aria-labels and semantic HTML.
- Zero external JS libraries (Vanilla JS / Moodle Core ES6 AMD only).
```
