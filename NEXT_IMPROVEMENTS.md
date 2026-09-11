# Learning Success — Next Improvements

**Project:** `local_learningsuccess`  
**Purpose:** Implementation roadmap for the next architecture and UX improvements.

## Product boundary

Keep the product focused on:

```text
Moodle Analytics → DETECT
Learning Success → EXPLAIN → PRIORITISE → RECOMMEND → INTERVENE → FOLLOW UP → MEASURE
```

Do **not** turn Learning Success into another analytics/reporting system.

Avoid duplicating:
- Moodle Analytics risk detection
- Gradebook
- Completion
- Moodle Messaging
- Generic BI dashboards

The main value is helping teachers act on existing learning-risk signals.

---

# P0 — Fix architecture before adding features

## 1. Fix Moodle Analytics integration

### Goal

Use Moodle's Analytics APIs/abstractions as the primary risk source instead of treating Analytics database tables as the application API.

### Current concern

The Moodle Analytics provider directly queries Analytics tables and assumes relationships around prediction/sample data.

This is fragile because Analytics internals can change and because tables such as `analytics_predict_samples` represent analysis samples rather than simply being a row-per-student prediction table.

### Required changes

1. Inspect the Moodle version supported by this plugin.
2. Identify the supported Analytics API for retrieving predictions/risk information.
3. Keep all Analytics-specific code inside:

```text
classes/local/risk/moodle_analytics_provider.php
```

4. Prefer Moodle Analytics manager/model/prediction APIs.
5. Do not allow UI/service classes to query Analytics tables directly.
6. If a direct DB query is genuinely unavoidable:
   - isolate it in the provider;
   - document exactly what the query represents;
   - add tests for the expected Analytics data shape.

### Acceptance criteria

- `student_success_service` does not query Analytics tables.
- Explanation/recommendation code does not know how Analytics predictions are stored.
- Analytics provider can be replaced without changing the rest of the application.
- Missing/unavailable Analytics data is handled safely.

---

## 2. Make risk calculation consistent everywhere

### Problem

Student detail and course summary must not calculate risk differently.

The course-level `batch_explain_students()` currently contains its own inactivity/grade/completion risk calculation.

That can produce:

```text
Course dashboard: HIGH RISK
Student page:     MEDIUM RISK
```

### Required architecture

All risk must go through the same provider abstraction.

Recommended interface:

```php
interface risk_provider {
    public function get_risk(int $userid, int $courseid): ?risk_result;

    /**
     * @param int[] $userids
     * @return array<int, risk_result>
     */
    public function get_risks(array $userids, int $courseid): array;
}
```

### Rules

- `get_risk()` is the single-student path.
- `get_risks()` is the bulk path.
- Both must use the same underlying logic.
- Bulk processing is for performance, not for a different risk algorithm.

### Acceptance criteria

For the same student/course/time:

```text
get_risk(userid, courseid)
```

and:

```text
get_risks([userid], courseid)[userid]
```

produce equivalent results.

No hard-coded risk scoring remains in `student_success_service`.

---

## 3. Remove duplicated risk logic from the service layer

Refactor:

```text
classes/local/service/student_success_service.php
```

so it orchestrates services rather than calculating risk.

Target:

```text
StudentSuccessService
    ↓
RiskProvider
    ↓
ExplanationEngine
    ↓
RecommendationEngine
```

The service should not contain rules such as:

```text
if inactive...
if grade declined...
if completion low...
```

Those belong to the risk/signal layer.

---

# P0 — Normalize intervention lifecycle

## 4. Define one intervention status model

There are currently overlapping status names/aliases such as:

```text
OPEN
CONTACTED
WAITING
FOLLOW_UP
COMPLETED
DISMISSED
IN_PROGRESS
```

Do not maintain multiple competing status vocabularies.

### Recommended lifecycle

```text
OPEN
  ↓
CONTACTED
  ↓
WAITING
  ↓
FOLLOW_UP
  ↓
COMPLETED
```

Alternative terminal path:

```text
OPEN / CONTACTED / WAITING / FOLLOW_UP
  ↓
DISMISSED
```

Optional:

```text
CONTACTED → UNABLE_TO_CONTACT
```

Only add `UNABLE_TO_CONTACT` if it has a clear UI/use case.

### Implementation

Create one authoritative status definition, for example:

```text
classes/local/intervention/intervention_status.php
```

It should provide:

- valid statuses;
- valid transitions;
- labels;
- terminal-state detection;
- transition validation.

Example concept:

```php
public static function can_transition(
    string $from,
    string $to
): bool;
```

### Important

Do not allow arbitrary status updates directly from controllers/external functions.

All transitions should pass through the intervention manager.

---

# P1 — Build an actionability layer

## 5. Add Actionability Engine

Risk is not the same thing as action priority.

Example:

```text
Student A
Risk: high
Contacted yesterday
Waiting for response

Student B
Risk: medium
No intervention
No activity for 10 days
```

Student B may deserve attention first.

### Add

```text
classes/local/actionability/
    actionability_engine.php
    actionability_result.php
```

### Inputs

```text
Risk
Signals
Existing intervention
Follow-up state
Recency
Course context
```

### Suggested output

```text
NO_ACTION
MONITOR
RECOMMEND
URGENT
FOLLOW_UP
```

### Example

```text
High risk
+ no active intervention
+ severe recent deterioration
= URGENT
```

```text
High risk
+ contacted yesterday
+ waiting
= MONITOR
```

```text
Medium risk
+ follow-up due today
= FOLLOW_UP
```

### Principle

The teacher should see:

> What should I do now?

rather than:

> What is the student's numeric risk score?

---

# P1 — Make Today's Priorities intervention-aware

## 6. Replace risk-only prioritisation

Today's priority list should consider:

```text
Risk
+ urgency
+ recency
+ actionability
+ existing intervention
+ follow-up due date
```

### Suggested priority categories

```text
URGENT
FOLLOW_UP
RECOMMEND
MONITOR
NO_ACTION
```

### Example

```text
URGENT

John Smith
No activity for 9 days
3 overdue activities
No intervention yet

[Check in]
```

```text
FOLLOW UP

David Nguyen
Check-in completed 7 days ago
Follow-up is due today

[Review]
```

Do not repeatedly recommend the same intervention when one is already active.

---

# P1 — Prevent duplicate interventions

## 7. Add intervention-aware recommendation filtering

Before creating a recommendation:

1. Check active interventions for the student/course.
2. Check intervention type.
3. Check status.
4. Check when the last intervention occurred.
5. Determine whether a new intervention is actually needed.

Example:

```text
Signal: inactivity
Existing intervention: inactivity check-in
Status: WAITING
```

Do not generate another identical check-in.

Instead:

```text
FOLLOW_UP
```

---

# P1 — Primary recommendation + alternatives

## 8. Reduce decision fatigue

The recommendation engine can produce multiple recommendations, but the UI should distinguish:

```text
Primary recommendation
Alternative actions
```

Example:

```text
Recommended

Check in with the student
Reason:
No activity for 9 days and 3 overdue activities.

[Start intervention]

Other options:
- Offer assignment support
- Schedule a short meeting
```

The system should make one clear recommendation while preserving teacher choice.

---

# P1 — Suggested teacher messages

## 9. Add message templates based on signals

This should use Moodle Messaging for delivery rather than creating another messaging system.

### Examples

#### Inactivity

```text
Hi {firstname},

I noticed you haven't been active in the course recently.
Is everything going okay? Let me know if you need any help getting back on track.
```

#### Overdue activities

```text
Hi {firstname},

I noticed you have some overdue activities in the course.
If you're having trouble completing them, I can help you work out what to tackle first.
```

#### Grade decline

```text
Hi {firstname},

I noticed your recent assessment results have dropped.
Would you like to discuss any areas where you're finding the course difficult?
```

### Requirements

- Templates must be editable.
- Use Moodle language strings.
- Do not expose unnecessary student information.
- Do not send automatically without explicit teacher action.
- Actual delivery should use Moodle Messaging.

---

# P1 — Teacher dismissal / override

## 10. Allow teachers to dismiss a signal

Not every unusual learning pattern is a problem.

Example:

```text
Low activity
+
92% grade
+
94% completion
```

The system should avoid forcing an intervention.

### Add

```text
Dismiss
```

with optional reason:

```text
Student on approved leave
Student working offline
False positive
Not currently relevant
Other
```

### Store

At minimum:

```text
signal
userid
courseid
dismissedby
dismissedat
reason
```

### Behaviour

A dismissed signal should not immediately recreate the same recommendation on the next page load.

Define a clear reactivation policy.

---

# P2 — Improve signal model

## 11. Decide whether signals are current or historical

Current signal storage appears to replace the student's existing signals.

That is acceptable for a simple current-state model, but a stronger product needs signal history.

### Recommended future structure

```text
local_ls_signal

id
userid
courseid
signaltype
value
firstseen
lastseen
active
dismissed
dismissedby
dismissedat
timecreated
timemodified
```

### Benefits

The UI can answer:

```text
New concern
```

versus:

```text
Ongoing concern
```

and:

```text
First detected: 12 days ago
Still active
```

Do not implement full history until the P0 architecture is stable.

---

# P2 — Canonical snapshots

## 12. Remove duplicate snapshot storage

Current intervention data appears to have both:

```text
before_snapshot
after_snapshot
```

JSON fields and separate snapshot records.

Avoid maintaining two representations of the same information.

### Recommended

Make:

```text
local_ls_snapshot
```

the canonical source.

Example:

```text
intervention
    ↓
snapshot
    ├── BEFORE
    └── FOLLOWUP
```

The intervention should reference snapshots rather than duplicating their contents.

### Benefits

- simpler queries;
- less duplicated data;
- easier future reporting;
- cleaner privacy handling;
- clearer historical semantics.

---

# P2 — Separate system evidence from teacher outcome

## 13. Do not treat automatic metric improvement as teacher-confirmed success

Current automatic evaluation after intervention completion is useful, but it should be treated as evidence.

Use:

```text
SYSTEM EVIDENCE
```

and:

```text
TEACHER OUTCOME
```

separately.

### System evidence

Example:

```text
Activity increased from 0.8/week to 3.2/week.
Completion increased from 61% to 78%.
```

Use neutral language:

> Indicators improved after the intervention.

Do not claim:

> The intervention caused the improvement.

### Teacher outcome

Allow:

```text
Improved
No change
Declined
Unable to contact
Not applicable
```

The teacher can confirm the final outcome.

---

# P2 — Add "Why now?"

## 14. Explain why the student is currently prioritised

The teacher should not have to interpret multiple numbers.

Example:

```text
Why now?

- No activity for 9 days
- 3 overdue activities
- Completion dropped 12% over the last 2 weeks
- No active intervention
```

This should be generated from structured signals/actionability data.

---

# P2 — Add "What changed?"

## 15. Compare current state with previous state

Where historical snapshots exist, show meaningful changes:

```text
Activity
Before: 0.8/week
Now:    3.1/week
↑ Improved
```

```text
Completion
Before: 61%
Now:    78%
↑ Improved
```

Avoid charts unless they genuinely improve the teacher workflow.

Simple before/after values are often sufficient.

---

# P2 — Recommendation-specific follow-up

## 16. Stop using one universal 7-day follow-up

Follow-up timing should depend on intervention type.

Suggested defaults:

```text
Check-in                3 days
Assignment support     7 days
Meeting requested      3 days
Progress monitoring    7 days
```

Teachers should be able to override the suggested date.

Store the actual due date on the intervention.

---

# P2 — Improve terminology

## 17. Avoid causal/promotional language

Prefer:

```text
Recent outcomes
```

or:

```text
Recent improvements
```

instead of:

```text
Success stories
```

Prefer:

```text
Indicators improved after intervention
```

instead of:

```text
Intervention succeeded
```

This keeps the system evidence-based.

---

# P2 — Privacy/data minimisation

## 18. Reduce unnecessary student data exposure

Avoid displaying student email addresses where they are not needed.

Prefer:

```text
Full name
Profile/avatar
Moodle profile link
Messaging action
```

Use Moodle's existing user/profile/messaging functionality.

### General rule

Only store/display data needed to support the intervention workflow.

---

# P3 — UX target

The dashboard should be work-queue-first.

Recommended structure:

```text
TODAY

3 students need action
2 follow-ups due
1 intervention unresolved


NEEDS ACTION

John Smith
No activity 9 days
3 overdue activities

[Check in]


FOLLOW UP

David Nguyen
Check-in 7 days ago
Follow-up due today

[Review]


RECENT OUTCOMES

Michael Nguyen
Indicators improved after assignment support

[View]
```

Class-level metrics can remain secondary.

The primary UX questions are:

1. Who needs me?
2. Why now?
3. What should I do?
4. What happened after I acted?

---

# Testing requirements

Every architectural change should include tests.

## PHPUnit

At minimum:

### Risk provider

- Analytics prediction available.
- Analytics prediction unavailable.
- Invalid/missing course.
- Invalid/missing user.
- Bulk and single risk results are consistent.
- Fallback provider is used only when intended.

### Actionability

Test:

```text
NO_ACTION
MONITOR
RECOMMEND
URGENT
FOLLOW_UP
```

including existing interventions.

### Intervention lifecycle

Test every valid transition and invalid transition.

Example:

```text
OPEN → CONTACTED       valid
CONTACTED → WAITING    valid
WAITING → FOLLOW_UP    valid
FOLLOW_UP → COMPLETED  valid

COMPLETED → OPEN       invalid
DISMISSED → CONTACTED  invalid
```

### Recommendation

Test:

- primary recommendation;
- alternatives;
- duplicate intervention suppression;
- recommendation-specific follow-up.

### Signal dismissal

Test:

- dismiss;
- reason;
- persistence;
- no immediate recreation;
- reactivation policy.

### Outcome

Test:

- system evidence;
- teacher outcome;
- before/after snapshot comparison;
- no causal wording in stored result.

---

# Behat

Add/maintain end-to-end scenarios for the main teacher workflow:

```text
Open course
→ View students needing attention
→ Open student
→ See why now
→ See recommendation
→ Start intervention
→ Send/edit message
→ Intervention becomes CONTACTED
→ Follow-up becomes due
→ Review follow-up
→ Complete intervention
→ Review system evidence
→ Record teacher outcome
```

Also test:

```text
Dismiss signal
```

and:

```text
Existing intervention prevents duplicate recommendation
```

Do not add Behat coverage for every internal implementation detail.

---

# Implementation order

Implement in this order.

## Phase 1 — Architecture

```text
[ ] Fix Moodle Analytics provider
[ ] Verify correct Analytics API usage
[ ] Add bulk risk provider
[ ] Remove hard-coded risk calculation from service
[ ] Make student/course views use the same risk provider
```

## Phase 2 — Intervention correctness

```text
[ ] Normalize intervention statuses
[ ] Centralise transition validation
[ ] Update scheduled task to use the same lifecycle
[ ] Add transition tests
```

## Phase 3 — Actionability

```text
[ ] Add actionability_result
[ ] Add actionability_engine
[ ] Make priority intervention-aware
[ ] Suppress duplicate interventions
[ ] Select primary recommendation
[ ] Keep alternatives available
```

## Phase 4 — Teacher workflow

```text
[ ] Add message templates
[ ] Add explicit teacher send action
[ ] Add signal dismissal
[ ] Add "Why now?"
[ ] Add "What changed?"
```

## Phase 5 — Follow-up and outcomes

```text
[ ] Recommendation-specific follow-up defaults
[ ] Canonical snapshot storage
[ ] Separate system evidence from teacher outcome
[ ] Add teacher outcome selection
```

## Phase 6 — Historical intelligence

```text
[ ] Signal history
[ ] first_seen / last_seen
[ ] ongoing/new concern
[ ] improved/declined state
```

---

# What NOT to implement yet

Do not add these until the above architecture is stable:

- AI-generated risk scores
- LLM-based student risk prediction
- large analytics charts
- custom predictive models
- complex dashboards
- generic reporting
- another messaging subsystem
- automatic intervention sending
- causal-effect claims
- unnecessary student profile data
- complicated graph visualisations

AI can be added later for optional tasks such as:

```text
Draft a personalised teacher message
```

but it should remain optional and sit on top of the deterministic intervention workflow.

---

# Definition of done

The next major version is in good shape when:

```text
Moodle Analytics
      ↓
Risk Provider
      ↓
Signals
      ↓
Explanation
      ↓
Actionability
      ↓
Recommendation
      ↓
Intervention
      ↓
Follow-up
      ↓
System Evidence
      ↓
Teacher Outcome
```

and there is exactly one consistent path for risk calculation.

The teacher experience should be:

```text
Who needs me?
      ↓
Why now?
      ↓
What should I do?
      ↓
Act
      ↓
When should I check again?
      ↓
Did the indicators improve?
      ↓
Record outcome
```

The product should remain an **intervention workflow layer for Moodle Analytics**, not another analytics engine.
