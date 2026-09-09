# Learning Success & Intervention
## Technical Plan

**Plugin:** `local_learningsuccess`  
**Purpose:** Turn Moodle Learning Analytics signals into actionable teacher interventions and measurable student outcomes.

---

## 1. Product Direction

Learning Success must **not become another analytics plugin**.

Moodle already provides:

- Learning Analytics models
- Risk predictions
- Indicators
- Insights
- Activity data
- Completion data
- Grade data
- Analytics reports

Learning Success should consume those capabilities and focus on:

```text
DETECT
  ↓
EXPLAIN
  ↓
PRIORITISE
  ↓
RECOMMEND
  ↓
INTERVENE
  ↓
FOLLOW UP
  ↓
MEASURE OUTCOME
```

### Product boundary

```text
Moodle Analytics
    │
    │ Who may need attention?
    ▼
Learning Success
    │
    ├── Why?
    ├── How urgent?
    ├── What should the teacher do?
    ├── What did the teacher do?
    ├── When should they follow up?
    └── Did the student's situation improve?
```

### Core product statement

> Moodle Analytics identifies students who may need attention. Learning Success helps teachers understand why, take action, follow up, and measure whether the intervention helped.

---

# 2. Goals

## Primary goals

1. Reuse Moodle Analytics instead of duplicating prediction functionality.
2. Help teachers identify students requiring attention today.
3. Explain the reasons behind a student's status.
4. Recommend practical teacher actions.
5. Record interventions.
6. Schedule and manage follow-ups.
7. Measure outcomes after interventions.
8. Provide a simple teacher workflow rather than another analytics dashboard.
9. Remain Moodle-native.
10. Maintain strong privacy, capability, and security boundaries.

## Non-goals

The plugin should not initially:

- Build a competing machine-learning platform.
- Replace Moodle Analytics.
- Replace Moodle Messaging.
- Replace Moodle Gradebook.
- Replace Moodle Completion.
- Build a general-purpose BI dashboard.
- Build a complex rule-builder for teachers.
- Require external AI services.
- Claim heuristic scores are statistically calibrated probabilities.

---

# 3. Primary Teacher Workflow

The main teacher question should be:

> Which students need my attention today, and what should I do?

The primary workflow:

```text
Teacher opens course
        ↓
Today's Priorities
        ↓
Student requires attention
        ↓
Why?
        ↓
Recommended action
        ↓
Teacher starts intervention
        ↓
Follow-up scheduled
        ↓
Teacher reviews outcome
        ↓
Resolved
```

---

# 4. Main User Interfaces

## 4.1 Today's Priorities

This should be the primary screen.

Example:

```text
TODAY'S PRIORITIES

5 students need attention
2 follow-ups due


HIGH PRIORITY

John Smith
At risk · inactive for 9 days

Why:
- No course activity for 9 days
- 3 overdue activities
- Grade decreased 18%
- Moodle Analytics identifies student as at risk

[Message] [Start intervention]


FOLLOW-UP

David Nguyen
Intervention started 5 days ago

Follow-up due today

[Review]


RECENT OUTCOMES

4 students improved
2 unchanged
```

Avoid making this primarily a collection of charts.

---

## 4.2 Class Pulse

Class Pulse provides a concise overview:

```text
CLASS PULSE

12 students requiring attention

High priority       3
Needs attention     5
Monitoring          4

Open interventions  7
Follow-ups due      2
```

The teacher should be able to drill down immediately.

---

## 4.3 Student Success View

Example:

```text
John Smith

HIGH PRIORITY


WHY?

Moodle Analytics
Student identified as potentially at risk

Activity
No activity for 9 days

Course progress
3 overdue activities
Completion: 38%

Assessment
Average: 51%
Down 18%


RECOMMENDED ACTION

Check in with the student.

Reason:
Recent inactivity combined with overdue activities.


ACTIVE INTERVENTION

Check-in
Status: Waiting
Started: 4 days ago
Follow-up: Today

[Review intervention]


HISTORY

Sep 4
Teacher sent check-in

Sep 7
Student submitted assignment

Sep 11
Follow-up due
```

---

# 5. Moodle Analytics Integration

## 5.1 Principle

Moodle Analytics is the primary risk provider.

Learning Success must not duplicate its prediction models.

Architecture:

```text
Moodle Analytics
       │
       ▼
Risk Provider
       │
       ▼
Learning Success
```

---

# 6. Risk Provider Abstraction

Create:

```text
classes/local/risk/
    risk_provider.php
    risk_result.php
    moodle_analytics_provider.php
    fallback_provider.php
```

## Interface

```php
interface risk_provider {

    /**
     * Get risk information for a student in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @return ?risk_result
     */
    public function get_risk(int $userid, int $courseid): ?risk_result;
}
```

---

## 6.1 Risk Result

Create a normalized value object:

```php
class risk_result {

    /** @var float */
    private $score;

    /** @var string */
    private $level;

    /** @var string */
    private $source;

    /** @var ?string */
    private $model;

    // Accessors...
}
```

Possible levels:

```text
healthy
monitor
atrisk
critical
```

The exact terminology can be adjusted to the final UX.

---

# 7. Moodle Analytics Provider

Create:

```text
classes/local/risk/moodle_analytics_provider.php
```

Responsibilities:

1. Detect whether Moodle Analytics is available.
2. Retrieve supported Moodle Analytics prediction information.
3. Convert the result into `risk_result`.
4. Expose the Analytics source/model.
5. Avoid leaking Moodle Analytics implementation details into the rest of the plugin.

Conceptually:

```php
$risk = $moodleanalyticsprovider->get_risk(
    $userid,
    $courseid
);
```

The rest of Learning Success must not directly query Analytics tables.

---

# 8. Avoid Direct Dependence on Analytics Database Internals

Do not make the entire plugin depend on assumptions such as:

```text
analytics_predictions
analytics_predict_samples
sampleid == userid
```

unless those relationships are explicitly guaranteed by the Moodle API/model being consumed.

Prefer Moodle Analytics APIs and managers where possible.

If direct database access is unavoidable, isolate it completely inside:

```text
moodle_analytics_provider.php
```

Never let UI/service classes perform Analytics queries.

This protects the plugin against Moodle Analytics implementation changes.

---

# 9. Fallback Provider

A fallback provider may be retained when Moodle Analytics cannot provide a suitable prediction.

```text
Moodle Analytics available
        │
        ├── Yes → moodle_analytics_provider
        │
        └── No  → fallback_provider
```

The fallback must be clearly labelled.

Example:

```text
Risk source:
Course activity signals
```

Do not present the fallback as a Moodle Analytics prediction.

---

# 10. Risk Score Semantics

A deterministic heuristic score must not be described as:

```text
65% probability of failure
```

unless it is actually calibrated as a probability.

Use:

```text
Risk score
```

or:

```text
Priority score
```

depending on the purpose.

Example:

```text
Risk level:
High priority

Source:
Course activity signals
```

The score is primarily for prioritisation.

---

# 11. Explanation Engine

This is one of the core differentiating features.

Create:

```text
classes/local/explanation/
    explanation.php
    explanation_engine.php
```

And:

```text
classes/local/signal/
    signal.php
    signal_collector.php

    inactivity_signal.php
    overdue_signal.php
    grade_decline_signal.php
    completion_signal.php
    analytics_risk_signal.php
```

---

# 12. Signal Interface

```php
interface signal {

    /**
     * Evaluate a student's course signals.
     *
     * @param int $userid
     * @param int $courseid
     * @return ?explanation
     */
    public function evaluate(
        int $userid,
        int $courseid
    ): ?explanation;
}
```

Each signal should produce structured data.

Example:

```php
new explanation(
    type: 'inactivity',
    severity: explanation::WARNING,
    title: 'No course activity',
    value: 9
);
```

---

# 13. Explanation Types

Initial signals:

### Inactivity

```text
No course activity for 9 days.
```

### Overdue activities

```text
3 activities are overdue.
```

### Completion

```text
Course completion is 38%.
```

### Grade decline

```text
Average grade decreased by 18%.
```

### Moodle Analytics

```text
Moodle Analytics identifies this student as potentially at risk.
```

---

# 14. Explanation Engine Responsibilities

The engine should:

1. Collect available signals.
2. Remove irrelevant signals.
3. Determine severity.
4. Sort signals.
5. Produce human-readable explanations.
6. Keep evidence separate from recommendations.

Example:

```text
Risk
 ↓
Signals
 ↓
Explanation
```

Do not combine explanation and recommendation logic.

---

# 15. Recommendation Engine

Create:

```text
classes/local/recommendation/
    recommendation.php
    recommendation_engine.php

    rules/
        inactivity_rule.php
        overdue_rule.php
        grade_decline_rule.php
        completion_rule.php
```

The engine converts evidence into suggested teacher actions.

Example:

```text
Inactivity >= 7 days
        ↓
Check in with student
```

```text
Multiple overdue activities
        ↓
Discuss assignment difficulties
```

```text
Significant grade decline
        ↓
Review recent assessment performance
```

---

# 16. Recommendation Requirements

Recommendations must be:

- Explainable.
- Simple.
- Action-oriented.
- Non-prescriptive.
- Based on observable signals.
- Easy for teachers to dismiss or ignore.

Example:

```text
RECOMMENDED ACTION

Check in with the student.

Reason:
No course activity for 9 days.
```

Avoid:

```text
AI recommends intervention #42.
```

---

# 17. Intervention Domain

Create:

```text
classes/local/intervention/
    intervention.php
    intervention_manager.php
    intervention_status.php
```

An intervention represents a teacher action taken to support a student.

---

# 18. Intervention Data Model

Database table:

```text
local_learningsuccess_intervention
```

Suggested fields:

```text
id
courseid
userid
teacherid
type
status
reason
action
followupat
resolvedat
timecreated
timemodified
```

Foreign keys/indexes should follow Moodle database conventions.

---

# 19. Intervention Types

Initial types:

```text
checkin
assignment_support
resource_recommendation
meeting
academic_support
other
```

Do not create excessive categories initially.

---

# 20. Intervention Lifecycle

Recommended states:

```text
OPEN
  ↓
CONTACTED
  ↓
WAITING
  ↓
FOLLOW_UP
  ↓
RESOLVED
```

Alternative terminal states:

```text
UNABLE_TO_CONTACT
NOT_APPLICABLE
```

The lifecycle should be represented by constants or a dedicated status class rather than arbitrary strings scattered through the code.

---

# 21. Moodle Messaging Integration

Do not implement another messaging system.

Use Moodle's existing messaging APIs.

Workflow:

```text
Teacher
  ↓
[Message student]
  ↓
Moodle Messaging
  ↓
Intervention records action
```

Learning Success records that the intervention occurred; Moodle remains responsible for messaging.

---

# 22. Teacher Notes

Interventions should support optional notes.

Create:

```text
local_learningsuccess_note
```

Suggested fields:

```text
id
interventionid
authorid
note
timecreated
```

Example:

```text
Teacher note:

Student mentioned difficulty understanding
the last two assignments.

Follow-up:
Check assignment 4.
```

---

# 23. Follow-up System

Follow-up is a key differentiator.

An intervention should optionally have:

```text
followupat
```

Example:

```text
Intervention:
Check-in

Created:
2026-09-04

Follow-up:
2026-09-11
```

The teacher should receive a Moodle notification when follow-up becomes due.

---

# 24. Scheduled Task

Create:

```text
classes/task/process_followups.php
```

Register it in:

```text
db/tasks.php
```

Responsibilities:

```text
Find interventions where:
    followupat <= current time
    AND status requires follow-up

        ↓

Create Moodle notification

        ↓

Mark notification as processed if required
```

The task must be idempotent.

---

# 25. Outcome Tracking

This is the second major differentiator.

After an intervention, the teacher should record:

```text
Improved
No change
Declined
Unable to contact
Not applicable
```

Create:

```text
classes/local/outcome/
    outcome.php
    outcome_evaluator.php
    snapshot_service.php
```

---

# 26. Before/After Snapshot

When an intervention starts, capture relevant values.

Create:

```text
local_learningsuccess_snapshot
```

Suggested fields:

```text
id
interventionid
phase
riskscore
risklevel
completion
grade
activitylevel
overduecount
timecreated
```

Possible phases:

```text
before
followup
```

---

# 27. Example Outcome

Before:

```text
Risk: 72
Completion: 38%
Overdue: 3
Grade: 51%
```

After:

```text
Risk: 43
Completion: 57%
Overdue: 1
Grade: 58%
```

Teacher sees:

```text
IMPROVED

Risk
72 → 43

Completion
38% → 57%

Overdue activities
3 → 1
```

Do not claim causation.

The plugin should say:

```text
Student indicators improved after the intervention.
```

not:

```text
The intervention caused a 40% improvement.
```

---

# 28. Effectiveness Reporting

Keep reporting focused on intervention effectiveness.

Example:

```text
INTERVENTION OUTCOMES

Total interventions       87

Improved                  51
No change                 21
Declined                   9
Unknown                    6
```

Another view:

```text
INTERVENTION TYPES

Check-in messages
42 interventions
28 improved

Assignment support
27 interventions
17 improved

Meetings
13 interventions
10 improved
```

Avoid building general analytics dashboards.

---

# 29. Central Application Service

Create:

```text
classes/local/service/student_success_service.php
```

This should orchestrate the domain components.

Conceptually:

```php
class student_success_service {

    public function get_student_summary(
        int $userid,
        int $courseid
    ): student_summary {

        $risk = $this->riskprovider->get_risk(
            $userid,
            $courseid
        );

        $signals = $this->signalcollector->collect(
            $userid,
            $courseid
        );

        $explanations = $this->explanationengine->build(
            $risk,
            $signals
        );

        $recommendations = $this->recommendationengine->build(
            $explanations
        );

        $interventions = $this->interventionmanager->get_active(
            $userid,
            $courseid
        );

        return new student_summary(
            $risk,
            $explanations,
            $recommendations,
            $interventions
        );
    }
}
```

This should become the main application boundary.

---

# 30. Student Summary Object

Create:

```text
classes/local/service/student_summary.php
```

The UI should consume a normalized object containing:

```text
risk
signals
explanations
recommendations
active interventions
pending follow-ups
recent outcomes
```

This avoids duplicating business logic across pages and AJAX endpoints.

---

# 31. External API

Keep external functions thin.

Suggested exporters:

```text
classes/external/
    dashboard_exporter.php
    student_exporter.php
    intervention_exporter.php
```

The flow should be:

```text
AMD JavaScript
      ↓
External function
      ↓
Capability validation
      ↓
Service layer
      ↓
Domain logic
      ↓
Exporter
      ↓
JSON
```

Do not put business logic in AMD JavaScript.

---

# 32. AMD JavaScript

Suggested:

```text
amd/src/
    dashboard.js
    student.js
    intervention.js
```

JavaScript responsibilities:

- Render UI.
- Trigger actions.
- Handle loading states.
- Display notifications.
- Refresh affected components.

JavaScript should not:

- Calculate risk.
- Calculate recommendations.
- Decide permissions.
- Query Moodle database.
- Implement intervention business rules.

---

# 33. Capabilities

Maintain course-level capabilities:

```text
local/learningsuccess:viewcourse
local/learningsuccess:viewstudent
local/learningsuccess:createintervention
local/learningsuccess:manageintervention
local/learningsuccess:viewreports
local/learningsuccess:manageconfig
```

Review whether each capability is actually required.

Important:

```text
View student information
```

should remain appropriately protected because the plugin processes potentially sensitive educational information.

Continue using Moodle risk flags such as:

```text
RISK_PERSONAL
RISK_SPAM
RISK_CONFIG
```

where appropriate.

---

# 34. Privacy API

Implement Moodle Privacy API.

The plugin stores:

- Student/intervention relationship.
- Teacher intervention information.
- Notes.
- Follow-up dates.
- Intervention outcomes.
- Snapshots.

The privacy provider should support:

```text
metadata
user data export
user data deletion
```

Document:

- What data is stored.
- Why it is stored.
- Who can see it.
- How long it is retained.
- Whether data is sent externally.

No external service should be required by default.

---

# 35. Security

Every action must validate:

```text
course context
student access
teacher capability
intervention ownership/access
```

Never trust:

```text
userid
courseid
interventionid
```

from client-side requests.

Example:

```php
$context = context_course::instance($courseid);

require_capability(
    'local/learningsuccess:createintervention',
    $context
);
```

Also verify that the requested student belongs to the relevant course.

---

# 36. Data Minimisation

Do not duplicate Moodle data unnecessarily.

For example:

Do not continuously store:

```text
every login
every grade
every activity
every completion event
```

Instead:

```text
Read Moodle data when required.
```

Only store snapshots when they are necessary to evaluate an intervention.

---

# 37. Events

Use Moodle events when useful for:

- Intervention creation.
- Intervention status changes.
- Intervention resolution.
- Outcome recording.

Potential plugin events:

```text
intervention_created
intervention_updated
intervention_resolved
outcome_recorded
```

Events should be useful to other Moodle plugins and integrations.

Do not create events simply because an internal method was called.

---

# 38. Performance

Avoid querying every student individually.

Bad:

```text
For each student:
    query Analytics
    query grades
    query completion
    query activity
```

This creates N+1 queries.

Prefer:

```text
Get student IDs
      ↓
Bulk load required data
      ↓
Build lookup arrays
      ↓
Calculate summaries
```

Cache expensive read-only calculations where appropriate.

Do not permanently cache data that could create stale risk information without an explicit strategy.

---

# 39. Large Course Strategy

For large courses:

```text
Teacher opens dashboard
        ↓
Get relevant student IDs
        ↓
Prioritise
        ↓
Load details only for visible students
```

Do not calculate every expensive explanation for thousands of students on every page request.

Consider:

- Pagination.
- Lazy loading.
- Bulk queries.
- Scheduled precomputation for expensive data.
- Moodle caches where appropriate.

---

# 40. Groups

Support Moodle groups.

Teacher should be able to filter:

```text
All students
My groups
Group A
Group B
```

Use Moodle's group APIs rather than implementing custom group membership.

Respect group visibility and teacher permissions.

---

# 41. Configuration

Avoid a complicated teacher-facing configuration system.

Default rules should work immediately.

Later, administrators may configure:

```text
Learning Success policy

Enabled signals:
- Inactivity
- Overdue activities
- Grade decline
- Completion
- Moodle Analytics

Follow-up default:
7 days
```

Configuration should be administrator-oriented.

---

# 42. Recommendation Rules

Recommendation rules should be code-based initially.

Example:

```php
class inactivity_rule implements recommendation_rule {

    public function matches(
        signal_collection $signals
    ): bool {
        return $signals->has_inactivity_over(7);
    }

    public function get_recommendation(): recommendation {
        return new recommendation(
            type: 'checkin',
            reason: 'Student has been inactive for more than 7 days.'
        );
    }
}
```

Do not introduce a generic expression/rule language in the first version.

---

# 43. AI

AI should be optional and later.

Potential future use:

```text
Structured signals
       ↓
Optional AI
       ↓
Draft teacher message
```

For example:

```text
Student has not logged in for 9 days
and has 3 overdue activities.

Draft message:
"Hi John, I noticed..."
```

The teacher must review before sending.

AI must not replace the underlying explanation or risk calculation.

No external AI dependency should be required for the core plugin.

---

# 44. Recommended Repository Structure

Target structure:

```text
local/learningsuccess/

classes/
    external/
        dashboard_exporter.php
        student_exporter.php
        intervention_exporter.php

    local/
        risk/
            risk_provider.php
            risk_result.php
            moodle_analytics_provider.php
            fallback_provider.php

        signal/
            signal.php
            signal_collector.php
            inactivity_signal.php
            overdue_signal.php
            grade_decline_signal.php
            completion_signal.php
            analytics_risk_signal.php

        explanation/
            explanation.php
            explanation_engine.php

        recommendation/
            recommendation.php
            recommendation_engine.php
            rules/

        intervention/
            intervention.php
            intervention_manager.php
            intervention_status.php

        outcome/
            outcome.php
            outcome_evaluator.php
            snapshot_service.php

        service/
            student_success_service.php
            student_summary.php

    task/
        process_followups.php

db/
    access.php
    install.xml
    install.php
    upgrade.php
    tasks.php

privacy/
    provider.php

amd/
    src/
        dashboard.js
        student.js
        intervention.js

templates/
    dashboard.mustache
    student.mustache
    intervention.mustache

tests/
    ...
```

Adjust the exact structure to existing Moodle conventions and the current repository.

---

# 45. Testing Strategy

## PHPUnit

Test:

```text
risk provider
risk result
signals
explanation engine
recommendation rules
intervention lifecycle
follow-up logic
outcome evaluation
snapshot creation
permissions
```

Important examples:

```text
test_inactive_student_generates_signal()
test_active_student_has_no_inactivity_signal()
test_multiple_overdue_activities_generate_recommendation()
test_moodle_analytics_risk_is_used()
test_fallback_provider_is_used_when_analytics_unavailable()
test_intervention_status_transition_is_valid()
test_invalid_status_transition_is_rejected()
test_followup_is_due()
test_snapshot_contains_before_values()
test_outcome_detects_improvement()
```

---

# 46. Behat

Focus on teacher workflows.

Example scenarios:

```text
Teacher views Today's Priorities

Teacher opens student

Teacher sees explanation

Teacher starts intervention

Teacher sends Moodle message

Teacher schedules follow-up

Teacher sees follow-up notification

Teacher records outcome

Teacher sees before/after result
```

Avoid testing implementation details through Behat.

Test user-visible behaviour.

---

# 47. Privacy Tests

Test:

```text
User data export contains intervention data
User data deletion removes personal intervention data
Unauthorised teacher cannot view intervention
Unauthorised teacher cannot create intervention
```

---

# 48. Upgrade / Uninstall

Ensure:

```text
install.xml
upgrade.php
version.php
```

properly handle schema evolution.

Uninstall must remove plugin-owned data.

Do not delete Moodle-owned data.

---

# 49. Backup / Restore

Determine whether intervention data should participate in Moodle course backup/restore.

Recommended behaviour:

- Course-related configuration may be backed up.
- Student-specific intervention records should be treated carefully because they contain personal information.
- Do not blindly copy intervention history between courses.

Document the intended behaviour explicitly.

---

# 50. Reporting Boundary

Learning Success reports should answer:

> What happened after teachers intervened?

Not:

> What is every student's activity/grade/completion?

Good:

```text
Interventions this semester
Outcomes
Follow-ups
Improvement after intervention
Intervention types
```

Avoid:

```text
Total clicks
Daily login graph
Complete grade analytics
Course activity BI
```

Moodle already provides those capabilities.

---

# 51. MVP Definition

The first production-quality release should contain:

```text
[ ] Moodle Analytics integration
[ ] Risk provider abstraction
[ ] Risk result
[ ] Explanation engine
[ ] Core signals
[ ] Today's Priorities
[ ] Class Pulse
[ ] Student Success view
[ ] Recommendation engine
[ ] Intervention creation
[ ] Intervention lifecycle
[ ] Moodle Messaging integration
[ ] Follow-up dates
[ ] Scheduled follow-up task
[ ] Outcome recording
[ ] Before/after snapshots
[ ] Privacy API
[ ] Capabilities
[ ] PHPUnit
[ ] Behat
[ ] Moodle coding standards
```

Do not add AI before these are stable.

---

# 52. Development Phases

## Phase 1 — Foundation

```text
Risk abstraction
Moodle Analytics provider
Fallback provider
Signals
Explanation engine
Student summary
Today's Priorities
```

## Phase 2 — Teacher Actions

```text
Recommendations
Interventions
Moodle Messaging
Notes
Intervention history
```

## Phase 3 — Follow-up

```text
Follow-up dates
Scheduled task
Notifications
Intervention lifecycle
```

## Phase 4 — Outcomes

```text
Snapshots
Outcome recording
Before/after comparison
Effectiveness reporting
```

## Phase 5 — Advanced

```text
Admin policies
Configurable intervention types
Advisor workflows
Additional providers
Optional AI assistance
```

---

# 53. Architectural Rules

These rules should guide future development.

### Rule 1

**Do not recreate Moodle Analytics.**

### Rule 2

**Do not store Moodle data unnecessarily.**

### Rule 3

**Keep risk detection separate from intervention.**

### Rule 4

**Every recommendation should have an explanation.**

### Rule 5

**Every intervention should optionally have a follow-up.**

### Rule 6

**Every completed intervention should be able to record an outcome.**

### Rule 7

**Do not claim causation from before/after measurements.**

### Rule 8

**Use Moodle APIs instead of replacing Moodle functionality.**

### Rule 9

**Business logic belongs in PHP services/domain classes, not JavaScript.**

### Rule 10

**Teacher UI should answer "What should I do next?"**

---

# 54. Final Architecture

```text
                         MOODLE
                           │
          ┌────────────────┴────────────────┐
          │                                 │
          ▼                                 ▼
 Learning Analytics                    Moodle Data
          │                           Grade / Completion
          │                           Activity / Groups
          ▼                                 │
 ┌─────────────────────┐                    │
 │ Moodle Analytics    │                    │
 │ Risk Provider       │◄───────────────────┘
 └──────────┬──────────┘
            │
            ▼
 ┌───────────────────────────────┐
 │     Learning Success          │
 │                               │
 │  Risk Provider                │
 │       ↓                       │
 │  Signal Collector             │
 │       ↓                       │
 │  Explanation Engine           │
 │       ↓                       │
 │  Recommendation Engine        │
 │       ↓                       │
 │  Intervention Manager         │
 │       ↓                       │
 │  Follow-up                    │
 │       ↓                       │
 │  Outcome / Snapshot           │
 └───────────────┬───────────────┘
                 │
                 ▼
          ┌──────────────┐
          │   TEACHER    │
          │              │
          │ What needs   │
          │ attention?   │
          │              │
          │ Why?         │
          │              │
          │ What should  │
          │ I do?        │
          │              │
          │ Did it work? │
          └──────────────┘
```

---

# 55. Success Criteria

The plugin is successful if a teacher can go from:

```text
"I have 100 students."
```

to:

```text
"These 5 students need attention today."
```

then:

```text
"I understand why."
```

then:

```text
"I know what action I can take."
```

then:

```text
"I recorded what I did."
```

then:

```text
"I know when to follow up."
```

and finally:

```text
"I can see whether the student's situation improved."
```

without needing to understand Moodle Analytics internals.

---

# 56. Product Identity

The plugin should be positioned as:

```text
Moodle Analytics
        =
Student risk detection

Learning Success
        =
Student support workflow
```

Or more simply:

```text
Moodle Analytics → DETECT

Learning Success → ACT → FOLLOW UP → MEASURE
```

This boundary should be maintained throughout implementation.

The core value of `local_learningsuccess` is therefore **not better analytics**.

It is:

> **Turning Moodle's existing learning signals into practical, explainable, trackable teacher interventions.**