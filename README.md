# <img alt="Learning Success Icon" src="pix/monologo.svg" width="64" style="max-width: 64px; vertical-align: middle;"> Learning Success & Intervention

[![Latest Release](https://img.shields.io/badge/release-v1.0.0--alpha-orange)](https://github.com/vuvanhieu143/local_learningsuccess/releases)
[![Moodle Plugin CI](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml)
[![PHP Support](https://img.shields.io/badge/php-8.2--8.4-blue)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![Moodle Support](https://img.shields.io/badge/Moodle-5.0--5.3-orange)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![License GPL-3.0](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](https://github.com/vuvanhieu143/local_learningsuccess/blob/main/LICENSE)

**Moodle Learning Success & Intervention** (`local_learningsuccess`) turns Moodle Learning Analytics signals and observable student engagement data into **explainable, actionable, and measurable teacher interventions**.

Moodle already detects students who may need attention. Learning Success provides the missing pedagogical workflow: **Why? → What should the teacher do? → When should they follow up? → Did the situation improve?**

```text
DETECT (Moodle Analytics / Activity Signals)
  ↓
EXPLAIN (Deterministic Observable Evidence)
  ↓
PRIORITISE (Today's Priority Queue)
  ↓
RECOMMEND (Action-Oriented Pedagogy)
  ↓
INTERVENE (Direct Moodle Message + Teacher Notes)
  ↓
FOLLOW UP (Scheduled Task & Reminders)
  ↓
MEASURE OUTCOME (Before/After Snapshots & Progress Evaluation)
```

---

## Table of Contents

- [Why Learning Success?](#why-learning-success)
- [How to Use (Teacher Walkthrough)](#how-to-use-teacher-walkthrough)
  - [Step 1: Open the Course Dashboard](#step-1-open-the-course-dashboard)
  - [Step 2: Inspect "Today's Priorities"](#step-2-inspect-todays-priorities)
  - [Step 3: Understand the Contributing Signals ("Why?")](#step-3-understand-the-contributing-signals-why)
  - [Step 4: Take Action & Schedule Follow-Up](#step-4-take-action--schedule-follow-up)
  - [Step 5: Automated Follow-Up Reminders](#step-5-automated-follow-up-reminders)
  - [Step 6: Measure Outcomes (Before/After)](#step-6-measure-outcomes-beforeafter)
- [Key Features](#key-features)
- [Screens & Navigation](#screens--navigation)
- [Architecture & Design Rules](#architecture--design-rules)
- [Installation](#installation)
- [Configuration](#configuration)
- [Scheduled Tasks](#scheduled-tasks)
- [Capabilities & Permissions](#capabilities--permissions)
- [Security & Performance](#security--performance)
- [Privacy & GDPR](#privacy--gdpr)
- [Contributing & Testing](#contributing--testing)
- [License](#license)

---

## Why Learning Success?

Traditional analytics dashboards display graphs, click counts, and prediction probabilities, but often fail to answer: **"Which 5 students need my attention today, and what action should I take right now?"**

Learning Success is built around three core design principles:

1. **Not Another Analytics Dashboard**: Focuses exclusively on intervention workflow, follow-up scheduling, and outcome measurement.
2. **Deterministic & Explainable**: Always explains why a student is flagged using observable facts (e.g. *No activity for 9 days*, *3 overdue activities*, *Grade down 18%*) instead of opaque machine-learning percentages.
3. **Moodle-Native & Safe**: Zero core modifications. Dispatches communications directly through Moodle Messaging and stores only the data needed to evaluate outcomes.

---

## How to Use (Teacher Walkthrough)

### Step 1: Open the Course Dashboard
1. Log in to Moodle with a **Teacher** or **Editing Teacher** role.
2. Navigate to your course.
3. In the course **Secondary Navigation** menu (the top tab bar in Boost theme), click **Learning Success**.

### Step 2: Inspect "Today's Priorities"
The primary dashboard screen presents two main sections:
- **Class Pulse**: A concise distribution of enrolled students into four actionable buckets: **Healthy**, **Monitor**, **At Risk**, and **Critical**.
- **Today's Priorities**: An intelligent queue surfacing students who require attention today, ranked by urgency.
- **Group Filter**: If your course uses groups, select your cohort from the group dropdown.

### Step 3: Understand the Contributing Signals ("Why?")
Click on any student card or click `[View Student Insights]` to open the **Student Success View**:
- **Contributing Evidence**: Displays exact signals triggering the alert:
  - ⏳ **Inactivity**: Days since last course access.
  - 📝 **Overdue Work**: Specific assignments that have passed deadline without submission.
  - 📉 **Grade Decline**: Drop in assessment scores.
  - 📊 **Completion Stall**: Low course module completion progress.
  - 🤖 **Moodle Analytics**: Prediction model status (if enabled).

### Step 4: Take Action & Schedule Follow-Up
Review the **Recommended Actions** provided by the system:
1. Click **`[Take Action]`** or **`[Quick Message]`**.
2. A modal dialog will appear:
   - **Intervention Type**: Choose *Direct Message*, *Recommend Resource*, *Assignment Support*, *Grant Extension*, or *Advisor Referral*.
   - **Empathetic Message Preset**: Select or customize a supportive, encouraging message.
   - **Send via Moodle Messaging**: Check this box to automatically dispatch the message to the student's Moodle chat and email.
   - **Schedule Follow-Up**: Pick a follow-up date (defaults to **7 days**).
   - **Teacher Notes**: Add internal private notes (e.g., *Student discussed family illness, agreed to submit by Friday*).
3. Click **Save Intervention**.

### Step 5: Automated Follow-Up Reminders
- You don't need to manually keep track of calendar deadlines.
- When an intervention's follow-up date arrives, the background task (`process_followups`) moves the intervention to **Follow-Up Due** and sends you a native Moodle notification reminder.

### Step 6: Measure Outcomes (Before/After)
1. When follow-up is due, click **`[Complete Intervention]`**.
2. The system compares the **Before Snapshot** (captured on intervention start) against the **Current Metrics**:
   - **IMPROVED 🎉**: Student logged in, submitted missing work, or improved their course grade.
   - **NO CHANGE**: Metrics remain stable; prompts teacher to decide if further support is required.
   - **DECLINED**: Alerts teacher that additional escalation or advisor referral is needed.
3. Successful interventions are celebrated in the **Recent Success Stories** banner on the dashboard!

---

## Key Features

| Feature | Description |
| :--- | :--- |
| **Risk Provider Abstraction** | Consumes core Moodle Analytics models safely, with automatic fallback to observable activity/performance signals when models are not trained. |
| **Modular Signals & Explanations** | Independent signal evaluators (`inactivity`, `overdue`, `grade_decline`, `completion`, `analytics_risk`) that sort evidence by severity without coupling to recommendations. |
| **Deterministic Recommendation Engine** | Action-oriented rules mapping signals to empathetic check-ins, remedial resources, deadline extensions, or advisor referrals. |
| **1-Click Moodle Messaging** | Dispatches instant messages and email notifications using Moodle's core message subsystem without leaving the dashboard. |
| **Private Teacher Notes** | Multiple internal notes can be attached to any intervention record for longitudinal tracking (`local_ls_note`). |
| **Follow-Up Tracking Task** | Automated cron job (`process_followups`) scanning due dates and notifying teachers via Moodle Core Notifications. |
| **Non-Causal Outcome Measurement** | Captures before/after snapshots (`local_ls_snapshot`) and reports observable metric recovery while strictly avoiding uncalibrated causal claims. |
| **Group & Cohort Isolation** | Full support for `SEPARATEGROUPS` mode, ensuring teachers only view and intervene on students within their permitted groups. |

---

## Screens & Navigation

### 1. Today's Priorities & Class Pulse (`dashboard.php`)
- **URL**: `/local/learningsuccess/dashboard.php?courseid=COURSE_ID`
- Displays aggregated health metrics, group selector, urgent student priorities, and recent comeback celebrations.

### 2. Student Success View (`student.php`)
- **URL**: `/local/learningsuccess/student.php?courseid=COURSE_ID&userid=USER_ID`
- Detailed pedagogical drill-down showing:
  - Full contributing signal breakdown (*Why is this student at risk?*).
  - Recommended actions with 1-click apply buttons.
  - Complete intervention history, outcome badges, follow-up dates, and private teacher notes.

---

## Architecture & Design Rules

The plugin strictly adheres to the 10 Golden Rules defined in [`technical.md`](technical.md):

1. **Do not recreate Moodle Analytics**: Consume Moodle Analytics via the `risk_provider` interface; never re-implement competing machine learning pipelines.
2. **Do not store Moodle data unnecessarily**: Read Moodle core tables on demand. Only persist intervention records, teacher notes, and evaluation snapshots.
3. **Keep risk detection separate from intervention**: Detecting that a student is struggling is distinct from deciding what action the teacher should take.
4. **Every recommendation should have an explanation**: Recommendations are always accompanied by observable evidence.
5. **Every intervention should optionally have a follow-up**: Default 7-day follow-up with automated reminders.
6. **Every completed intervention records an outcome**: Measurable evaluation (Improved, No Change, Declined).
7. **Do not claim causation**: Say *"Student indicators improved after the intervention"*, not *"The intervention caused a 40% improvement"*.
8. **Use Moodle APIs instead of replacing them**: Use Moodle Messaging, Moodle Groups, MUC Caching, and Moodle Tasks.
9. **Business logic belongs in PHP services, not JavaScript**: AMD modules only handle UI state and AJAX triggers.
10. **Teacher UI answers: "What should I do next?"**: Practical, clear, and actionable.

---

## Installation

### Method 1: Git (Recommended)
```bash
cd /path/to/moodle/local
git clone https://github.com/vuvanhieu143/local_learningsuccess.git learningsuccess
```

### Method 2: ZIP Download
1. Download the latest release from the [GitHub Releases](https://github.com/vuvanhieu143/local_learningsuccess/releases) page.
2. Extract the archive into your Moodle root at `local/learningsuccess`.
3. Log in to Moodle as an administrator and visit **Site administration -> Notifications** to complete the database upgrade.

---

## Configuration

Site administrators can configure plugin policies in **Site administration -> Plugins -> Local plugins -> Learning Success & Intervention**:

| Setting | Default | Description |
| :--- | :--- | :--- |
| **Enable Plugin** | `Yes` | Globally enables or disables Learning Success features. |
| **Inactivity Threshold** | `7` days | Number of days without course access before triggering an inactivity warning (14 days for critical). |
| **Grade Decline Threshold** | `20%` | Assessment score drop percentage triggering academic performance warnings. |

---

## Scheduled Tasks

The plugin registers two automated background tasks in **Site administration -> Server -> Tasks -> Scheduled tasks**:

1. **`Refresh student learning success cache and signals`** (`\local_learningsuccess\task\refresh_student_data`):
   - Runs every 30 minutes.
   - Pre-warms MUC caches for active courses, auto-resolves stagnant interventions after 7 days, and runs garbage collection to prevent memory leaks.
2. **`Process due intervention follow-ups and notify teachers`** (`\local_learningsuccess\task\process_followups`):
   - Runs daily at 08:00 AM.
   - Scans active interventions where follow-up date is due and sends Moodle Core Notifications to teachers.

---

## Capabilities & Permissions

Permissions are defined in `db/access.php` with default assignments for Teacher, Editing Teacher, and Manager archetypes:

- `local/learningsuccess:viewcourse`: Access the course Learning Success dashboard and Class Pulse.
- `local/learningsuccess:viewstudent`: View detailed student explanations and timeline history.
- `local/learningsuccess:createintervention`: Create new interventions, send check-in messages, and add notes.
- `local/learningsuccess:manageintervention`: Complete, update, or dismiss intervention records.
- `local/learningsuccess:viewreports`: View course intervention effectiveness reports.
- `local/learningsuccess:manageconfig`: Configure plugin-wide policies and thresholds.

---

## Security & Performance

- **Zero SQL Injections**: 100% of database queries use `$DB` parameterized methods.
- **IDOR & Group Isolation**: Strict validation on every web controller and External Web Service endpoint. Teachers in `SEPARATEGROUPS` mode cannot view or intervene on students outside their assigned groups.
- **XSS Prevention**: 100% of Mustache template variables are auto-escaped using `{{var}}`.
- **$O(1)$ Bulk Aggregation**: Batch course analytics load grades, completion, and quiz attempts in 4–5 bulk queries rather than per-student loops, delivering sub-150ms page renders for classes of 300+ students.
- **Two-Tier MUC Caching**: Configured with 30-minute TTL and static acceleration in `db/caches.php`.

---

## Privacy & GDPR

Learning Success fully implements Moodle's Privacy API (`classes/privacy/provider.php`):
- Implements `metadata_provider` documenting stored tables (`local_ls_intervention`, `local_ls_signal`, `local_ls_note`, `local_ls_snapshot`).
- Implements `plugin_provider` supporting:
  - **User Data Export**: Exports all interventions, notes, and snapshots associated with a user in standard Moodle format.
  - **User Data Deletion**: Deletes personal intervention history when a student or context erasure request is processed.

---

## Contributing & Testing

We welcome issues, feedback, and pull requests!

### Running Unit Tests (PHPUnit)
```bash
vendor/bin/phpunit --filter local_learningsuccess
```

### Running Acceptance Tests (Behat)
```bash
vendor/bin/behat --tags @local_learningsuccess
```

### Code Standards Validation
```bash
# PHP CodeSniffer with Moodle Coding Style
phpcs --standard=.phpcs.xml.dist local/learningsuccess

# PHP File Standards Audit
node scratch/audit.js
```

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License as published by the Free Software Foundation**, either version 3 of the License, or (at your option) any later version.

&copy; 2026 Learning Success Team.