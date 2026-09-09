# <img alt="Learning Success Icon" src="pix/monologo.svg" width="64" style="max-width: 64px; vertical-align: middle;"> Learning Success & Intervention

[![Latest Release](https://img.shields.io/badge/release-v1.0.0--alpha-orange)](https://github.com/vuvanhieu143/local_learningsuccess/releases)
[![Moodle Plugin CI](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml)
[![PHP Support](https://img.shields.io/badge/php-8.2--8.4-blue)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![Moodle Support](https://img.shields.io/badge/Moodle-5.0--5.3-orange)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![License GPL-3.0](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](https://github.com/vuvanhieu143/local_learningsuccess/blob/main/LICENSE)
[![GitHub contributors](https://img.shields.io/badge/contributors-1-brightgreen)](https://github.com/vuvanhieu143/local_learningsuccess/graphs/contributors)

Moodle provides learning analytics, predictive models, course completion, grades, and logs. However, teachers often face the challenge of interpreting fragmented data across multiple screens. When a student is flagged as "At Risk", teachers still need answers to four crucial pedagogical questions:

- **Why is this student at risk?**
- **What should I do to help?**
- **How can I quickly execute an intervention?**
- **Did the intervention actually work?**

**Learning Success & Intervention** (`local_learningsuccess`) provides this missing workflow. It converts raw Moodle learning signals into **explainable, actionable, and measurable interventions** directly embedded in your course navigation.

```text
Moodle Learning Signals
        ↓
Student Status / Insights
        ↓
     Explain
        ↓
    Recommend
        ↓
    Intervene (1-Click Action)
        ↓
  Track Outcome (Automated)
        ↓
Measure Effectiveness
```

---

## Key Features

### 1. Class Pulse & Priority Queue
- **Class Pulse**: Instant course-level distribution across four clear health buckets (**Healthy**, **Monitor**, **At Risk**, **Critical**) alongside completion metrics.
- **Today's Priorities**: A focused queue showing teachers the top students requiring immediate attention, avoiding cognitive overload.
- **Group Filtering**: Easily filter priorities and course pulse by Moodle Course Groups or Cohorts.

### 2. Explainable Contributing Signals ("Why?")
Instead of opaque predictive scores, the deterministic explanation engine explains contributing factors in clear language:
- **Inactivity**: Tracks days since last course access against dynamic thresholds.
- **Overdue Work**: Detects missed assignment deadlines.
- **Grade Declines**: Identifies significant grade drops across consecutive assessments.
- **Completion Lag**: Evaluates progress against required course completion criteria.

### 3. Actionable Recommendations ("What to do?")
Rule-based recommendation engine maps signals directly to concrete pedagogical actions:
- **Direct Check-in**: Recommend sending a personalized message.
- **Learning Resources**: Suggest reviewing prerequisite modules or remedial materials.
- **Extensions**: Recommend assignment deadline extensions for struggling learners.

### 4. 1-Click Intervention Workflow
- **Zero-Friction Quick Messaging**: Click `[Quick Message]` to open a check-in modal pre-filled with supportive text.
- **Moodle Messaging API Integration**: Directly dispatches messages via Moodle's native instant messaging and email notification system.
- **Audit History**: Records teacher interventions, notes, and actions for longitudinal review.

### 5. Automated Outcome Tracking ("Did it work?")
- **Before & After Snapshots**: Captures student risk, completion, and grade metrics at the moment of intervention.
- **Scheduled Background Evaluation**: The background task automatically recalculates metrics after 7 days and categorizes outcomes:
  - **IMPROVED 🎉**: Significant recovery in engagement or grades.
  - **NO_CHANGE**: Metrics remain stable.
  - **DECLINED**: Further escalation or advisor referral needed.

---

## Installation

### Via Git
Clone the repository into your Moodle installation's `local/` directory:

```bash
cd /path/to/moodle/local
git clone https://github.com/vuvanhieu143/local_learningsuccess.git learningsuccess
```

### Via ZIP Download
1. Download the latest release from the [Releases page](https://github.com/vuvanhieu143/local_learningsuccess/releases).
2. Extract the archive into `local/learningsuccess`.
3. Log in as an Administrator and navigate to **Site administration -> Notifications** to run the database upgrade.

---

## Navigation & Usage

Once installed and enabled:
1. Open any course where you have a teacher or editing teacher role.
2. In the course **Secondary Navigation** menu, click **Learning Success & Intervention**.
3. Inspect **Class Pulse** and **Today's Priorities**.
4. Click `[Quick Message]` or `[Record Intervention]` to support a student.

---

## Configuration

Site administrators can configure plugin behavior via **Site administration -> Plugins -> Local plugins -> Learning Success & Intervention**:

- **Enable Learning Success**: Toggle the plugin globally.
- **Enable Class Pulse**: Show or hide the course-level health pulse card.
- **Enable Intervention Tracking**: Turn intervention recording on or off.
- **Inactivity Threshold**: Number of days without course access before flagging (default: `7` days).
- **Grade Decline Threshold**: Percentage drop between assessments triggering a warning (default: `20%`).

---

## Compatibility

Supported and tested with:

- **Moodle Versions**: 5.0, 5.1, 5.2, and 5.3+
- **PHP Versions**: 8.2, 8.3, and 8.4
- **Databases**: MariaDB 10.11+, MySQL 8.0+, PostgreSQL 16+
- **Frontend Standard**: Modern ES6 AMD modules (`core/modal_save_cancel`, `core/ajax`), WCAG 2.1 AA accessible
- **Browsers**: Chrome, Firefox, Safari, Edge

---

## Automated CI Testing

This plugin is tested using the official [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) workflow:

- **PHP Linting & CodeSniffer**: `php -l` and Moodle Code Checker (`moodle-cs`)
- **PHP Mess Detector & PHPDoc**: Zero warnings enforced
- **Mustache Linting**: Validated against Moodle theme standards
- **ES6 JavaScript Linting**: Verified module exports and selectors
- **PHPUnit Test Suites**: Unit test coverage across rules, services, privacy, and interventions
- **Behat BDD Testing**: End-to-end teacher journey acceptance tests

---

## Contributing

Contributions, bug reports, and feature requests are welcome!

- Submit issues and suggestions to the [Issue Tracker](https://github.com/vuvanhieu143/local_learningsuccess/issues).
- Submit code contributions via [Pull Requests](https://github.com/vuvanhieu143/local_learningsuccess/pulls).
- Translations can be contributed via [Moodle AMOS](https://lang.moodle.org/).

---

## License

Licensed under the [GNU General Public License, Version 3.0](https://www.gnu.org/licenses/gpl-3.0.html).

&copy; 2026 Learning Success Team.