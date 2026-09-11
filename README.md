# <img alt="Learning Success Icon" src="pix/monologo.svg" width="64" style="max-width: 64px; vertical-align: middle;"> Learning Success & Intervention

[![Latest Release](https://img.shields.io/badge/release-v1.0.0--alpha-orange)](https://github.com/vuvanhieu143/local_learningsuccess/releases)
[![Moodle Plugin CI](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml/badge.svg)](https://github.com/vuvanhieu143/local_learningsuccess/actions/workflows/moodle-plugin-ci.yml)
[![PHP Support](https://img.shields.io/badge/php-8.2--8.4-blue)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![Moodle Support](https://img.shields.io/badge/Moodle-5.0--5.3-orange)](https://github.com/vuvanhieu143/local_learningsuccess/actions)
[![License GPL-3.0](https://img.shields.io/badge/license-GPL--3.0-lightgrey)](https://github.com/vuvanhieu143/local_learningsuccess/blob/main/LICENSE)

**Moodle Learning Success & Intervention** (`local_learningsuccess`) turns Moodle Learning Analytics flags and observable student engagement data into an **action-oriented, work-queue-first pedagogical intervention workflow**.

Core Moodle detects students who may need attention. Learning Success provides the missing pedagogical journey: **Who needs me? → Why now? → What should I do? → When do I follow up? → Did the indicators improve?**

```text
Moodle Analytics → DETECT
      ↓
Learning Success → EXPLAIN → PRIORITISE → RECOMMEND → INTERVENE → FOLLOW UP → MEASURE
```

```text
[DETECT]      Core Moodle Analytics & activity signals identify potential struggle.
   ↓
[EXPLAIN]     Deterministic, observable evidence ("Why now?": 9 days inactive, 3 overdue tasks).
   ↓
[PRIORITISE]  Actionability Work-Queue (Urgent → Follow-Up Due → Recommended → Monitor).
   ↓
[RECOMMEND]   Actionable pedagogy (Primary recommendation + alternative options).
   ↓
[INTERVENE]   Editable message templates dispatched via Moodle Messaging + private notes.
   ↓
[FOLLOW UP]   Automated scheduled follow-ups (3-day check-in, 7-day assignment support).
   ↓
[MEASURE]     Canonical snapshots: System Evidence vs Teacher-Confirmed Outcome.
```

---

## Table of Contents

- [Why Learning Success?](#why-learning-success)
- [How to Use (Teacher Walkthrough)](#how-to-use-teacher-walkthrough)
  - [Step 1: Open the Course Dashboard](#step-1-open-the-course-dashboard)
  - [Step 2: Review the Actionability Work-Queue](#step-2-review-the-actionability-work-queue)
  - [Step 3: Understand Contributing Signals ("Why Now?")](#step-3-understand-contributing-signals-why-now)
  - [Step 4: Take Action with Message Templates & Specific Follow-Up](#step-4-take-action-with-message-templates--specific-follow-up)
  - [Step 5: Teacher Dismissals & Overrides](#step-5-teacher-dismissals--overrides)
  - [Step 6: Automated Follow-Up Reminders](#step-6-automated-follow-up-reminders)
  - [Step 7: Evidence-Based Outcome Confirmation ("What Changed?")](#step-7-evidence-based-outcome-confirmation-what-changed)
- [Core Architecture & Technical Plan](#core-architecture--technical-plan)
  - [1. Actionability Engine vs Risk Scoring](#1-actionability-engine-vs-risk-scoring)
  - [2. Unified Single & Bulk Risk Provider](#2-unified-single--bulk-risk-provider)
  - [3. Normalized Intervention Lifecycle State Machine](#3-normalized-intervention-lifecycle-state-machine)
  - [4. Recommendation-Specific Follow-Up Windows](#4-recommendation-specific-follow-up-windows)
  - [5. System Evidence vs Teacher-Confirmed Outcome](#5-system-evidence-vs-teacher-confirmed-outcome)
  - [6. Canonical Before/After Snapshots](#6-canonical-beforeafter-snapshots)
  - [7. Privacy & Data Minimisation](#7-privacy--data-minimisation)
- [Screens & Navigation](#screens--navigation)
- [Installation](#installation)
- [Configuration](#configuration)
- [Scheduled Tasks](#scheduled-tasks)
- [Capabilities & Permissions](#capabilities--permissions)
- [Security & Performance](#security--performance)
- [Privacy & GDPR](#privacy--gdpr)
- [Testing & Quality Assurance](#testing--quality-assurance)
- [License](#license)

---

## Why Learning Success?

Traditional analytics dashboards display complex charts, log counters, and prediction probabilities, but leave teachers with decision fatigue: **"Which students actually need my attention right now, and what should I do?"**

Learning Success solves this through three foundational principles:

1. **Work-Queue First**: Surfaces actionable work items rather than raw data. A high-risk student who was contacted yesterday and is waiting for a reply is in **Monitor** state; an unattended student with overdue tasks or a follow-up due today is prioritized as **Urgent**.
2. **Deterministic & Explainable**: Always explains why a student is prioritized using observable facts (*"No activity for 9 days"*, *"3 overdue assignments"*, *"Completion stalled at 25%"*) instead of opaque percentages.
3. **Moodle-Native & Safe**: Zero core modifications. Dispatches messages directly through Moodle Messaging (`\core\message\message`), stores canonical metric snapshots, and adheres strictly to GDPR data minimisation.

---

## How to Use (Teacher Walkthrough)

### Step 1: Open the Course Dashboard
1. Log in to Moodle with a **Teacher** or **Editing Teacher** role.
2. Navigate to your course.
3. In the course navigation tab bar, click **Learning Success**.

### Step 2: Review the Actionability Work-Queue
The dashboard prioritizes students into distinct actionability tiers:
- 🚨 **Urgent Action**: Students exhibiting critical or multiple struggle signals with no active intervention in place, or open intervention drafts awaiting teacher outreach.
- ⏰ **Follow-Up Due**: Interventions whose scheduled follow-up window has elapsed and require teacher review.
- 💡 **Recommended**: Students with early warning indicators where proactive outreach or learning resource support is advised.
- 👁️ **Monitor**: Students where an intervention is already underway (`Contacted` or `Waiting`), awaiting student response or indicator movement. These are deprioritized so they do not clutter today's immediate action queue.

### Step 3: Understand Contributing Signals ("Why Now?")
Click on any student card or click `[View Student Insights]` to open the **Student Success View**:
- **Contributing Evidence**: Clear, observable facts explaining the alert:
  - ⏳ **Inactivity**: Days since last course access.
  - 📝 **Overdue Work**: Specific assignments past deadline without submission.
  - 📉 **Grade Decline**: Drop in assessment scores.
  - 📊 **Completion Stall**: Low or stalled module completion progress.
  - 🤖 **Moodle Analytics**: Prediction model status (if trained).
- **Struggle Archetype Tags**: Concise badges such as `Disengaged`, `Quiz Retries`, `Overdue Work`, or `Pacing Behind`.

### Step 4: Take Action with Message Templates & Specific Follow-Up
Review the **Primary Recommendation** and any alternative actions:
1. Click **`[Take Action]`** or **`[Check In]`**.
2. A modal dialog opens with a **pre-filled, editable message draft**:
   - **Inactivity Template**: Supportive check-in asking if the student needs assistance getting back on track.
   - **Overdue Template**: Practical guidance offering help prioritizing overdue activities.
   - **Grade Decline Template**: Constructive inquiry offering assessment feedback.
3. **Edit Draft**: Teachers can personalize the message directly (all messages require explicit teacher action; nothing is sent automatically).
4. **Follow-Up Scheduling**: Automatically defaults based on recommendation type:
   - Check-in: **3 days**
   - Meeting requested: **3 days**
   - Assignment support: **7 days**
   - Learning resources / progress monitoring: **7 days**
   *(Teachers can override the due date as needed).*
5. Click **Record Intervention**. The message is sent to the student via Moodle Messaging, and the intervention moves to `Contacted`.

### Step 5: Teacher Dismissals & Overrides
Not every unusual learning pattern represents a problem. A student may have low online activity because they are on approved leave or studying offline with a textbook.
- Teachers can **Dismiss** any signal by selecting a reason:
  - *Student on approved leave*
  - *Student working offline*
  - *False positive indicator*
  - *Not currently relevant*
  - *Other*
- **Reactivation Policy**: Dismissed signals remain suppressed for 14 days and will not recreate recommendations on subsequent page loads, unless the student's condition escalates to critical severity.

### Step 6: Automated Follow-Up Reminders
- Teachers do not need to track follow-up dates in external calendars.
- The background task (`process_followups`) runs daily, transitions due interventions to `Follow-Up Due`, and sends a native Moodle notification reminder to the teacher.

### Step 7: Evidence-Based Outcome Confirmation ("What Changed?")
1. When follow-up is due, the teacher reviews student progress and clicks **`[Complete Intervention]`**.
2. **"What Changed?"**: The system compares the canonical **Before Snapshot** against the **Current Metrics**:
   - *Activity*: e.g. 0.8 visits/wk → 3.1 visits/wk (Improved)
   - *Completion*: e.g. 45% → 72% (Improved)
   - *Grade*: e.g. 50% → 65% (Improved)
3. **System Evidence vs Teacher Outcome**:
   - **System Evidence**: Reports neutral indicator movements (*"Indicators improved after the intervention"*). Does not make uncalibrated claims of direct causation.
   - **Teacher-Confirmed Outcome**: The teacher selects the final verified outcome:
     - `Improved`
     - `No Change`
     - `Declined`
     - `Unable to Contact`
     - `Not Applicable`
4. Completed interventions with indicator recovery are presented in the **Recent Improvements & Outcomes** card on the dashboard.

---

## Core Architecture & Technical Plan

The plugin is structured according to clean domain-driven architecture and conforms to the 10 Architecture Rules in [`technical.md`](technical.md):

### 1. Actionability Engine vs Risk Scoring
- Pure risk calculation (`risk_provider`) computes mathematical struggle.
- The **Actionability Engine** (`classes/local/actionability/actionability_engine.php`) factors in current intervention status, follow-up deadlines, and recency to answer: *"What should the teacher do right now?"*
- Prevents duplicate check-ins when an intervention is already in progress.

### 2. Unified Single & Bulk Risk Provider
- Single-student profile (`get_risk($userid, $courseid)`) and bulk course pulse (`get_risks($userids, $courseid)`) use identical underlying evaluation logic via `classes/local/risk/risk_provider.php`.
- Guarantees 100% mathematical consistency across course summary views and individual student details.

### 3. Normalized Intervention Lifecycle State Machine
Strict state machine enforced through `classes/local/intervention/intervention_manager.php`:

```text
OPEN  ──►  CONTACTED  ──►  WAITING  ──►  FOLLOW_UP  ──►  COMPLETED
 │              │              │             │
 └──────────────┴──────────────┴─────────────┴────────►  DISMISSED / UNABLE_TO_CONTACT
```

Direct arbitrary status mutations are rejected; all updates pass through transition validation.

### 4. Recommendation-Specific Follow-Up Windows
- Replaces universal 7-day timers with context-aware defaults:
  - Check-in: **3 days**
  - Meeting: **3 days**
  - Assignment Support: **7 days**
  - Learning Resource: **7 days**

### 5. System Evidence vs Teacher-Confirmed Outcome
- Automated metric changes are reported as **System Evidence** with neutral phrasing (*"Indicators improved after intervention"*).
- The teacher confirms the final pedagogical outcome (`Improved`, `No Change`, `Declined`, `Unable to Contact`, `Not Applicable`).

### 6. Canonical Before/After Snapshots
- `local_ls_snapshot` is the single canonical source of metrics before and after an intervention.
- The intervention record references snapshots, eliminating duplicate JSON data storage.

### 7. Privacy & Data Minimisation
- Student email addresses are omitted from dashboard overviews and student cards to prevent unnecessary exposure.
- Provides student full names, profile avatars, Moodle profile links, and direct messaging actions.

---

## Screens & Navigation

### 1. Today's Priorities & Class Pulse (`dashboard.php`)
- **Route**: `/local/learningsuccess/dashboard.php?courseid=COURSE_ID`
- Features:
  - **Class Pulse**: Cohort breakdown across Healthy, Monitor, At Risk, and Critical.
  - **Actionability Work-Queue**: Ranked list of students requiring immediate attention.
  - **Group Filter**: Cohort filtering for courses using separate groups.
  - **Recent Improvements**: Completed interventions with documented recovery.

### 2. Student Success Details (`student.php`)
- **Route**: `/local/learningsuccess/student.php?courseid=COURSE_ID&userid=USER_ID`
- Features:
  - **Why Now?**: Bullet-point explanations of observable struggle signals.
  - **Recommendations**: Primary action and alternative options.
  - **Intervention History**: Longitudinal record of teacher actions, notes, follow-up dates, and outcomes.

---

## Installation

### Method 1: Git (Recommended)
```bash
cd /path/to/moodle/local
git clone https://github.com/vuvanhieu143/local_learningsuccess.git learningsuccess
```

### Method 2: ZIP Package
1. Download the latest release from [GitHub Releases](https://github.com/vuvanhieu143/local_learningsuccess/releases).
2. Extract the archive into your Moodle installation at `local/learningsuccess`.
3. Visit **Site administration -> Notifications** to run the database installation/upgrade.

---

## Configuration

Site administrators can configure plugin policies in **Site administration -> Plugins -> Local plugins -> Learning Success & Intervention**:

| Setting | Default | Description |
| :--- | :--- | :--- |
| **Enable Plugin** | `Yes` | Globally enables or disables Learning Success features. |
| **Inactivity Threshold** | `7` days | Days of inactivity before triggering a warning (critical threshold at 14 days). |
| **Grade Decline Threshold** | `20%` | Assessment score drop percentage triggering academic performance warnings. |

---

## Scheduled Tasks

The plugin registers two automated tasks in **Site administration -> Server -> Tasks -> Scheduled tasks**:

1. **`Refresh student learning success cache and signals`** (`\local_learningsuccess\task\refresh_student_data`):
   - Runs every 30 minutes.
   - Refreshes MUC caches for active courses and ensures smooth performance.
2. **`Process due intervention follow-ups and notify teachers`** (`\local_learningsuccess\task\process_followups`):
   - Runs daily at 08:00 AM.
   - Transitions due interventions to `FOLLOW_UP` and sends Moodle Core Notification reminders to teachers.

---

## Capabilities & Permissions

Permissions are defined in `db/access.php`:

- `local/learningsuccess:viewcourse`: Access the course dashboard and Class Pulse.
- `local/learningsuccess:viewstudent`: View detailed student explanations and timeline history.
- `local/learningsuccess:createintervention`: Create new interventions, send check-in messages, and add notes.
- `local/learningsuccess:manageintervention`: Complete, update, or dismiss intervention records.
- `local/learningsuccess:viewreports`: View course intervention effectiveness reports.
- `local/learningsuccess:manageconfig`: Configure plugin-wide policies and thresholds.

---

## Security & Performance

- **Zero Raw SQL**: 100% of database queries use `$DB` parameterized calls (`get_records`, `insert_record`, `update_record`).
- **IDOR & Group Protection**: Enforced on every controller and web service endpoint. Teachers in `SEPARATEGROUPS` mode cannot view or intervene on students outside their allocated groups.
- **XSS Prevention**: All Mustache template variables are auto-escaped using `{{var}}`.
- **$O(1)$ Bulk Aggregation**: Batch course analytics load completion, grades, and access records in 4–5 bulk SQL queries rather than per-student loops, delivering sub-150ms page renders for large cohorts.
- **Two-Tier MUC Caching**: Configured with 30-minute TTL and static acceleration in `db/caches.php`.

---

## Privacy & GDPR

Learning Success complies with Moodle's Privacy API (`classes/privacy/provider.php`):
- Implements `metadata_provider` documenting stored tables (`local_ls_intervention`, `local_ls_signal`, `local_ls_note`, `local_ls_snapshot`).
- Implements `plugin_provider` supporting:
  - **User Data Export**: Exports all interventions, notes, and snapshots associated with a user in standard Moodle format.
  - **User Data Deletion**: Deletes personal intervention history when a student or context erasure request is processed.
- Adheres to **Data Minimisation**: Omits raw email addresses from teacher dashboard listings.

---

## Testing & Quality Assurance

### Running PHPUnit Tests
```bash
vendor/bin/phpunit --filter local_learningsuccess
```

### Running Specific Test Suites
```bash
# Risk Provider consistency test
vendor/bin/phpunit local/learningsuccess/tests/risk_provider_test.php

# Actionability Engine & Work-Queue test
vendor/bin/phpunit local/learningsuccess/tests/actionability_test.php

# Normalized Lifecycle test
vendor/bin/phpunit local/learningsuccess/tests/intervention_lifecycle_test.php

# Signal Dismissal & Message Templates test
vendor/bin/phpunit local/learningsuccess/tests/signal_dismissal_test.php

# Outcome Evaluation & Snapshots test
vendor/bin/phpunit local/learningsuccess/tests/outcome_evaluator_test.php
```

### Code Standards Audit
```bash
# Verify 100% compliance with Moodle 5.x standards
node scratch/audit.js
```

---

## License

This program is free software: you can redistribute it and/or modify it under the terms of the **GNU General Public License as published by the Free Software Foundation**, either version 3 of the License, or (at your option) any later version.

&copy; 2026 Learning Success Team.